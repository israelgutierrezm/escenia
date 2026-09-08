<?php

declare(strict_types=1);

namespace App\Application\Commerce;

use App\Domain\Commerce\Enums\OrderStatus;
use App\Domain\Commerce\Models\Cta;
use App\Domain\Commerce\Models\CtaClick;
use App\Domain\Commerce\Models\Order;
use App\Domain\Commerce\Models\OrderItem;
use App\Domain\Commerce\Models\Ticket;
use App\Domain\Events\Models\Event;

/**
 * Revenue reporting for an event. Unlike the analytics reports (derived from the
 * telemetry plane), money is read from the OLTP commerce ledger — orders,
 * items, payments — because financial figures must be exact. All amounts are
 * integer minor units in the event's currency.
 */
final class EventCommerceReport
{
    /**
     * @return array<string, mixed>
     */
    public function revenue(Event $event): array
    {
        $grossMinor = (int) Order::query()
            ->where('event_id', $event->getKey())
            ->where('status', OrderStatus::Paid->value)
            ->sum('total_minor');

        $refundedMinor = (int) Order::query()
            ->where('event_id', $event->getKey())
            ->where('status', OrderStatus::Refunded->value)
            ->sum('total_minor');

        $ordersPaid = Order::query()
            ->where('event_id', $event->getKey())
            ->where('status', OrderStatus::Paid->value)
            ->count();

        $currency = (string) (Ticket::query()->where('event_id', $event->getKey())->value('currency')
            ?? Order::query()->where('event_id', $event->getKey())->value('currency')
            ?? 'USD');

        return [
            'currency' => $currency,
            'gross_minor' => $grossMinor,
            'refunded_minor' => $refundedMinor,
            'net_minor' => $grossMinor - $refundedMinor,
            'orders_paid' => $ordersPaid,
            'avg_order_minor' => $ordersPaid > 0 ? intdiv($grossMinor, $ordersPaid) : 0,
            'by_ticket' => $this->byTicket($event),
            'cta' => $this->ctaPerformance($event),
        ];
    }

    /**
     * @return list<array{name: string, units: int, revenue_minor: int}>
     */
    private function byTicket(Event $event): array
    {
        $paidOrderIds = Order::query()
            ->where('event_id', $event->getKey())
            ->where('status', OrderStatus::Paid->value)
            ->pluck('id');

        return OrderItem::query()
            ->whereIn('order_id', $paidOrderIds)
            ->groupBy('ticket_id', 'ticket_name')
            ->selectRaw('ticket_name, SUM(quantity) as units, SUM(subtotal_minor) as revenue_minor')
            ->toBase()
            ->get()
            ->map(fn (object $row): array => [
                'name' => (string) $row->ticket_name,
                'units' => (int) $row->units,
                'revenue_minor' => (int) $row->revenue_minor,
            ])
            ->all();
    }

    /**
     * @return array{clicks: int, unique_clickers: int}
     */
    private function ctaPerformance(Event $event): array
    {
        $clicks = (int) Cta::query()->where('event_id', $event->getKey())->sum('clicks_count');

        $ctaIds = Cta::query()->where('event_id', $event->getKey())->pluck('id');
        $uniqueClickers = CtaClick::query()
            ->whereIn('cta_id', $ctaIds)
            ->whereNotNull('attendee_id')
            ->distinct()
            ->count('attendee_id');

        return ['clicks' => $clicks, 'unique_clickers' => $uniqueClickers];
    }
}
