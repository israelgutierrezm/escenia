<?php

declare(strict_types=1);

namespace App\Application\Sponsorship\Actions;

use App\Domain\Audit\Contracts\AuditLogger;
use App\Domain\Events\Models\Event;
use App\Domain\Identity\Models\User;
use App\Domain\Sponsorship\Models\Booth;
use App\Domain\Sponsorship\Models\Sponsor;

/**
 * Adds an expo booth for a sponsor.
 */
final class CreateBoothAction
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    public function execute(Event $event, User $actor, Sponsor $sponsor, string $name, ?string $description, ?string $url): Booth
    {
        $position = (int) Booth::query()->where('event_id', $event->getKey())->max('position');

        $booth = Booth::query()->create([
            'event_id' => $event->getKey(),
            'sponsor_id' => $sponsor->getKey(),
            'name' => $name,
            'description' => $description,
            'url' => $url,
            'leads_count' => 0,
            'position' => $position + 1,
        ]);

        $this->audit->log('sponsorship.booth.created', actor: $actor, tenant: $event->tenant, auditable: $booth);

        return $booth;
    }
}
