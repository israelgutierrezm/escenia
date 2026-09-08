<?php

declare(strict_types=1);

namespace App\Domain\Broadcasting\Enums;

enum BroadcastDestinationStatus: string
{
    case Pending = 'pending';
    case Live = 'live';
    case Failed = 'failed';
}
