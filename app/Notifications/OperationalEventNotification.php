<?php

namespace App\Notifications;

use App\Services\SystemSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Every operational event the platform raises that has no notification of its own: a server
 * finishing provisioning, a site being added, a certificate issued or approaching expiry, a
 * queue that has started failing. All of them reduce to a title, a sentence, and somewhere to
 * go and look, so one notification carries them rather than nine near-identical classes.
 */
class OperationalEventNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $event,
        public readonly string $title,
        public readonly string $body,
        public readonly ?string $url = null,
        public readonly string $severity = 'info',
        public readonly array $context = [],
    ) {
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        // Recorded in the bell regardless of whether anyone asked to be emailed about it: the
        // record of what happened is not the same thing as being told about it.
        return $this->recipients($notifiable) === [] ? ['database'] : ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $platform = app(SystemSettings::class)->branding()['name'];
        $mail = (new MailMessage)
            ->subject($this->title)
            ->line($this->body);

        return $this->url ? $mail->action('Open '.$platform, $this->url) : $mail;
    }

    public function toArray(object $notifiable): array
    {
        return ['event' => $this->event, 'title' => $this->title, 'body' => $this->body, 'severity' => $this->severity, ...$this->context];
    }

    /** Which subscription this belongs to. The notifiable routes the mail from it. */
    public function notificationEvent(): string
    {
        return $this->event;
    }

    /** @return array<int, string> */
    private function recipients(object $notifiable): array
    {
        return method_exists($notifiable, 'emailRecipientsFor') ? $notifiable->emailRecipientsFor($this->event) : [];
    }
}
