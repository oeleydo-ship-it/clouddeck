<?php

namespace App\Enums;

enum ServerStatus: string
{
    case Pending = 'pending';
    case Creating = 'creating';
    case Active = 'active';
    case Provisioning = 'provisioning';
    case Ready = 'ready';
    case Failed = 'failed';
    case Deleting = 'deleting';
}
