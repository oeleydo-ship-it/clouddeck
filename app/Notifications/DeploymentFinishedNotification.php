<?php

namespace App\Notifications;

use App\Models\Deployment;
use App\Notifications\Concerns\RespectsClientEmailPolicy;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DeploymentFinishedNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use RespectsClientEmailPolicy;

    public function __construct(public readonly Deployment $deployment)
    {
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        return $this->channelsWithOptionalMail($notifiable, $this->recipients($notifiable));
    }

    public function notificationEvent(): string
    {
        return 'deploy_complete';
    }

    /** @return array<int, string> */
    private function recipients(object $notifiable): array
    {
        return method_exists($notifiable, 'emailRecipientsFor') ? $notifiable->emailRecipientsFor('deploy_complete') : [];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $successful = in_array($this->deployment->status->value, ['successful', 'rolled_back'], true);

        return (new MailMessage)->subject('Deployment '.$this->deployment->status->value.': '.$this->deployment->site->domain)->line("The {$this->deployment->trigger} deployment for {$this->deployment->site->domain} {$this->deployment->status->value}.")->line('Release: '.($this->deployment->release ?? 'not created'))->action('View deployment', route('deployments.show', $this->deployment))->line($successful ? 'The release is now live.' : 'Review the command log to identify the failure.');
    }

    public function toArray(object $notifiable): array
    {
        return ['deployment_id' => $this->deployment->id, 'site_id' => $this->deployment->site_id, 'domain' => $this->deployment->site->domain, 'status' => $this->deployment->status->value, 'release' => $this->deployment->release];
    }
}
