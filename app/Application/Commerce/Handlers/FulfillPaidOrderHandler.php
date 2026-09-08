<?php

declare(strict_types=1);

namespace App\Application\Commerce\Handlers;

use App\Domain\Analytics\Contracts\AnalyticsCollector;
use App\Domain\Analytics\Enums\AnalyticsEventName;
use App\Domain\Commerce\Models\Order;
use App\Domain\Outbox\Contracts\OutboxHandler;
use App\Domain\Outbox\Models\OutboxEvent;

/**
 * Fulfils a paid order (ADR-024): records revenue on the analytics plane and
 * stamps the order fulfilled. Idempotent — a replayed `order.paid` event is a
 * no-op once the order is fulfilled. Runs inside the order's tenant context.
 *
 * The buyer's attendee (and join token) is already issued at checkout, so
 * fulfilment here is the reliable side effect (revenue + future receipt email).
 */
final class FulfillPaidOrderHandler implements OutboxHandler
{
    public function __construct(
        private readonly AnalyticsCollector $analytics,
    ) {}

    public function handle(OutboxEvent $event): void
    {
        $orderId = (int) ($event->payload['order_id'] ?? 0);

        $order = Order::query()->whereKey($orderId)->first();

        if ($order === null || $order->fulfilled_at !== null) {
            return;
        }

        $this->analytics->record(
            AnalyticsEventName::CommerceOrderPaid,
            $order->event()->firstOrFail(),
            $order->attendee,
            [
                'order' => $order->ulid,
                'amount_minor' => $order->total_minor,
                'currency' => $order->currency,
            ],
        );

        $order->forceFill(['fulfilled_at' => now()])->save();
    }
}
