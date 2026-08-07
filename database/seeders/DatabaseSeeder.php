<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use App\Models\User;
use App\Services\PlatformDefaults;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(PlatformDefaults $defaults): void
    {
        // User::factory(10)->create();

        // Plans and feature flags are shared with the installer, so a seeded instance and an
        // installed one start from exactly the same catalogue.
        $defaults->ensure();
        $free = $defaults->freePlan();

        // Fixture accounts with published passwords, so never outside local development:
        // `db:seed` is reachable on a production box and used to create this one silently.
        if (app()->environment('local')) {
            $user = User::firstOrCreate(['email' => 'test@example.com'], [
                'name' => 'Test User',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]);
            $user->subscriptions()->firstOrCreate(
                ['plan_id' => $free->id, 'provider' => 'system'],
                ['status' => 'active'],
            );

            SystemSetting::updateOrCreate(['key' => 'email_verification_required'], ['value' => '0', 'type' => 'boolean', 'is_public' => true]);
            $admin = User::updateOrCreate(['email' => config('clouddeck.development_admin.email')], [
                'name' => 'CloudDeck Administrator',
                'password' => Hash::make(config('clouddeck.development_admin.password')),
                'email_verified_at' => now(),
            ]);
            $admin->forceFill(['role' => 'super_admin'])->save();
            $admin->subscriptions()->firstOrCreate(['plan_id' => $free->id, 'provider' => 'system'], ['status' => 'active']);
        }
    }
}
