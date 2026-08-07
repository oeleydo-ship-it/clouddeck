<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->markInstalled();
    }

    public function test_intended_redirect_rejects_external_urls(): void
    {
        $user = User::factory()->create([
            'email' => 'owner@example.com',
            'password' => Hash::make('a-secure-password-99'),
            'email_verified_at' => now(),
        ]);

        $this->withSession(['url.intended' => 'https://evil.example/phish'])
            ->post('/login', ['email' => 'owner@example.com', 'password' => 'a-secure-password-99'])
            ->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
    }

    public function test_intended_redirect_allows_relative_paths(): void
    {
        $user = User::factory()->create([
            'email' => 'owner@example.com',
            'password' => Hash::make('a-secure-password-99'),
            'email_verified_at' => now(),
        ]);

        $this->withSession(['url.intended' => '/billing'])
            ->post('/login', ['email' => 'owner@example.com', 'password' => 'a-secure-password-99'])
            ->assertRedirect('/billing');

        $this->assertAuthenticatedAs($user);
    }

    public function test_registration_requires_letters_and_numbers_in_password(): void
    {
        $this->post('/register', [
            'name' => 'Weak',
            'email' => 'weak@example.com',
            'password' => 'aaaaaaaaaaaa',
            'password_confirmation' => 'aaaaaaaaaaaa',
        ])->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'weak@example.com']);
    }
}
