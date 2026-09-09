<?php

declare(strict_types=1);

namespace App\Domain\Enterprise\Enums;

/**
 * Lifecycle of a custom domain: created (awaiting DNS) -> ownership proven and
 * serving, or failed. `failed` is non-terminal — the tenant can re-verify once
 * the DNS record is in place.
 */
enum DomainStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Failed = 'failed';
}
