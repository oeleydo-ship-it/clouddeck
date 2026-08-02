<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

final class SafeTerminalCommand
{
    private const ALLOWED = ['php', 'composer', 'git', 'npm', 'node', 'ls', 'pwd', 'cat', 'tail'];

    public function compile(string $command): string
    {
        $command = trim($command);
        if ($command === '' || strlen($command) > 1000 || preg_match('/[^A-Za-z0-9_@%+=:,\.\/\-\s]/', $command)) {
            throw ValidationException::withMessages(['command' => 'The command contains unsupported shell syntax.']);
        }

        $arguments = preg_split('/\s+/', $command) ?: [];
        if (! in_array($arguments[0] ?? '', self::ALLOWED, true) || collect($arguments)->contains(fn (string $argument) => $argument === '..' || str_starts_with($argument, '../') || str_contains($argument, '/../') || str_starts_with($argument, '/'))) {
            throw ValidationException::withMessages(['command' => 'Use an allowed command and paths relative to the site root.']);
        }

        return implode(' ', array_map('escapeshellarg', $arguments));
    }
}
