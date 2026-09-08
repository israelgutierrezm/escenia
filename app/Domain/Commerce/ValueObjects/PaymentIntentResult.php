<?php

declare(strict_types=1);

namespace App\Domain\Commerce\ValueObjects;

use App\Domain\Commerce\Enums\PaymentStatus;

/**
 * Gateway-agnostic result of creating a payment intent. The client uses either
 * `clientSecret` (Stripe-style confirm in the browser) or `redirectUrl`
 * (hosted-checkout redirect, e.g. Mercado Pago).
 */
final class PaymentIntentResult
{
    public function __construct(
        public readonly string $reference,
        public readonly PaymentStatus $status,
        public readonly ?string $clientSecret = null,
        public readonly ?string $redirectUrl = null,
    ) {}
}
