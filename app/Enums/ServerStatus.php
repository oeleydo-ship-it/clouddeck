<?php

namespace App\Enums;

enum ServerStatus: string
{
    case AwaitingPayment = 'awaiting_payment';
    case Pending = 'pending';
    case Creating = 'creating';
    case Active = 'active';
    case Provisioning = 'provisioning';
    case Ready = 'ready';
    case Failed = 'failed';
    case Deleting = 'deleting';

    /**
     * Fully bootstrapped and safe to operate (monitoring, databases, backups, etc.).
     * Intermediate states such as Active (IP assigned, bootstrap not finished) are not ready.
     */
    public function isReady(): bool
    {
        return $this === self::Ready;
    }
}
