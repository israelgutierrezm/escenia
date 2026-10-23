<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Server-side egress (ADR-035, ADR-037)
    |--------------------------------------------------------------------------
    | Requests the server makes to URLs a tenant configured (IdP endpoints,
    | automation webhooks) only reach public addresses and are pinned to the
    | address that was checked, without following redirects (SSRF guard).
    |
    | Allowing private networks is a local-development escape hatch (an IdP or a
    | receiver on localhost). It is ignored in production. The former
    | ENTERPRISE_EGRESS_ALLOW_PRIVATE name is still read.
    */
    'allow_private_networks' => (bool) env('EGRESS_ALLOW_PRIVATE', env('ENTERPRISE_EGRESS_ALLOW_PRIVATE', false)),
];
