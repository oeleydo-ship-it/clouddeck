<?php

namespace Tests;

use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Marks the instance as already installed, so requests are not diverted to the setup
     * wizard. Needed only by tests that exercise guest pages against a genuinely empty
     * database: anywhere a user fixture exists the instance already counts as installed,
     * which is why the rest of the suite needs no such call.
     */
    protected function markInstalled(): void
    {
        SystemSetting::updateOrCreate(
            ['key' => 'installed_at'],
            ['value' => now()->toIso8601String(), 'type' => 'string', 'is_public' => false],
        );
    }
}
