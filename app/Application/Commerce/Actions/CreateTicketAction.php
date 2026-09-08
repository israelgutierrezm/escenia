<?php

declare(strict_types=1);

namespace App\Application\Commerce\Actions;

use App\Application\Commerce\DTOs\CreateTicketData;
use App\Domain\Audit\Contracts\AuditLogger;
use App\Domain\Commerce\Models\Ticket;
use App\Domain\Events\Models\Event;
use App\Domain\Identity\Models\User;

/**
 * Creates a ticket type for an event. Price is integer minor units + currency
 * (never a float). New tickets go to the end of the list.
 */
final class CreateTicketAction
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    public function execute(Event $event, User $actor, CreateTicketData $data): Ticket
    {
        $position = (int) Ticket::query()->where('event_id', $event->getKey())->max('position');

        $ticket = Ticket::query()->create([
            'event_id' => $event->getKey(),
            'name' => $data->name,
            'description' => $data->description,
            'amount_minor' => $data->amountMinor,
            'currency' => $data->currency,
            'compare_at_minor' => $data->compareAtMinor,
            'capacity' => $data->capacity,
            'sold_count' => 0,
            'is_active' => true,
            'sales_start_at' => $data->salesStartAt,
            'sales_end_at' => $data->salesEndAt,
            'position' => $position + 1,
        ]);

        $this->audit->log('commerce.ticket.created', actor: $actor, tenant: $event->tenant, auditable: $ticket);

        return $ticket;
    }
}
