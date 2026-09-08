<?php

declare(strict_types=1);

namespace App\Application\Automation;

use App\Domain\Automation\Enums\TriggerEvent;
use App\Domain\Commerce\Models\Order;
use App\Domain\Events\Models\Event;
use App\Domain\Registration\Models\Contact;

/**
 * Builds the flattened context an automation reads from, out of a trigger's
 * outbox payload (which carries ids). Public dotted fields (`event.type`,
 * `contact.email`, `order.total_minor`) feed conditions and webhooks; the
 * `_`-prefixed keys are internal ids for actions and never leave the system.
 * Runs inside the event's tenant context, so every lookup is tenant-scoped.
 */
final class TriggerContextBuilder
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function build(TriggerEvent $trigger, array $payload): array
    {
        $context = ['trigger' => $trigger->value];

        $order = isset($payload['order_id']) ? Order::query()->find((int) $payload['order_id']) : null;
        $event = $order?->event()->first() ?? (isset($payload['event_id']) ? Event::query()->find((int) $payload['event_id']) : null);
        $contact = $order?->contact()->first() ?? (isset($payload['contact_id']) ? Contact::query()->find((int) $payload['contact_id']) : null);

        if ($event !== null) {
            $context['event.id'] = $event->ulid;
            $context['event.type'] = $event->type->value;
            $context['event.title'] = $event->title;
            $context['_event_id'] = $event->getKey();
        }

        if ($contact !== null) {
            $context['contact.email'] = $contact->email;
            $context['contact.name'] = $contact->name;
            $context['_contact_id'] = $contact->getKey();
        }

        if ($order !== null) {
            $context['order.id'] = $order->ulid;
            $context['order.total_minor'] = $order->total_minor;
            $context['order.currency'] = $order->currency;
        }

        return $context;
    }
}
