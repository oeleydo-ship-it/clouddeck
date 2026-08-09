<?php

namespace App\Notifications\Concerns;

use App\Services\SystemSettings;

/**
 * Superadmin Notification Center can mute client alert emails per event so SMTP quota is
 * not burned by high-volume operational mail. Database (bell) delivery stays on.
 */
trait RespectsClientEmailPolicy
{
    /** @return list<string> */
    protected function channelsWithOptionalMail(object $notifiable, array $recipients, ?string $event = null): array
    {
        $event ??= method_exists($this, 'notificationEvent') ? $this->notificationEvent() : null;
        $mailAllowed = $event !== null
            && $recipients !== []
            && app(SystemSettings::class)->clientEmailEventAllowed($event);

        return $mailAllowed ? ['database', 'mail'] : ['database'];
    }
}
