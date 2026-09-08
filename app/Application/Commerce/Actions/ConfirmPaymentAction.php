<?php

declare(strict_types=1);

namespace App\Application\Commerce\Actions;

use App\Domain\Audit\Contracts\AuditLogger;
use App\Domain\Commerce\Enums\OrderStatus;
use App\Domain\Commerce\Enums\PaymentStatus;
use App\Domain\Commerce\Exceptions\InvalidOrderTransitionException;
use App\Domain\Commerce\Exceptions\OrderTransitionConflictException;
use App\Domain\Commerce\Models\Order;
use App\Domain\Commerce\Models\Payment;
use App\Domain\Commerce\Models\Ticket;
use App\Domain\Commerce\ValueObjects\GatewayEvent;
use App\Domain\Outbox\Models\OutboxEvent;
use Illuminate\Support\Facades\DB;

/**
 * Applies a verified gateway event to an order. On success it transitions
 * pending → paid (guarded + optimistic locking), reserves stock, and writes an
 * `order.paid` row to the outbox (ADR-007) in the same transaction — the revenue
 * side effect is published reliably by the dispatcher. On refund it transitions
 * paid → refunded and releases stock. Idempotent: a replayed webhook is a no-op.
 */
final class ConfirmPaymentAction
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    public function execute(Order $order, Payment $payment, GatewayEvent $event): Order
    {
        return match ($event->status) {
            PaymentStatus::Succeeded => $this->markPaid($order, $payment),
            PaymentStatus::Refunded => $this->markRefunded($order, $payment),
            default => $this->markFailed($order, $payment),
        };
    }

    private function markPaid(Order $order, Payment $payment): Order
    {
        if ($order->status === OrderStatus::Paid) {
            return $order; // idempotent replay
        }

        if (! $order->status->canTransitionTo(OrderStatus::Paid)) {
            throw new InvalidOrderTransitionException($order->status, OrderStatus::Paid);
        }

        return DB::transaction(function () use ($order, $payment): Order {
            $applied = Order::query()
                ->whereKey($order->getKey())
                ->where('status', OrderStatus::Pending->value)
                ->update(['status' => OrderStatus::Paid->value, 'paid_at' => now()]);

            if ($applied === 0) {
                throw new OrderTransitionConflictException(OrderStatus::Pending, OrderStatus::Paid);
            }

            $payment->forceFill(['status' => PaymentStatus::Succeeded])->save();

            foreach ($order->items as $item) {
                Ticket::query()->whereKey($item->ticket_id)->increment('sold_count', $item->quantity);
            }

            OutboxEvent::query()->create([
                'topic' => 'order.paid',
                'payload' => ['order_id' => $order->getKey()],
                'available_at' => now(),
            ]);

            $order->refresh();

            $this->audit->log('commerce.order.paid', tenant: $order->tenant, auditable: $order);

            return $order;
        });
    }

    private function markRefunded(Order $order, Payment $payment): Order
    {
        if ($order->status === OrderStatus::Refunded) {
            return $order; // idempotent replay
        }

        if (! $order->status->canTransitionTo(OrderStatus::Refunded)) {
            throw new InvalidOrderTransitionException($order->status, OrderStatus::Refunded);
        }

        return DB::transaction(function () use ($order, $payment): Order {
            $applied = Order::query()
                ->whereKey($order->getKey())
                ->where('status', OrderStatus::Paid->value)
                ->update(['status' => OrderStatus::Refunded->value, 'refunded_at' => now()]);

            if ($applied === 0) {
                throw new OrderTransitionConflictException(OrderStatus::Paid, OrderStatus::Refunded);
            }

            $payment->forceFill(['status' => PaymentStatus::Refunded])->save();

            foreach ($order->items as $item) {
                Ticket::query()->whereKey($item->ticket_id)->decrement('sold_count', $item->quantity);
            }

            $order->refresh();

            $this->audit->log('commerce.order.refunded', tenant: $order->tenant, auditable: $order);

            return $order;
        });
    }

    private function markFailed(Order $order, Payment $payment): Order
    {
        $payment->forceFill(['status' => PaymentStatus::Failed])->save();

        return $order;
    }
}
