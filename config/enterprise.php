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
    // fake|real — `real` routes each connection to its OIDC or SAML adapter
    // (`oidc` is accepted as an alias). Fakes are refused in production (ADR-034).
    'identity_provider' => env('ENTERPRISE_IDENTITY_PROVIDER', 'fake'),

    /*
    | Server-side calls to tenant-configured URLs (IdP endpoints) are pinned to
    | public addresses (SSRF guard). Allowing private networks is only for local
    | development against an IdP on localhost — never in production.
    */
    'egress_allow_private_networks' => (bool) env('ENTERPRISE_EGRESS_ALLOW_PRIVATE', false),

    /*
    | Verified SSO domains are re-checked daily; one that keeps failing its DNS
    | challenge for this long loses its verification (and its logins).
    */
    'sso_domain_grace_hours' => (int) env('ENTERPRISE_SSO_DOMAIN_GRACE_HOURS', 72),

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
