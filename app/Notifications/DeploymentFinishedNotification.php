<?php

namespace App\Notifications;

use App\Models\Deployment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DeploymentFinishedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Deployment $deployment)
    {
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail', 'clouddeck'];
    }

    public function toOutbound(object $notifiable): OutboundMessage
    {
        $status = $this->deployment->status->value;

        return new OutboundMessage(
            event: 'deploy_complete',
            title: 'Deployment '.$status.': '.$this->deployment->site->domain,
            body: 'The '.$this->deployment->trigger.' deployment '.$status.'. Release: '.($this->deployment->release ?? 'not created').'.',
            url: route('deployments.show', $this->deployment),
            severity: in_array($status, ['successful', 'rolled_back'], true) ? 'info' : 'critical',
        );
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
