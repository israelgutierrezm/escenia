<?php

declare(strict_types=1);

namespace App\Infrastructure\Enterprise\Sso;

use App\Domain\Enterprise\Models\SsoConnection;

/**
 * Escenia's SAML service-provider URLs for a connection, derived from APP_URL —
 * never from the request's Host header — so the AuthnRequest, the ACS
 * validation and the published metadata always agree. The entity ID is the
 * metadata URL (the usual convention).
 */
final class SamlEndpoints
{
    public static function entityId(SsoConnection $connection): string
    {
        return self::base($connection).'/saml/metadata';
    }

    public static function acsUrl(SsoConnection $connection): string
    {
        return self::base($connection).'/acs';
    }

    /**
     * Single logout service: where the IdP sends LogoutRequests and answers ours.
     */
    public static function sloUrl(SsoConnection $connection): string
    {
        return self::base($connection).'/slo';
    }

    private static function base(SsoConnection $connection): string
    {
        return rtrim((string) config('app.url'), '/').'/api/v1/sso/'.$connection->ulid;
    }
}
