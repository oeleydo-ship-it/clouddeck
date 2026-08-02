<?php

namespace App\Enums;

enum DeploymentStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Successful = 'successful';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case RolledBack = 'rolled_back';
}
