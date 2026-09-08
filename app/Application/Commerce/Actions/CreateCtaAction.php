<?php

declare(strict_types=1);

namespace App\Application\Commerce\Actions;

use App\Application\Commerce\DTOs\CreateCtaData;
use App\Domain\Audit\Contracts\AuditLogger;
use App\Domain\Commerce\Models\Cta;
use App\Domain\Commerce\Models\Ticket;
use App\Domain\Events\Models\Event;
use App\Domain\Identity\Models\User;

/**
 * Creates an in-event call-to-action, optionally linked to a ticket (a buy CTA
 * / offer). New CTAs go to the end of the list.
 */
final class CreateCtaAction
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    public function execute(Event $event, User $actor, CreateCtaData $data): Cta
    {
        $ticketId = null;

        if ($data->ticket !== null) {
            $ticket = Ticket::query()->where('ulid', $data->ticket)->where('event_id', $event->getKey())->first();
            $ticketId = $ticket?->getKey();
        }

        $position = (int) Cta::query()->where('event_id', $event->getKey())->max('position');

        $cta = Cta::query()->create([
            'event_id' => $event->getKey(),
            'ticket_id' => $ticketId,
            'title' => $data->title,
            'body' => $data->body,
            'url' => $data->url,
            'is_active' => true,
            'starts_at' => $data->startsAt,
            'ends_at' => $data->endsAt,
            'clicks_count' => 0,
            'position' => $position + 1,
            'created_by' => $actor->getKey(),
        ]);

        $this->audit->log('commerce.cta.created', actor: $actor, tenant: $event->tenant, auditable: $cta);

        return $cta;
    }
}
