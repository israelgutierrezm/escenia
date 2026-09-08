<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Contracts;

use App\Domain\Commerce\Exceptions\WebhookVerificationException;
use App\Domain\Commerce\Models\Order;
use App\Domain\Commerce\Models\PaymentAccount;
use App\Domain\Commerce\ValueObjects\GatewayEvent;
use App\Domain\Commerce\ValueObjects\PaymentIntentResult;

/**
 * A payment gateway adapter (ADR-024). Keeps Stripe / Mercado Pago SDKs out of
 * the domain: the domain speaks only Money and these gateway-agnostic Value
 * Objects. Implementations must verify webhook authenticity before returning a
 * GatewayEvent.
 */
interface PaymentGateway
{
    /**
     * Create a payment intent for an order and return the client-facing handle.
     */
    public function createIntent(Order $order, ?PaymentAccount $account): PaymentIntentResult;

    /**
     * Verify and parse an incoming webhook into a gateway-agnostic event.
     *
     * @throws WebhookVerificationException
     */
    public function parseWebhook(string $payload, string $signature, PaymentAccount $account): GatewayEvent;
}
