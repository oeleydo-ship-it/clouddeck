<?php

namespace App\Ssh;

use App\Models\Server;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\Process;
use RuntimeException;

final class SshClient
{
    public function run(Server $server, string $command): string
    {
        $result = $this->execute($server, $command);
        if ($result->failed()) {
            throw new RuntimeException($result->errorOutput());
        }

        return $result->output();
    }

    public function runStreaming(Server $server, string $command, callable $output): ProcessResult
    {
        return $this->execute($server, $command, $output);
    }

    public function runScript(Server $server, string $path, array $env = []): string
    {
        $result = $this->executeScript($server, $this->script($path, $env));
        if ($result->failed()) {
            throw new RuntimeException($result->errorOutput());
        }

        return $result->output();
    }

    public function runScriptStreaming(Server $server, string $path, array $env, callable $output): ProcessResult
    {
        return $this->executeScript($server, $this->script($path, $env), $output);
    }

    private function execute(Server $server, string $command, ?callable $output = null): ProcessResult
    {
        $this->guard($command);
        $key = $this->keyFile($server);
        try {
            return Process::timeout(1800)->run([...$this->sshCommand($server, $key), $command], $output);
        } finally {
            if (is_file($key)) {
                unlink($key);
            }
        }
    }

    private function executeScript(Server $server, string $script, ?callable $output = null): ProcessResult
    {
        $key = $this->keyFile($server);
        try {
            return Process::timeout(1800)->input($script)->run([...$this->sshCommand($server, $key), 'bash', '-s'], $output);
        } finally {
            if (is_file($key)) {
                unlink($key);
            }
        }
    }

    /**
     * A custom server is one the operator already runs, and may answer SSH somewhere other
     * than 22, so the port travels with the server rather than being assumed.
     *
     * @return array<int, string>
     */
    private function sshCommand(Server $server, string $key): array
    {
        return [
            'ssh',
            '-o', 'BatchMode=yes',
            '-o', 'StrictHostKeyChecking=accept-new',
            '-p', (string) ($server->ssh_port ?: 22),
            '-i', $key,
            "root@{$server->public_ip}",
        ];
    }

    private function script(string $path, array $env): string
    {
        $script = file_get_contents($path);
        foreach ($env as $key => $value) {
            $script = str_replace("{{{$key}}}", escapeshellarg((string) $value), $script);
        }

        // A placeholder nobody supplied used to travel to the server as literal text and
        // be written into whatever it was building. That produced an Nginx config reading
        // "root /var/www/site/{{DOCUMENT_ROOT}};" and an error about a missing semicolon,
        // which says nothing about the actual mistake. Name it here instead.
        if (preg_match_all('/\{\{([A-Z_]+)\}\}/', $script, $matches)) {
            throw new RuntimeException(
                'Refusing to run '.basename($path).': no value was given for '.implode(', ', array_unique($matches[1])).'.'
            );
        }

        return $script;
    }

    private function guard(string $command): void
    {
        if (str_contains($command, "\0")) {
            throw new RuntimeException('Invalid command.');
        }
    }

    private function keyFile(Server $server): string
    {
        if (! $server->sshKey?->private_key) {
            throw new RuntimeException('A managed private key is required for provisioning.');
        }

        // Unique per invocation: the file is locked down to a single reader, so a shared
        // per-server path would make concurrent jobs collide and unlink each other's key.
        $directory = storage_path('app/private/ssh');
        if (! is_dir($directory)) {
            mkdir($directory, 0700, true);
        }
        $path = $directory.'/'.$server->id.'-'.bin2hex(random_bytes(8));
        file_put_contents($path, $server->sshKey->private_key);
        $this->secureKeyFile($path);

        return $path;
    }

    private function secureKeyFile(string $path): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            chmod($path, 0600);

            return;
        }

        $identity = trim(Process::run(['whoami'])->output());
        if ($identity === '') {
            throw new RuntimeException('Unable to determine the Windows account used by the SSH worker.');
        }

        // Modify, not read-only: OpenSSH only requires that no other account has access, and a
        // read-only grant leaves the key file undeletable, stranding private keys on disk.
        $acl = Process::run(['icacls', $path, '/inheritance:r', '/grant:r', "{$identity}:(M)"]);
        if ($acl->failed()) {
            throw new RuntimeException('Unable to secure the temporary SSH private key: '.$acl->errorOutput());
        }
    }
}
