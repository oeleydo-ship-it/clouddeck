<?php

namespace App\Notifications;

use App\Services\SystemSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Config;

/**
 * Replaces the framework's generic reset mail so the message carries the platform's own
 * name — an operator resetting a password on their own install should not be told to
 * trust an email from "Laravel".
 */
class ResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly string $token) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $platform = app(SystemSettings::class)->branding()['name'];
        $minutes = Config::get('auth.passwords.users.expire', 60);

        return (new MailMessage)
            ->subject('Reset your '.$platform.' password')
            ->greeting('Password reset')
            ->line('A password reset was requested for this address on '.$platform.'.')
            ->action('Choose a new password', route('password.reset', [
                'token' => $this->token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ]))
            ->line('The link stops working in '.$minutes.' minutes.')
            // Nothing has changed yet at this point, so there is no action to undo and
            // nothing the recipient needs to do if they did not ask for this.
            ->line('If you did not request this, no action is needed and your password stays as it is.');
    }
}
