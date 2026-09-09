<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Enterprise providers (ADR-030)
    |--------------------------------------------------------------------------
    | Custom-domain verification and SSO both sit behind contracts. The `fake`
    | implementations are deterministic and network-free (default for dev and
    | tests); the real ones (`dns`, `oidc`) need per-environment config and are
    | not integration-tested in this repo.
    */
    'domain_verifier' => env('ENTERPRISE_DOMAIN_VERIFIER', 'fake'),   // fake|dns
    'identity_provider' => env('ENTERPRISE_IDENTITY_PROVIDER', 'fake'), // fake|oidc

    /*
    | CNAME target tenants point their custom domain at. Surfaced to the tenant
    | alongside the TXT challenge so they can finish DNS setup.
    */
    'domain_target' => env('ENTERPRISE_DOMAIN_TARGET', 'ingress.escenia.app'),

    /*
    | Default data residency region for new tenants. Recorded as intent; actual
    | region pinning/enforcement is future work (see technical debt).
    */
    'default_region' => env('ENTERPRISE_DEFAULT_REGION', 'us'),
];
