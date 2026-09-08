<?php

declare(strict_types=1);

namespace App\Application\Commerce\Actions;

use App\Domain\Commerce\Exceptions\WebhookVerificationException;
use App\Domain\Commerce\Models\Order;
use App\Domain\Commerce\Models\Payment;
use App\Domain\Commerce\Models\PaymentAccount;
use App\Domain\Tenancy\Context\TenantContext;
use App\Infrastructure\Payments\PaymentGatewayFactory;

/**
 * Public entry point for a gateway webhook. Verifies and parses the payload with
 * the account's gateway, matches the payment by (gateway, reference) unscoped,
 * then applies the event within the order's tenant context. The account is
 * identified by its public ULID in the webhook URL; security comes from the
 * signature verification, not the URL.
 */
final class HandleWebhookAction
{
    public function __construct(
        private readonly PaymentGatewayFactory $gateways,
        private readonly ConfirmPaymentAction $confirm,
        private readonly TenantContext $tenantContext,
    ) {}

    public function execute(PaymentAccount $account, string $rawPayload, string $signature): void
    {
        $gatewayEvent = $this->gateways->for($account)->parseWebhook($rawPayload, $signature, $account);

        $payment = Payment::query()
            ->withoutGlobalScopes()
            ->where('gateway', $account->gateway->value)
            ->where('gateway_reference', $gatewayEvent->reference)
            ->first();

        if ($payment === null) {
            throw new WebhookVerificationException('Unknown payment reference.');
        }

        $order = Order::query()->withoutGlobalScopes()->whereKey($payment->order_id)->firstOrFail();

        $this->tenantContext->runFor($order->tenant, function () use ($order, $payment, $gatewayEvent): void {
            $this->confirm->execute($order, $payment, $gatewayEvent);
        });
    }
}
