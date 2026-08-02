<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

final class EnvironmentFile
{
    public function parse(string $contents): array
    {
        $variables = [];
        foreach (preg_split('/\r\n|\r|\n/', $contents) as $lineNumber => $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (! str_contains($line, '=')) {
                throw ValidationException::withMessages(['environment' => 'Invalid environment line '.($lineNumber + 1).'.']);
            }
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            if (! preg_match('/^[A-Z_][A-Z0-9_]*$/', $key)) {
                throw ValidationException::withMessages(['environment' => "Invalid environment key [{$key}]."]);
            }
            $value = trim($value);
            if ((str_starts_with($value, '"') && str_ends_with($value, '"')) || (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                $value = substr($value, 1, -1);
            }
            $variables[$key] = str_replace(['\\n', '\\"', '\\\\'], ["\n", '"', '\\'], $value);
        }

        return $variables;
    }

    public function render(iterable $variables): string
    {
        return collect($variables)->sortBy('key')->map(fn ($variable) => $variable->key.'="'.str_replace(['\\', '"', "\n"], ['\\\\', '\\"', '\\n'], $variable->value).'"')->implode("\n");
    }
}
