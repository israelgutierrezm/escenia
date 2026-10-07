<?php

declare(strict_types=1);

namespace App\Application\Enterprise\Actions;

use App\Domain\Enterprise\Contracts\SingleLogoutProvider;
use App\Domain\Enterprise\DTOs\LogoutRedirect;
use App\Domain\Enterprise\Models\SsoConnection;

/**
 * When a session that SSO opened ends, also end the IdP session if the
 * connection supports single logout (ADR-036). Null keeps the logout local.
 */
final class StartSingleLogoutAction
{
    public function __construct(
        private readonly SingleLogoutProvider $logout,
    ) {}

    /**
     * @param  array<string, mixed>  $session  what the SSO login stored in the session
     */
    public function execute(array $session): ?LogoutRedirect
    {
        $ulid = $session['connection'] ?? null;

        $connection = is_string($ulid) ? SsoConnection::query()
            ->withoutGlobalScopes()
            ->where('ulid', $ulid)
            ->where('is_active', true)
            ->first() : null;

        return $connection !== null ? $this->logout->start($connection, $session) : null;
    }
}
