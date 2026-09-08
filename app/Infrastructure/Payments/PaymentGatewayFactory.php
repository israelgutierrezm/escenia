<?php

declare(strict_types=1);

namespace App\Infrastructure\Payments;

use App\Domain\Commerce\Contracts\PaymentGateway;
use App\Domain\Commerce\Enums\PaymentGatewayName;
use App\Domain\Commerce\Models\PaymentAccount;

/**
 * Resolves the concrete {@see PaymentGateway} for a tenant's payment account,
 * falling back to the config default (`fake` in dev/tests) when no account is
 * connected. Keeps gateway selection in one place so callers stay
 * SDK-agnostic (ADR-024).
 */
final class PaymentGatewayFactory
{
    public function for(?PaymentAccount $account): PaymentGateway
    {
        $name = $account !== null
            ? $account->gateway
            : PaymentGatewayName::from((string) config('payments.default', 'fake'));

        return match ($name) {
            PaymentGatewayName::Stripe => new StripePaymentGateway,
            PaymentGatewayName::MercadoPago => new MercadoPagoPaymentGateway,
            PaymentGatewayName::Fake => new FakePaymentGateway,
        };
    }
}
