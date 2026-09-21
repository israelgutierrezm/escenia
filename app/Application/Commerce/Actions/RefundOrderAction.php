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
use Illuminate\Support\Facades\DB;

/**
 * The single source of truth for the guarded paid → refunded transition:
 * releases the reserved stock and marks the payment refunded, with optimistic
 * locking (CAS) and idempotency. Shared by the webhook path (gateway told us it
 * refunded) and the host path (we told the gateway to refund) so the money logic
 * lives in one place. Does NOT call the gateway — the caller does that first.
 */
final class RefundOrderAction
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    public function execute(Order $order, ?Payment $payment): Order
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

            $payment?->forceFill(['status' => PaymentStatus::Refunded])->save();

            foreach ($order->items as $item) {
                Ticket::query()->whereKey($item->ticket_id)->decrement('sold_count', $item->quantity);
            }

            $order->refresh();

            $this->audit->log('commerce.order.refunded', tenant: $order->tenant, auditable: $order);

            return $order;
        });
    }
}
