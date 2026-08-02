<?php

namespace App\Notifications;

use App\Models\AlertIncident;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AlertTriggeredNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly AlertIncident $incident)
    {
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        $channels = ['database'];
        if ($notifiable->notificationChannels()->where('type', 'email')->where('enabled', true)->exists()) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(strtoupper($this->incident->severity).' server alert: '.$this->incident->server->name)
            ->line($this->incident->message)
            ->line('Current value: '.$this->incident->value.'; threshold: '.$this->incident->threshold)
            ->action('Open server monitoring', route('servers.manage', $this->incident->server));
    }

    public function toArray(object $notifiable): array
    {
        return ['incident_id' => $this->incident->id, 'server_id' => $this->incident->server_id, 'severity' => $this->incident->severity, 'metric' => $this->incident->metric, 'value' => $this->incident->value, 'message' => $this->incident->message];
    }
}
