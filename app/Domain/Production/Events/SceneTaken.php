<?php

declare(strict_types=1);

namespace App\Domain\Production\Events;

use App\Domain\Production\Models\Scene;
use App\Domain\Studio\Models\Studio;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired when a scene is taken to program (goes on-air). Application realtime
 * (Reverb) will broadcast the program switch to viewers/producers later.
 */
final class SceneTaken
{
    use Dispatchable;

    public function __construct(
        public readonly Studio $studio,
        public readonly Scene $scene,
    ) {}
}
