<?php

namespace Tests\Unit;

use App\Services\SupervisorProgram;
use PHPUnit\Framework\TestCase;

class SupervisorProgramTest extends TestCase
{
    public function test_canonical_name_uses_clouddeck_prefix(): void
    {
        $this->assertSame('clouddeck-abc', SupervisorProgram::name('abc'));
        $this->assertSame(['clouddeck-abc', 'Uplary-abc'], SupervisorProgram::candidates('abc'));
    }

    public function test_parse_status_prefers_clouddeck_running_line(): void
    {
        $output = "clouddeck-abc:clouddeck-abc_00   RUNNING   pid 1, uptime 0:01:00\n"
            ."Uplary-abc: ERROR (no such process)";

        $this->assertSame('RUNNING', SupervisorProgram::parseStatus($output, 'abc'));
    }

    public function test_parse_status_falls_back_to_legacy_uplary_name(): void
    {
        $output = "clouddeck-abc: ERROR (no such process)\n"
            ."Uplary-abc:Uplary-abc_00   BACKOFF   Exited too quickly";

        $this->assertSame('BACKOFF', SupervisorProgram::parseStatus($output, 'abc'));
    }

    public function test_status_command_queries_both_program_names(): void
    {
        $command = SupervisorProgram::statusCommand('worker-1');

        $this->assertStringContainsString('supervisorctl status '.escapeshellarg('clouddeck-worker-1:*'), $command);
        $this->assertStringContainsString('supervisorctl status '.escapeshellarg('Uplary-worker-1:*'), $command);
    }
}
