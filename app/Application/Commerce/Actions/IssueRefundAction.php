<?php

declare(strict_types=1);

namespace App\Application\Commerce\Actions;

use App\Domain\Commerce\Enums\OrderStatus;
use App\Domain\Commerce\Enums\PaymentStatus;
use App\Domain\Commerce\Exceptions\InvalidOrderTransitionException;
use App\Domain\Commerce\Models\Order;
use App\Domain\Commerce\Models\PaymentAccount;
use App\Infrastructure\Payments\PaymentGatewayFactory;

/**
 * Host-initiated refund: tell the gateway to refund the succeeded payment, then
 * apply the guarded paid → refunded transition (shared with the webhook path).
 * The order is guarded BEFORE the external call so a non-refundable order never
 * reaches the gateway. The gateway call stays outside the DB transaction (which
 * lives in RefundOrderAction) so a slow gateway never holds row locks.
 */
final class IssueRefundAction
{
    public function __construct(
        private readonly PaymentGatewayFactory $gateways,
        private readonly RefundOrderAction $refunds,
    ) {}

    public function execute(Order $order): Order
    {
        if ($order->status === OrderStatus::Refunded) {
            return $order; // idempotent
        }

        if (! $order->status->canTransitionTo(OrderStatus::Refunded)) {
            throw new InvalidOrderTransitionException($order->status, OrderStatus::Refunded);
        }

        $payment = $order->payments()
            ->where('status', PaymentStatus::Succeeded->value)
            ->latest('id')
            ->first();

        $account = PaymentAccount::query()->where('is_active', true)->first();

        if ($payment !== null) {
            $this->gateways->for($account)->refund($order, $payment, $account);
        }

        return $this->refunds->execute($order, $payment);
    }
}
