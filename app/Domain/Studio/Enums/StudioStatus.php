<?php

declare(strict_types=1);

namespace App\Domain\Studio\Enums;

enum StudioStatus: string
{
    case Idle = 'idle';
    case Live = 'live';
    case Ended = 'ended';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
