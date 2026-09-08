<?php

declare(strict_types=1);

namespace App\Domain\Studio\Enums;

enum StudioSessionStatus: string
{
    case Live = 'live';
    case Ended = 'ended';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
