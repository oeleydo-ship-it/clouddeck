<?php

namespace Tests\Feature;

use App\Models\DnsAccount;
use App\Models\DnsZone;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CloudflareDnsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_token_is_proved_against_cloudflare_before_it_is_stored(): void
    {
        Http::fake([
            'https://api.cloudflare.com/client/v4/user/tokens/verify' => Http::response(['success' => true, 'result' => ['status' => 'active']]),
            'https://api.cloudflare.com/client/v4/zones*' => Http::response(['success' => true, 'result' => []]),
        ]);

        $this->actingAs($user = User::factory()->create())
            ->post('/dns/accounts', ['name' => 'Cloudflare', 'token' => str_repeat('t', 40)])
            ->assertRedirect(route('dns.index'))->assertSessionHas('status');

        $this->assertDatabaseHas('dns_accounts', ['user_id' => $user->id, 'name' => 'Cloudflare', 'provider' => 'cloudflare']);
    }

    public function test_a_rejected_token_is_reported_rather_than_stored(): void
    {
        Http::fake(['https://api.cloudflare.com/client/v4/user/tokens/verify' => Http::response(['success' => false], 401)]);

        $this->actingAs($user = User::factory()->create())
            ->post('/dns/accounts', ['name' => 'Cloudflare', 'token' => str_repeat('t', 40)])
            ->assertSessionHasErrors('token');

        $this->assertDatabaseCount('dns_accounts', 0);
    }

    public function test_cloudflares_own_reason_is_repeated_back_rather_than_a_generic_sentence(): void
    {
        Http::fake(['https://api.cloudflare.com/client/v4/user/tokens/verify' => Http::response([
            'success' => false,
            'errors' => [['code' => 1000, 'message' => 'Invalid API Token']],
        ], 401)]);

        $this->actingAs(User::factory()->create())
            ->post('/dns/accounts', ['name' => 'Cloudflare', 'token' => str_repeat('t', 40)])
            ->assertSessionHasErrors('token');

        // The code is what Cloudflare's docs and support are indexed by, so it travels too.
        $message = session('errors')->first('token');
        $this->assertStringContainsString('Invalid API Token', $message);
        $this->assertStringContainsString('code 1000', $message);
    }

    public function test_a_nested_cloudflare_error_is_not_swallowed_by_its_wrapper(): void
    {
        // A 6003 carries the real cause underneath; reporting only the outer message would
        // say "Invalid request headers" and leave the actual problem unmentioned.
        Http::fake(['https://api.cloudflare.com/client/v4/user/tokens/verify' => Http::response([
            'success' => false,
            'errors' => [['code' => 6003, 'message' => 'Invalid request headers', 'error_chain' => [['code' => 6111, 'message' => 'Invalid format for Authorization header']]]],
        ], 400)]);

        $this->actingAs(User::factory()->create())
            ->post('/dns/accounts', ['name' => 'Cloudflare', 'token' => str_repeat('t', 40)])
            ->assertSessionHasErrors('token');

        $this->assertStringContainsString('Invalid format for Authorization header', session('errors')->first('token'));
    }

    public function test_a_global_api_key_is_named_as_the_mistake_it_is(): void
    {
        Http::fake(['https://api.cloudflare.com/client/v4/user/tokens/verify' => Http::response(['success' => false, 'errors' => []], 400)]);

        // 37 hex characters is the shape of a Global API Key, which is not a bearer token
        // at all and otherwise fails looking exactly like a typo.
        $this->actingAs(User::factory()->create())
            ->post('/dns/accounts', ['name' => 'Cloudflare', 'token' => str_repeat('a1b2', 9).'c'])
            ->assertSessionHasErrors('token');

        $this->assertStringContainsString('Global API Key', session('errors')->first('token'));
    }

    public function test_an_inactive_token_is_refused_even_though_the_call_succeeded(): void
    {
        Http::fake(['https://api.cloudflare.com/client/v4/user/tokens/verify' => Http::response(['success' => true, 'result' => ['status' => 'disabled']])]);

        $this->actingAs(User::factory()->create())
            ->post('/dns/accounts', ['name' => 'Cloudflare', 'token' => str_repeat('t', 40)])
            ->assertSessionHasErrors('token');
    }

    public function test_the_stored_token_is_encrypted_at_rest(): void
    {
        $account = $this->account();

        // The raw column must not contain the token, or "encrypted" is decoration.
        $this->assertStringNotContainsString('secret-token-value', (string) $account->getRawOriginal('credentials'));
        $this->assertSame('secret-token-value', $account->fresh()->credentials['token']);
    }

    public function test_importing_zones_records_what_cloudflare_actually_holds(): void
    {
        $account = $this->account();
        Http::fake(['https://api.cloudflare.com/client/v4/zones*' => Http::response(['success' => true, 'result' => [
            ['id' => 'zone-1', 'name' => 'example.com', 'status' => 'active'],
            ['id' => 'zone-2', 'name' => 'example.org', 'status' => 'pending'],
        ]])]);

        $this->actingAs($account->user)->post("/dns/accounts/{$account->id}/sync")->assertRedirect();

        $this->assertDatabaseHas('dns_zones', ['provider_zone_id' => 'zone-1', 'name' => 'example.com', 'status' => 'active']);
        $this->assertDatabaseHas('dns_zones', ['provider_zone_id' => 'zone-2', 'name' => 'example.org', 'status' => 'pending']);
    }

    public function test_importing_twice_does_not_duplicate_a_zone(): void
    {
        $account = $this->account();
        Http::fake(['https://api.cloudflare.com/client/v4/zones*' => Http::response(['success' => true, 'result' => [
            ['id' => 'zone-1', 'name' => 'example.com', 'status' => 'active'],
        ]])]);

        $this->actingAs($account->user)->post("/dns/accounts/{$account->id}/sync");
        $this->actingAs($account->user)->post("/dns/accounts/{$account->id}/sync");

        // Two rows for one zone would mean two pages editing the same records.
        $this->assertDatabaseCount('dns_zones', 1);
    }

    public function test_records_are_read_live_rather_than_from_a_local_copy(): void
    {
        $zone = $this->zone();
        Http::fake(['*/zones/zone-1/dns_records*' => Http::response(['success' => true, 'result' => [
            ['id' => 'rec-1', 'type' => 'A', 'name' => 'example.com', 'content' => '203.0.113.10', 'ttl' => 1, 'proxied' => false],
        ]])]);

        $this->actingAs($zone->user)->get("/dns/zones/{$zone->id}")
            ->assertOk()->assertSee('203.0.113.10')->assertSee('example.com');
    }

    public function test_a_record_is_created_with_the_values_that_were_submitted(): void
    {
        $zone = $this->zone();
        Http::fake(['*' => Http::response(['success' => true, 'result' => ['id' => 'rec-1']])]);

        $this->actingAs($zone->user)->post("/dns/zones/{$zone->id}/records", [
            'type' => 'A', 'name' => 'www.example.com', 'content' => '203.0.113.10', 'ttl' => 300, 'proxied' => '1',
        ])->assertRedirect()->assertSessionHas('status');

        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && str_contains($request->url(), '/zones/zone-1/dns_records')
            && $request['type'] === 'A'
            && $request['content'] === '203.0.113.10'
            && $request['ttl'] === 300
            && $request['proxied'] === true);
    }

    public function test_proxying_is_not_claimed_for_a_type_that_cannot_be_proxied(): void
    {
        $zone = $this->zone();
        Http::fake(['*' => Http::response(['success' => true, 'result' => []])]);

        // Cloudflare rejects `proxied` on a TXT record, so sending it would fail the change.
        $this->actingAs($zone->user)->post("/dns/zones/{$zone->id}/records", [
            'type' => 'TXT', 'name' => 'example.com', 'content' => 'v=spf1 -all', 'ttl' => 1, 'proxied' => '1',
        ])->assertRedirect();

        Http::assertSent(fn ($request) => $request['proxied'] === false);
    }

    public function test_a_rejection_from_cloudflare_is_shown_in_its_own_words(): void
    {
        $zone = $this->zone();
        // 200 with success:false is how Cloudflare refuses a change, so the status code
        // alone would read as though the record had been created.
        Http::fake(['*' => Http::response(['success' => false, 'errors' => [['message' => 'Record already exists.']]])]);

        $this->actingAs($zone->user)->post("/dns/zones/{$zone->id}/records", [
            'type' => 'A', 'name' => 'example.com', 'content' => '203.0.113.10', 'ttl' => 1,
        ])->assertSessionHasErrors('dns');

        $this->assertStringContainsString('Record already exists.', session('errors')->first('dns'));
    }

    public function test_a_record_can_be_deleted(): void
    {
        $zone = $this->zone();
        Http::fake(['*' => Http::response(['success' => true, 'result' => ['id' => 'rec-1']])]);

        $this->actingAs($zone->user)->delete("/dns/zones/{$zone->id}/records/rec-1")->assertRedirect()->assertSessionHas('status');

        Http::assertSent(fn ($request) => $request->method() === 'DELETE' && str_contains($request->url(), '/dns_records/rec-1'));
    }

    public function test_a_stranger_cannot_read_or_change_someone_elses_zone(): void
    {
        $zone = $this->zone();
        $stranger = User::factory()->create();
        Http::fake(['*' => Http::response(['success' => true, 'result' => []])]);

        $this->actingAs($stranger)->get("/dns/zones/{$zone->id}")->assertNotFound();
        $this->actingAs($stranger)->post("/dns/zones/{$zone->id}/records", [
            'type' => 'A', 'name' => 'example.com', 'content' => '203.0.113.10', 'ttl' => 1,
        ])->assertNotFound();
        $this->actingAs($stranger)->delete("/dns/zones/{$zone->id}/records/rec-1")->assertNotFound();

        Http::assertNothingSent();
    }

    private function account(): DnsAccount
    {
        $user = User::factory()->create();

        return DnsAccount::create([
            'user_id' => $user->id,
            'name' => 'Cloudflare',
            'provider' => 'cloudflare',
            'credentials' => ['token' => 'secret-token-value'],
            'validated_at' => now(),
        ]);
    }

    private function zone(): DnsZone
    {
        $account = $this->account();

        return DnsZone::create([
            'dns_account_id' => $account->id,
            'user_id' => $account->user_id,
            'name' => 'example.com',
            'provider_zone_id' => 'zone-1',
            'status' => 'active',
            'synced_at' => now(),
        ]);
    }
}
