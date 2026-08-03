<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The events that have no object of their own to hang off — a certificate approaching expiry,
 * a queue that has started failing. Both reduce to the same thing: a title, a sentence, and
 * somewhere to go and look.
 */
class OperationalEventNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $event,
        public readonly string $title,
        public readonly string $body,
        public readonly ?string $url = null,
        public readonly string $severity = 'warning',
        public readonly array $context = [],
    ) {
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        $channels = ['database', 'clouddeck'];

        if ($notifiable->notificationChannels()->where('type', 'email')->where('enabled', true)->exists()) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject($this->title)->line($this->body);

        return $this->url ? $mail->action('Open CloudDeck', $this->url) : $mail;
    }

    public function toOutbound(object $notifiable): OutboundMessage
    {
        return new OutboundMessage($this->event, $this->title, $this->body, $this->url, $this->severity);
    }

    public function toArray(object $notifiable): array
    {
        return ['event' => $this->event, 'title' => $this->title, 'body' => $this->body, ...$this->context];
    }
}
