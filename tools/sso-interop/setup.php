<?php

// Seeds an isolated Escenia (SQLite, see interop.mjs) for the SSO interop run:
// the SAML IdP's signing key + certificate, an owner with a tenant, and one
// OIDC + one SAML connection for acme.com with verified domains. Prints JSON.

declare(strict_types=1);

use App\Application\Enterprise\Actions\CreateSsoConnectionAction;
use App\Application\Identity\Actions\RegisterUserAction;
use App\Application\Identity\DTOs\RegisterUserData;
use App\Domain\Enterprise\Contracts\ServiceProviderCredentialIssuer;
use App\Domain\Enterprise\Enums\SsoProvider;
use App\Domain\Tenancy\Context\TenantContext;
use App\Domain\Tenancy\Enums\TenantRole;
use Illuminate\Contracts\Console\Kernel;

[$self, $repo, $dir] = $argv;

require $repo.'/vendor/autoload.php';
$app = require $repo.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$issued = app(ServiceProviderCredentialIssuer::class)->issue('Interop SAML IdP');
file_put_contents($dir.'/idp-cert.pem', $issued->certificate);
file_put_contents($dir.'/idp-key.pem', $issued->privateKey);

$owner = app(RegisterUserAction::class)->execute(new RegisterUserData(
    name: 'Interop Owner',
    email: 'owner@acme.com',
    password: 'interop-password-123',
    tenantName: 'Acme Interop',
));
$tenant = $owner->tenants()->firstOrFail();

[$oidc, $saml] = app(TenantContext::class)->runFor($tenant, function () use ($owner, $issued): array {
    $create = app(CreateSsoConnectionAction::class);

    $oidc = $create->execute($owner, SsoProvider::Oidc, 'Interop OIDC', 'acme.com', [
        'issuer' => 'http://127.0.0.1:8032',
        'client_id' => 'escenia',
        'client_secret' => 'interop-secret',
    ], TenantRole::Member);

    $saml = $create->execute($owner, SsoProvider::Saml, 'Interop SAML', 'acme.com', [
        'idp_entity_id' => 'http://127.0.0.1:8033/metadata',
        'idp_sso_url' => 'http://127.0.0.1:8033/sso',
        'idp_slo_url' => 'http://127.0.0.1:8033/slo',
        'idp_x509_cert' => $issued->certificate,
        'sign_requests' => true,
        'encrypt_assertions' => true,
    ], TenantRole::Member);

    foreach ([$oidc, $saml] as $connection) {
        $connection->forceFill(['domain_verified_at' => now()])->save();
    }

    return [$oidc, $saml];
});

echo json_encode(['oidc' => $oidc->ulid, 'saml' => $saml->ulid]), PHP_EOL;
