<?php

namespace App\Services;

use App\Models\NotificationChannel;
use App\Notifications\OutboundMessage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Delivers a notification to the destinations a customer has configured. Every send is
 * best-effort and isolated: a Slack webhook that has been revoked must not stop the Telegram
 * message behind it, and none of them may fail the job that raised the alert.
 */
final class NotificationDispatcher
{
    public function send(NotificationChannel $channel, OutboundMessage $message): bool
    {
        if (! $channel->enabled || ! $channel->wantsEvent($message->event)) {
            return false;
        }

        try {
            return match ($channel->type) {
                'slack' => $this->post($channel, ['text' => $message->plain()]),
                'discord' => $this->post($channel, ['content' => $message->plain()]),
                'telegram' => $this->telegram($channel, $message),
                'sms' => $this->sms($channel, $message),
                'push' => $this->push($channel, $message),
                default => false,
            };
        } catch (Throwable $e) {
            // Recorded rather than raised: the caller is a monitoring or deployment job whose
            // own work has already succeeded by this point.
            Log::warning('Notification delivery failed', ['channel' => $channel->id, 'type' => $channel->type, 'error' => $e->getMessage()]);

            return false;
        }
    }

    private function post(NotificationChannel $channel, array $payload): bool
    {
        $url = $channel->configuration['webhook_url'] ?? null;

        return $url ? Http::timeout(10)->asJson()->post($url, $payload)->successful() : false;
    }

    private function telegram(NotificationChannel $channel, OutboundMessage $message): bool
    {
        $token = $channel->configuration['bot_token'] ?? null;
        $chat = $channel->configuration['chat_id'] ?? null;
        if (! $token || ! $chat) {
            return false;
        }

        return Http::timeout(10)
            ->post("https://api.telegram.org/bot{$token}/sendMessage", ['chat_id' => $chat, 'text' => $message->plain(), 'disable_web_page_preview' => true])
            ->successful();
    }

    /**
     * Twilio's REST API directly rather than through a package: it is one form post, and a
     * dependency for it would have to be kept current for the life of the product.
     */
    private function sms(NotificationChannel $channel, OutboundMessage $message): bool
    {
        $config = $channel->configuration;
        foreach (['account_sid', 'auth_token', 'from', 'to'] as $key) {
            if (empty($config[$key])) {
                return false;
            }
        }

        return Http::timeout(15)
            ->withBasicAuth($config['account_sid'], $config['auth_token'])
            ->asForm()
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$config['account_sid']}/Messages.json", [
                'From' => $config['from'],
                'To' => $config['to'],
                // An SMS is charged by the segment, so this is the one destination that gets
                // the short form rather than the full body.
                'Body' => mb_substr($message->title.': '.$message->body, 0, 300),
            ])
            ->successful();
    }

    /** Pushover, which needs no service worker, no VAPID keys, and no browser permission. */
    private function push(NotificationChannel $channel, OutboundMessage $message): bool
    {
        $config = $channel->configuration;
        if (empty($config['app_token']) || empty($config['user_key'])) {
            return false;
        }

        return Http::timeout(10)->asForm()->post('https://api.pushover.net/1/messages.json', [
            'token' => $config['app_token'],
            'user' => $config['user_key'],
            'title' => $message->title,
            'message' => $message->body,
            'url' => $message->url,
            'priority' => $message->severity === 'critical' ? 1 : 0,
        ])->successful();
    }
}
