<?php

namespace Tests\Feature;

use App\Models\CloudAccount;
use App\Models\Server;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class TeamInfrastructureTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_roles_control_shared_server_access(): void
    {
        [$owner, $team, $server] = $this->sharedServer();
        $operator = User::factory()->create(['email_verified_at' => now()]);
        $viewer = User::factory()->create(['email_verified_at' => now()]);
        $team->memberships()->create(['user_id' => $operator->id, 'role' => 'operator', 'accepted_at' => now()]);
        $team->memberships()->create(['user_id' => $viewer->id, 'role' => 'viewer', 'accepted_at' => now()]);

        $this->assertTrue(Gate::forUser($operator)->allows('view', $server));
        $this->assertTrue(Gate::forUser($operator)->allows('update', $server));
        $this->assertFalse(Gate::forUser($operator)->allows('delete', $server));
        $this->assertTrue(Gate::forUser($viewer)->allows('view', $server));
        $this->assertFalse(Gate::forUser($viewer)->allows('update', $server));
        // The browsable server list lives on the dedicated servers page, not the dashboard.
        $this->actingAs($viewer)->get('/servers')->assertOk()->assertSee($server->name);
        $this->actingAs($viewer)->get('/dashboard')->assertOk();
    }

    public function test_owner_can_transfer_a_server_to_and_from_a_team(): void
    {
        [$owner, $team, $server] = $this->sharedServer(false);

        $this->actingAs($owner)->patch("/servers/{$server->id}/team", [
            'team_id' => $team->id,
            'confirmation' => $server->hostname,
        ])->assertSessionHas('status');
        $this->assertSame($team->id, $server->fresh()->team_id);

        $this->actingAs($owner)->patch("/servers/{$server->id}/team", [
            'team_id' => null,
            'confirmation' => $server->hostname,
        ])->assertSessionHas('status');
        $this->assertNull($server->fresh()->team_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'server.team_transferred', 'auditable_id' => $server->id]);
    }

    public function test_user_can_only_switch_to_an_accepted_team(): void
    {
        [$owner, $team] = $this->sharedServer();
        $outsider = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($owner)->post("/teams/{$team->id}/switch")->assertSessionHas('status');
        $this->assertSame($team->id, $owner->fresh()->current_team_id);
        $this->actingAs($outsider)->post("/teams/{$team->id}/switch")->assertForbidden();
    }

    public function test_removing_a_member_clears_their_active_workspace(): void
    {
        [$owner, $team] = $this->sharedServer();
        $member = User::factory()->create(['email_verified_at' => now()]);
        $membership = $team->memberships()->create(['user_id' => $member->id, 'role' => 'operator', 'accepted_at' => now()]);
        $member->update(['current_team_id' => $team->id]);

        $this->actingAs($owner)->delete("/teams/{$team->id}/members/{$membership->id}")->assertSessionHas('status');

        $this->assertNull($member->fresh()->current_team_id);
    }

    private function sharedServer(bool $assigned = true): array
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $team = Team::create(['owner_id' => $owner->id, 'name' => 'Platform', 'slug' => 'platform-'.str()->random(6)]);
        $team->memberships()->create(['user_id' => $owner->id, 'role' => 'owner', 'accepted_at' => now()]);
        $account = CloudAccount::create(['user_id' => $owner->id, 'name' => 'Production', 'provider' => 'digitalocean', 'credentials' => ['token' => 'secret'], 'validated_at' => now()]);
        $server = Server::create([
            'user_id' => $owner->id,
            'team_id' => $assigned ? $team->id : null,
            'cloud_account_id' => $account->id,
            'name' => 'Shared application',
            'hostname' => 'shared-app-01',
            'region' => 'nyc3',
            'size' => 's-1vcpu-1gb',
            'image' => 'ubuntu-24-04-x64',
        ]);

        return [$owner, $team, $server];
    }
}
