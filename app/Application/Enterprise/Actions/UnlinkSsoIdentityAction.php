<?php

declare(strict_types=1);

namespace App\Application\Enterprise\Actions;

use App\Domain\Audit\Contracts\AuditLogger;
use App\Domain\Enterprise\Models\SsoIdentity;
use App\Domain\Identity\Models\User;

/**
 * Forgets which IdP subject an account answers to on a connection (ADR-035).
 * The way out when a person's IdP account was recreated (new subject, same
 * email): until unlinked, that login is refused as a possible reassigned
 * address; afterwards the next login links the new subject by email.
 */
final class UnlinkSsoIdentityAction
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    public function execute(User $actor, SsoIdentity $identity): void
    {
        $identity->loadMissing(['connection', 'user']);
        $identity->delete();

        $this->audit->log('enterprise.sso.identity_unlinked', actor: $actor, auditable: $identity->connection, context: [
            'subject' => $identity->subject,
            'user' => $identity->user->ulid,
        ]);
    }
}
