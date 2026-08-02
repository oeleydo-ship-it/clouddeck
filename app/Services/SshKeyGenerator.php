<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;

final class SshKeyGenerator
{
    public function generate(string $comment): array
    {
        $directory = storage_path('app/private/keygen');
        if (! is_dir($directory)) {
            mkdir($directory, 0700, true);
        }
        $path = $directory.'/'.Str::uuid();

        try {
            $result = Process::timeout(30)->run(['ssh-keygen', '-q', '-t', 'ed25519', '-N', '', '-C', $comment, '-f', $path]);
            if ($result->failed() || ! is_file($path) || ! is_file($path.'.pub')) {
                throw new RuntimeException('Unable to generate an SSH key pair. Ensure ssh-keygen is installed.');
            }
            $public = trim(file_get_contents($path.'.pub'));

            return ['public_key' => $public, 'private_key' => file_get_contents($path), 'fingerprint' => $this->fingerprint($public)];
        } finally {
            foreach ([$path, $path.'.pub'] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }

    public function fingerprint(string $publicKey): string
    {
        $parts = preg_split('/\s+/', trim($publicKey));
        if (count($parts) < 2 || ! in_array($parts[0], ['ssh-rsa', 'ssh-ed25519', 'ecdsa-sha2-nistp256'], true)) {
            throw new RuntimeException('The public key is not a supported OpenSSH key.');
        }

        $blob = base64_decode($parts[1], true);
        if ($blob === false) {
            throw new RuntimeException('The public key payload is invalid.');
        }

        return 'SHA256:'.rtrim(base64_encode(hash('sha256', $blob, true)), '=');
    }
}
