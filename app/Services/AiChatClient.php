<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * OpenAI-compatible chat completions for Admin → AI providers (OpenAI, Moonshot/Kimi).
 */
final class AiChatClient
{
    public function __construct(private SystemSettings $settings) {}

    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     */
    public function chat(array $messages, ?float $temperature = null, int $timeout = 45): string
    {
        $key = $this->settings->aiApiKey();
        if (blank($key)) {
            throw new RuntimeException('No AI API key is configured. Save one under Admin → AI.');
        }

        $provider = $this->settings->aiProvider();
        $model = $this->settings->aiModel();
        $payload = [
            'model' => $model,
            'messages' => array_values($messages),
        ];

        // Moonshot/Kimi models reject non-default temperature (fixed per model). OpenAI accepts it.
        if ($provider === SystemSettings::AI_PROVIDER_OPENAI && $temperature !== null) {
            $payload['temperature'] = $temperature;
        }

        // Kimi K3 always reasons; default effort is "max" which is slow/expensive for guide & drafts.
        if ($provider === SystemSettings::AI_PROVIDER_MOONSHOT && str_starts_with($model, 'kimi-k3')) {
            $payload['reasoning_effort'] = 'low';
        }

        $url = $this->settings->aiChatCompletionsUrl();

        try {
            $response = Http::withToken((string) $key)
                ->acceptJson()
                ->timeout($timeout)
                ->post($url, $payload);
        } catch (ConnectionException $e) {
            throw new RuntimeException('Could not reach the AI provider: '.$e->getMessage(), 0, $e);
        } catch (Throwable $e) {
            throw new RuntimeException('AI request failed: '.$e->getMessage(), 0, $e);
        }

        if (! $response->successful()) {
            $detail = $response->json('error.message') ?: $response->body();
            throw new RuntimeException('AI provider rejected the request: '.$detail);
        }

        $content = trim((string) data_get($response->json(), 'choices.0.message.content', ''));

        if ($content === '') {
            throw new RuntimeException('The AI returned an empty reply.');
        }

        return $content;
    }
}
