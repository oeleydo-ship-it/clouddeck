<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Thin OpenAI chat wrapper for the in-console platform guide. The API key lives in
 * system settings (encrypted) so a superadmin can enable guidance without redeploying.
 */
final class PlatformGuide
{
    public function __construct(private SystemSettings $settings) {}

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

        $key = $this->settings->openaiApiKey();
        $payload = [
            'model' => $this->settings->openaiModel(),
            'temperature' => 0.3,
            'messages' => [
                ['role' => 'system', 'content' => $this->settings->aiGuideSystemPrompt()],
                ...array_values($messages),
            ],
        ];

        try {
            $response = Http::withToken((string) $key)
                ->acceptJson()
                ->timeout(45)
                ->post('https://api.openai.com/v1/chat/completions', $payload);
        } catch (ConnectionException $e) {
            throw new RuntimeException('Could not reach OpenAI: '.$e->getMessage(), 0, $e);
        } catch (Throwable $e) {
            throw new RuntimeException('The AI guide failed: '.$e->getMessage(), 0, $e);
        }

        if (! $response->successful()) {
            $detail = $response->json('error.message') ?: $response->body();
            throw new RuntimeException('OpenAI rejected the request: '.$detail);
        }

        $content = trim((string) data_get($response->json(), 'choices.0.message.content', ''));

        if ($content === '') {
            throw new RuntimeException('The AI guide returned an empty reply.');
        }

        return $content;
    }
}
