<?php

declare(strict_types=1);

namespace App\Domain\Enterprise\Enums;

use App\Domain\Enterprise\Contracts\IdentityProvider;

/**
 * The kind of identity provider backing an SSO connection. Both are resolved
 * through the {@see IdentityProvider} contract
 * so the domain never depends on a concrete OIDC/SAML library.
 */
enum SsoProvider: string
{
    case Oidc = 'oidc';
    case Saml = 'saml';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $p): string => $p->value, self::cases());
    }
}
