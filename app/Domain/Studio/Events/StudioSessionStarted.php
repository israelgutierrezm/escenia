<?php

declare(strict_types=1);

namespace App\Domain\Studio\Events;

use App\Domain\Studio\Models\StudioSession;
use Illuminate\Foundation\Events\Dispatchable;

final class StudioSessionStarted
{
    use Dispatchable;

    public function __construct(
        public readonly StudioSession $session,
    ) {}
}
