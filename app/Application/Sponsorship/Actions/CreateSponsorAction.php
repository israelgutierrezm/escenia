<?php

declare(strict_types=1);

namespace App\Application\Sponsorship\Actions;

use App\Domain\Audit\Contracts\AuditLogger;
use App\Domain\Events\Models\Event;
use App\Domain\Identity\Models\User;
use App\Domain\Sponsorship\Enums\SponsorTier;
use App\Domain\Sponsorship\Models\Sponsor;

/**
 * Adds a sponsor to an event.
 */
final class CreateSponsorAction
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    public function execute(Event $event, User $actor, string $name, SponsorTier $tier, ?string $logoUrl, ?string $websiteUrl): Sponsor
    {
        $position = (int) Sponsor::query()->where('event_id', $event->getKey())->max('position');

        $sponsor = Sponsor::query()->create([
            'event_id' => $event->getKey(),
            'name' => $name,
            'tier' => $tier,
            'logo_url' => $logoUrl,
            'website_url' => $websiteUrl,
            'position' => $position + 1,
        ]);

        $this->audit->log('sponsorship.sponsor.created', actor: $actor, tenant: $event->tenant, auditable: $sponsor);

        return $sponsor;
    }
}
