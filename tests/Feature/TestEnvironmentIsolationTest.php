<?php

namespace Tests\Feature;

use Tests\TestCase;

class TestEnvironmentIsolationTest extends TestCase
{
    public function test_automated_tests_use_an_in_memory_database(): void
    {
        $this->assertSame('testing', app()->environment());
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
    }
}
