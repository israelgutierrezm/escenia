<?php

declare(strict_types=1);

namespace App\Domain\Enterprise\Contracts;

use App\Domain\Enterprise\DTOs\LogoutRedirect;
use App\Domain\Enterprise\Models\SsoConnection;

/**
 * Single logout (ADR-036): ending the IdP session when the user signs out of
 * Escenia. Only protocols/IdPs that support it answer; the rest return null
 * and the logout stays local.
 */
interface SingleLogoutProvider
{
    /**
     * @param  array<string, mixed>  $session  what the login stored about the IdP session
     */
    public function start(SsoConnection $connection, array $session): ?LogoutRedirect;
}
