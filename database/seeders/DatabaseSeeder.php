<?php

namespace Database\Seeders;

use App\Models\FeatureFlag;
use App\Models\Plan;
use App\Models\SystemSetting;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $free = Plan::updateOrCreate(['slug' => 'free'], ['name' => 'Free', 'monthly_price' => 0, 'yearly_price' => 0, 'currency' => 'USD', 'limits' => ['servers' => 1, 'sites' => 3, 'databases' => 3, 'api_tokens' => 2, 'teams' => 1, 'team_members' => 3], 'features' => ['monitoring' => true, 'remote_management' => false, 'teams' => true], 'active' => true, 'public' => true, 'sort_order' => 10]);
        Plan::updateOrCreate(['slug' => 'pro'], ['name' => 'Pro', 'monthly_price' => 2900, 'yearly_price' => 29000, 'currency' => 'USD', 'limits' => ['servers' => 10, 'sites' => 50, 'databases' => 50, 'api_tokens' => 10, 'teams' => 3, 'team_members' => 20], 'features' => ['monitoring' => true, 'remote_management' => true, 'teams' => true], 'active' => true, 'public' => true, 'sort_order' => 20]);
        Plan::updateOrCreate(['slug' => 'business'], ['name' => 'Business', 'monthly_price' => 9900, 'yearly_price' => 99000, 'currency' => 'USD', 'limits' => ['servers' => -1, 'sites' => -1, 'databases' => -1, 'api_tokens' => -1, 'teams' => -1, 'team_members' => -1], 'features' => ['monitoring' => true, 'remote_management' => true, 'teams' => true], 'active' => true, 'public' => true, 'sort_order' => 30]);
        foreach (['monitoring' => 'Monitoring and alerts', 'remote_management' => 'Remote management', 'teams' => 'Team collaboration'] as $key => $name) {
            FeatureFlag::updateOrCreate(['key' => $key], ['name' => $name, 'enabled' => true, 'rollout_percentage' => 100]);
        }

        $user = User::firstOrCreate(['email' => 'test@example.com'], [
            'name' => 'Test User',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);
        $user->subscriptions()->firstOrCreate(
            ['plan_id' => $free->id, 'provider' => 'system'],
            ['status' => 'active'],
        );

        if (app()->environment('local')) {
            SystemSetting::updateOrCreate(['key' => 'email_verification_required'], ['value' => '0', 'type' => 'boolean', 'is_public' => true]);
            $admin = User::updateOrCreate(['email' => config('clouddeck.development_admin.email')], [
                'name' => 'CloudDeck Administrator',
                'password' => Hash::make(config('clouddeck.development_admin.password')),
                'role' => 'super_admin',
                'email_verified_at' => now(),
            ]);
            $admin->subscriptions()->firstOrCreate(['plan_id' => $free->id, 'provider' => 'system'], ['status' => 'active']);
        }
    }
}
