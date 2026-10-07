<?php

declare(strict_types=1);

namespace App\Application\Enterprise\Actions;

use App\Domain\Audit\Contracts\AuditLogger;
use App\Domain\Enterprise\Contracts\ServiceProviderCredentialIssuer;
use App\Domain\Enterprise\Enums\SsoProvider;
use App\Domain\Enterprise\Models\SsoConnection;
use App\Domain\Identity\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Issues new service-provider credentials for a SAML connection (ADR-036):
 * key rotation, or a first key pair for a connection created before they
 * existed. The IdP must re-import the SP metadata afterwards — until then it
 * cannot verify signed requests nor encrypt to the new certificate.
 */
final class RotateSamlCredentialsAction
{
    public function __construct(
        private readonly ServiceProviderCredentialIssuer $issuer,
        private readonly AuditLogger $audit,
    ) {}

    public function execute(User $actor, SsoConnection $connection): SsoConnection
    {
        if ($connection->provider !== SsoProvider::Saml) {
            throw ValidationException::withMessages(['provider' => 'Only SAML connections have service-provider credentials.']);
        }

        $connection->assignServiceProviderCredentials($this->issuer->issue(CreateSsoConnectionAction::SP_COMMON_NAME));
        $connection->save();

        $this->audit->log('enterprise.sso.sp_credentials_rotated', actor: $actor, auditable: $connection);

        return $connection->refresh();
    }
}
