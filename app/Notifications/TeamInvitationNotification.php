<?php

namespace App\Notifications;

use App\Models\TeamInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TeamInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly TeamInvitation $invitation, public readonly string $token)
    {
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Invitation to '.$this->invitation->team->name)->line('You have been invited as '.$this->invitation->role.'.')->action('Accept invitation', route('team-invitations.accept', [$this->invitation, $this->token]))->line('This invitation expires in seven days.');
    }
}
