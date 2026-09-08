<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Enums;

/**
 * Supported payment gateways. `fake` is the deterministic, network-free default
 * for local dev and tests; `stripe` and `mercadopago` are the production
 * gateways, both behind the PaymentGateway contract (the domain never sees an
 * SDK).
 */
enum PaymentGatewayName: string
{
    case Fake = 'fake';
    case Stripe = 'stripe';
    case MercadoPago = 'mercadopago';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $g): string => $g->value, self::cases());
    }
}
