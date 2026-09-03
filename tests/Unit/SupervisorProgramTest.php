<?php

namespace Tests\Unit;

use App\Services\SupervisorProgram;
use Tests\TestCase;

class SupervisorProgramTest extends TestCase
{
    public function test_canonical_name_uses_uplary_prefix(): void
    {
        $this->assertSame('uplary-abc', SupervisorProgram::name('abc'));
        $this->assertSame(['uplary-abc', 'clouddeck-abc'], SupervisorProgram::candidates('abc'));
    }

    public function test_parse_status_prefers_uplary_running_line(): void
    {
        $output = "uplary-abc:uplary-abc_00   RUNNING   pid 1, uptime 0:01:00\n"
            ."clouddeck-abc: STOPPED\n";

        $this->assertSame('RUNNING', SupervisorProgram::parseStatus($output, 'abc'));
    }

    public function test_parse_status_falls_back_to_legacy_clouddeck_name(): void
    {
        $output = "clouddeck-abc: ERROR (no such process)\n"
            ."uplary-abc:uplary-abc_00   STOPPED\n";

        $this->assertSame('STOPPED', SupervisorProgram::parseStatus($output, 'abc'));
    }

    public function test_status_command_queries_both_program_names(): void
    {
        $command = SupervisorProgram::statusCommand('worker-1');

        $this->assertStringContainsString('supervisorctl status '.escapeshellarg('uplary-worker-1:*'), $command);
        $this->assertStringContainsString('supervisorctl status '.escapeshellarg('clouddeck-worker-1:*'), $command);
    }
}
