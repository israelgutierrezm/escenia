<?php

declare(strict_types=1);

namespace App\Domain\Broadcasting\Events;

use App\Domain\Broadcasting\Enums\BroadcastHealth;
use App\Domain\Broadcasting\Models\BroadcastSession;
use Illuminate\Foundation\Events\Dispatchable;

final class BroadcastHealthChanged
{
    use Dispatchable;

    public function __construct(
        public readonly BroadcastSession $broadcast,
        public readonly BroadcastHealth $from,
        public readonly BroadcastHealth $to,
    ) {}
}
