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

    public function runStreaming(Server $server, string $command, callable $output, ?int $timeoutSeconds = null): ProcessResult
    {
        return $this->execute($server, $command, $output, $timeoutSeconds);
    }

    public function runScript(Server $server, string $path, array $env = [], ?int $timeoutSeconds = null): string
    {
        $result = $this->executeScript($server, $this->script($path, $env), timeoutSeconds: $timeoutSeconds);
        if ($result->failed()) {
            throw new RuntimeException($result->errorOutput());
        }

        return $result->output();
    }

    public function runScriptStreaming(Server $server, string $path, array $env, callable $output, ?int $timeoutSeconds = null): ProcessResult
    {
        return $this->executeScript($server, $this->script($path, $env), $output, $timeoutSeconds);
    }

    /**
     * Stream local contents to a remote path over SSH (binary-safe; used for site restore archives).
     *
     * @param  resource|string  $contents
     */
    public function putContents(Server $server, string $remotePath, mixed $contents, ?int $timeoutSeconds = null): void
    {
        if (str_contains($remotePath, "\0") || ! str_starts_with($remotePath, '/tmp/')) {
            throw new RuntimeException('Remote path must be under /tmp.');
        }

        $key = $this->keyFile($server);
        try {
            $result = Process::timeout($timeoutSeconds ?? 1800)
                ->input($contents)
                ->run([...$this->sshCommand($server, $key), 'bash', '-c', 'cat > '.escapeshellarg($remotePath)]);
            if ($result->failed()) {
                throw new RuntimeException($result->errorOutput() ?: 'Failed to upload file over SSH.');
            }
        } finally {
            if (is_file($key)) {
                unlink($key);
            }
        }
    }

    private function execute(Server $server, string $command, ?callable $output = null, ?int $timeoutSeconds = null): ProcessResult
    {
        $this->guard($command);
        $key = $this->keyFile($server);
        try {
            return Process::timeout($timeoutSeconds ?? 1800)->run([...$this->sshCommand($server, $key), $command], $output);
        } finally {
            if (is_file($key)) {
                unlink($key);
            }
        }
    }

    private function executeScript(Server $server, string $script, ?callable $output = null, ?int $timeoutSeconds = null): ProcessResult
    {
        $key = $this->keyFile($server);
        try {
            return Process::timeout($timeoutSeconds ?? 1800)->input($script)->run([...$this->sshCommand($server, $key), 'bash', '-s'], $output);
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
        // Keep configure-site compatible with workers that were started before the
        // multisite option existed. A normal site is never a catch-all unless its caller
        // explicitly opts in with 1.
        if (basename($path) === 'configure-site.sh') {
            $env += ['MULTISITE_ORIGIN' => '0'];
        }

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

        $identity = $this->windowsIdentity();
        if ($identity === '') {
            throw new RuntimeException('Unable to determine the Windows account used by the SSH worker.');
        }

        // Modify, not read-only: OpenSSH only requires that no other account has access, and a
        // read-only grant leaves the key file undeletable, stranding private keys on disk.
        $acl = Process::run([$this->windowsSystemExecutable('icacls.exe'), $path, '/inheritance:r', '/grant:r', "{$identity}:(M)"]);
        if ($acl->failed()) {
            throw new RuntimeException('Unable to secure the temporary SSH private key: '.$acl->errorOutput());
        }
    }

    private function windowsIdentity(): string
    {
        // Queue workers started by service managers can inherit a minimal PATH. Try the
        // ordinary command first for compatibility, then its absolute Windows location.
        foreach ([['whoami'], [$this->windowsSystemExecutable('whoami.exe')]] as $command) {
            $result = Process::run($command);
            $identity = trim($result->output());
            if ($result->successful() && $identity !== '') {
                return $identity;
            }
        }

        // USERDOMAIN and USERNAME are populated for normal interactive and service
        // accounts even when a process cannot resolve or execute whoami.exe.
        $username = trim((string) getenv('USERNAME'));
        $domain = trim((string) getenv('USERDOMAIN'));

        return $username === '' ? '' : ($domain === '' ? $username : "{$domain}\\{$username}");
    }

    private function windowsSystemExecutable(string $name): string
    {
        $windows = rtrim((string) (getenv('SystemRoot') ?: getenv('WINDIR') ?: 'C:\\Windows'), '\\/');

        return $windows.'\\System32\\'.$name;
    }
}
