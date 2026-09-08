<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default payment gateway
    |--------------------------------------------------------------------------
    | Used when a tenant has no connected PaymentAccount (e.g. local dev/tests).
    | `fake` is deterministic and network-free. Real gateways (stripe,
    | mercadopago) require a per-tenant PaymentAccount with encrypted
    | credentials — see ADR-024.
    */
    'default' => env('PAYMENTS_DEFAULT_GATEWAY', 'fake'),
];
