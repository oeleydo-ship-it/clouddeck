<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Services\TwoFactorService;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class AccountSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_factor_user_must_complete_challenge_before_login(): void
    {
        $secret = app(TwoFactorService::class)->generateSecret();
        $user = User::factory()->create(['password' => 'correct-password-123', 'two_factor_secret' => $secret, 'two_factor_confirmed_at' => now(), 'two_factor_recovery_codes' => []]);

        $this->post('/login', ['email' => $user->email, 'password' => 'correct-password-123'])->assertRedirect('/two-factor-challenge');
        $this->assertGuest();

        $code = app(Google2FA::class)->getCurrentOtp($secret);
        $this->post('/two-factor-challenge', ['code' => $code])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_recovery_code_is_single_use(): void
    {
        $service = app(TwoFactorService::class);
        $user = User::factory()->create(['two_factor_recovery_codes' => $service->hashRecoveryCodes(['alpha-bravo'])]);

        $this->assertTrue($service->consumeRecoveryCode($user, 'alpha-bravo'));
        $this->assertFalse($service->consumeRecoveryCode($user->fresh(), 'alpha-bravo'));
    }

    public function test_api_token_is_created_with_expiry_and_can_be_revoked(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user)->post('/account/tokens', ['name' => 'CI'])->assertSessionHas('plain_token');
        $token = $user->tokens()->firstOrFail();
        $this->assertNotNull($token->expires_at);

        $this->actingAs($user)->delete("/account/tokens/{$token->id}")->assertRedirect();
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->id]);
    }

    public function test_password_reset_link_can_be_requested(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHas('status');
        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_changing_email_revokes_verification_and_sends_a_new_link(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)->patch('/account/profile', ['name' => $user->name, 'email' => 'new@example.test', 'timezone' => 'Asia/Dubai'])->assertRedirect();

        $this->assertNull($user->fresh()->email_verified_at);
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_read_only_token_cannot_mutate_servers(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $token = $user->createToken('readonly', ['servers:read'])->plainTextToken;

        $this->withToken($token)->getJson('/api/servers')->assertOk();
        $this->withToken($token)->postJson('/api/servers', [])->assertForbidden();
    }

    public function test_login_attempts_are_rate_limited(): void
    {
        $user = User::factory()->create(['password' => 'correct-password-123']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password'])->assertSessionHasErrors('email');
        }

        $this->post('/login', ['email' => $user->email, 'password' => 'correct-password-123'])->assertStatus(429);
    }
}
