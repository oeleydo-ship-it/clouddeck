<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Services\SystemSettings;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_sign_in_form_offers_a_way_to_start_a_reset(): void
    {
        User::factory()->create();

        // The routes existed but nothing linked to them, which made the whole flow
        // unreachable for anyone who did not know the URL.
        $this->get('/login')->assertOk()->assertSee(route('password.request'), false);
    }

    public function test_a_reset_link_is_emailed_and_carries_the_platform_name(): void
    {
        Notification::fake();
        app(SystemSettings::class)->put('platform_name', 'RecurringPress');
        $user = User::factory()->create(['email' => 'owner@example.com']);

        $this->post('/forgot-password', ['email' => 'owner@example.com'])->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPasswordNotification::class, function ($notification) use ($user) {
            $mail = $notification->toMail($user);
            $this->assertStringContainsString('RecurringPress', $mail->subject);
            $this->assertStringContainsString(route('password.reset', ['token' => $notification->token, 'email' => $user->email]), $mail->actionUrl);

            return true;
        });

        // The framework's own generic message would name the wrong product.
        Notification::assertNotSentTo($user, ResetPassword::class);
    }

    public function test_an_unknown_address_is_refused_rather_than_silently_accepted(): void
    {
        Notification::fake();
        User::factory()->create(['email' => 'somebody@example.com']);

        $this->post('/forgot-password', ['email' => 'nobody@example.com'])->assertSessionHasErrors('email');

        Notification::assertNothingSent();
    }

    public function test_a_password_is_actually_changed_and_the_user_can_sign_in_with_it(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com', 'password' => Hash::make('the-old-password')]);
        $token = app('auth.password.broker')->createToken($user);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => 'owner@example.com',
            'password' => 'a-much-longer-password',
            'password_confirmation' => 'a-much-longer-password',
        ])->assertRedirect('/login')->assertSessionHas('status');

        $this->assertTrue(Hash::check('a-much-longer-password', $user->fresh()->password));
        $this->post('/login', ['email' => 'owner@example.com', 'password' => 'a-much-longer-password'])->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    public function test_a_token_cannot_be_reused_once_it_has_been_spent(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com']);
        $token = app('auth.password.broker')->createToken($user);
        $payload = ['token' => $token, 'email' => 'owner@example.com', 'password' => 'a-much-longer-password', 'password_confirmation' => 'a-much-longer-password'];

        $this->post('/reset-password', $payload)->assertRedirect('/login');
        $this->post('/reset-password', [...$payload, 'password' => 'another-long-password', 'password_confirmation' => 'another-long-password'])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('a-much-longer-password', $user->fresh()->password));
    }

    public function test_someone_elses_token_does_not_reset_this_account(): void
    {
        $victim = User::factory()->create(['email' => 'victim@example.com', 'password' => Hash::make('the-old-password')]);
        $attacker = User::factory()->create(['email' => 'attacker@example.com']);
        $token = app('auth.password.broker')->createToken($attacker);

        $this->post('/reset-password', [
            'token' => $token, 'email' => 'victim@example.com',
            'password' => 'a-much-longer-password', 'password_confirmation' => 'a-much-longer-password',
        ])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('the-old-password', $victim->fresh()->password));
    }

    public function test_a_short_or_unconfirmed_password_is_refused(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com', 'password' => Hash::make('the-old-password')]);
        $token = app('auth.password.broker')->createToken($user);

        $this->post('/reset-password', ['token' => $token, 'email' => 'owner@example.com', 'password' => 'short', 'password_confirmation' => 'short'])->assertSessionHasErrors('password');
        $this->post('/reset-password', ['token' => $token, 'email' => 'owner@example.com', 'password' => 'a-much-longer-password', 'password_confirmation' => 'a-different-password'])->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('the-old-password', $user->fresh()->password));
    }

    public function test_the_reset_flow_works_with_the_public_site_turned_off(): void
    {
        Notification::fake();
        app(SystemSettings::class)->put('public_site_enabled', '0', 'boolean');
        $user = User::factory()->create(['email' => 'owner@example.com']);

        $this->get('/forgot-password')->assertOk();
        $this->post('/forgot-password', ['email' => 'owner@example.com'])->assertSessionHas('status');
        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }
}
