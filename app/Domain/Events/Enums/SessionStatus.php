<?php

declare(strict_types=1);

namespace App\Domain\Events\Enums;

enum SessionStatus: string
{
    case Scheduled = 'scheduled';
    case Live = 'live';
    case Ended = 'ended';
    case Canceled = 'canceled';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
