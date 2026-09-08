<?php

declare(strict_types=1);

namespace App\Http\Concerns;

use App\Domain\Events\Models\Event;

trait ResolvesEvent
{
    /**
     * Resolve an event by its public ULID within the current tenant scope. The
     * tenant global scope guarantees a foreign event 404s rather than leaking.
     */
    protected function resolveEvent(string $ulid): Event
    {
        return Event::query()->where('ulid', $ulid)->firstOrFail();
    }
}
