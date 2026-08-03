<?php

namespace App\Notifications\Channels;

use App\Services\NotificationDispatcher;
use Illuminate\Notifications\Notification;

/**
 * Fans a notification out to every destination the customer has configured. Registering this
 * as one Laravel channel keeps the per-destination detail in one place rather than in every
 * notification's via().
 */
class OutboundChannel
{
    public function __construct(private readonly NotificationDispatcher $dispatcher) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toOutbound') || ! method_exists($notifiable, 'notificationChannels')) {
            return;
        }

        $message = $notification->toOutbound($notifiable);

        // Email is Laravel's own mail channel; everything else is delivered here.
        $notifiable->notificationChannels()
            ->where('enabled', true)
            ->where('type', '!=', 'email')
            ->get()
            ->each(fn ($channel) => $this->dispatcher->send($channel, $message));
    }
}
