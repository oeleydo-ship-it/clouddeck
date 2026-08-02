<?php

namespace App\Jobs\Monitoring;

use App\Models\AlertIncident;
use App\Models\NotificationChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class DeliverAlertChannelsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly string $incidentId, public readonly string $channelId)
    {
        $this->onQueue('notifications');
    }

    public function handle(): void
    {
        $incident = AlertIncident::with('server')->findOrFail($this->incidentId);
        $message = '['.strtoupper($incident->severity).'] '.$incident->server->name.': '.$incident->message.' ('.$incident->value.')';
        $channel = NotificationChannel::where('user_id', $incident->user_id)->where('enabled', true)->whereIn('type', ['slack', 'discord', 'telegram'])->find($this->channelId);
        if (! $channel) {
            return;
        }

        $config = $channel->configuration;
        match ($channel->type) {
            'slack' => Http::timeout(10)->withoutRedirecting()->post($config['webhook_url'], ['text' => $message])->throw(),
            'discord' => Http::timeout(10)->withoutRedirecting()->post($config['webhook_url'], ['content' => $message])->throw(),
            'telegram' => Http::timeout(10)->withoutRedirecting()->post('https://api.telegram.org/bot'.$config['bot_token'].'/sendMessage', ['chat_id' => $config['chat_id'], 'text' => $message])->throw(),
        };
    }
}
