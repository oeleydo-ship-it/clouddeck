<?php

namespace Tests\Feature;

use App\Enums\ServerStatus;
use App\Jobs\Servers\BootstrapServerJob;
use App\Models\Server;
use App\Models\SshKey;
use App\Models\User;
use App\Ssh\SshClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Tests\TestCase;

/**
 * A provider reports a machine "active" the moment it powers on, a good half-minute
 * before sshd is listening. Bootstrapping straight into that gap is what turns a healthy
 * new server into a provision that failed on "connection refused".
 */
class ServerBootstrapReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_bootstrap_runs_once_the_ssh_port_answers(): void
    {
        // A real listening socket, so the readiness check is exercised rather than mocked.
        $listener = stream_socket_server('tcp://127.0.0.1:0', $code, $message);
        $this->assertNotFalse($listener, "could not open a local listener: {$message}");
        $port = (int) explode(':', stream_socket_get_name($listener, false))[1];

        $server = $this->server('127.0.0.1', $port);
        Process::fake(['*' => Process::result(output: 'bootstrapped', exitCode: 0)]);

        (new BootstrapServerJob($server->id))->handle(app(SshClient::class));

        fclose($listener);
        Process::assertRan(fn ($process) => str_contains(implode(' ', (array) $process->command), 'ssh'));
        $this->assertSame(90, $server->fresh()->progress);
    }

    public function test_a_host_that_never_answers_fails_instead_of_bootstrapping(): void
    {
        // Zero timeout: one attempt, no waiting, so the failure path stays fast.
        config(['clouddeck.ssh_ready_timeout' => 0]);
        Process::fake();

        // Port 1 on loopback: nothing listens there, and it refuses rather than hangs.
        $server = $this->server('127.0.0.1', 1);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('did not accept SSH on port 1');

        try {
            (new BootstrapServerJob($server->id))->handle(app(SshClient::class));
        } finally {
            // The whole point: nothing was run against a host that never answered, and the
            // server never advanced to the bootstrapping step.
            Process::assertNothingRan();
            $this->assertLessThan(40, $server->fresh()->progress);
        }
    }

    public function test_the_wait_uses_the_port_the_server_is_reachable_on(): void
    {
        config(['clouddeck.ssh_ready_timeout' => 0]);
        Process::fake();
        $server = $this->server('127.0.0.1', 2222);

        $this->expectExceptionMessage('did not accept SSH on port 2222');

        (new BootstrapServerJob($server->id))->handle(app(SshClient::class));
    }

    private function server(string $ip, int $port): Server
    {
        $user = User::factory()->create();
        $key = SshKey::create(['user_id' => $user->id, 'name' => 'Managed', 'public_key' => 'ssh-rsa public', 'private_key' => 'private']);

        return Server::create([
            'user_id' => $user->id,
            'ssh_key_id' => $key->id,
            'name' => 'Production',
            'hostname' => 'production',
            'public_ip' => $ip,
            'ssh_port' => $port,
            'region' => 'ams3',
            'size' => 's-1vcpu-1gb',
            'status' => ServerStatus::Active,
        ]);
    }
}
