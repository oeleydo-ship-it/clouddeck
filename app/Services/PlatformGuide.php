<?php

namespace App\Services;

use RuntimeException;

/**
 * Thin chat wrapper for the in-console platform guide. The API key lives in system settings
 * (encrypted) so a superadmin can enable guidance without redeploying.
 */
final class PlatformGuide
{
    public function __construct(
        private SystemSettings $settings,
        private AiChatClient $client,
    ) {}

    public function enabled(): bool
    {
        return $this->settings->aiGuideEnabled();
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     */
    public function reply(array $messages): string
    {
        if (! $this->enabled()) {
            throw new RuntimeException('The AI guide is not enabled.');
        }

        return $this->client->chat(
            messages: [
                ['role' => 'system', 'content' => $this->settings->aiGuideSystemPrompt()],
                ...array_values($messages),
            ],
            temperature: 0.3,
            timeout: 45,
        );
    }
}
