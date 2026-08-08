<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Accepts common clone URLs for GitHub, GitLab, Bitbucket, and self-hosted Git:
 * HTTPS (https://…), SCP-style SSH (git@host:path), and ssh:// URLs.
 */
class GitRepositoryUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        if ($this->isValid($value)) {
            return;
        }

        $fail('The :attribute must be an HTTPS or SSH Git repository URL (GitHub, GitLab, Bitbucket, or self-hosted).');
    }

    public static function isValid(string $value): bool
    {
        if (strlen($value) > 2048 || preg_match('/\s/', $value)) {
            return false;
        }

        // HTTPS clone URLs (any host, including nested GitLab group paths).
        if (preg_match('/^https:\/\/[^\s\/]+(?:\/[^\s]+)+\/?$/', $value) === 1) {
            return true;
        }

        // SCP-style SSH: git@host:owner/repo.git
        if (preg_match('/^git@[^\s:]+:[^\s]+$/', $value) === 1) {
            return true;
        }

        // URI-style SSH used by Bitbucket/GitLab clone UIs: ssh://git@host[:port]/path
        if (preg_match('/^ssh:\/\/git@[^\s\/]+(?::\d+)?\/[^\s]+$/', $value) === 1) {
            return true;
        }

        return false;
    }
}
