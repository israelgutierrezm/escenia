<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Enterprise\Contracts\DomainVerifier;
use App\Domain\Enterprise\Contracts\IdentityProvider;
use App\Infrastructure\Enterprise\Domains\DnsDomainVerifier;
use App\Infrastructure\Enterprise\Domains\FakeDomainVerifier;
use App\Infrastructure\Enterprise\Sso\FakeIdentityProvider;
use App\Infrastructure\Enterprise\Sso\OidcIdentityProvider;
use Illuminate\Support\ServiceProvider;

/**
 * Binds the enterprise providers selected by config/enterprise.php. Keeps the
 * DNS/OIDC/SAML details out of the domain (ADR-008/ADR-030), with network-free,
 * deterministic fakes by default so dev/tests need no external services.
 */
class EnterpriseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(DomainVerifier::class, fn (): DomainVerifier => match (config('enterprise.domain_verifier')) {
            'dns' => new DnsDomainVerifier,
            default => new FakeDomainVerifier,
        });

        $this->app->singleton(IdentityProvider::class, fn (): IdentityProvider => match (config('enterprise.identity_provider')) {
            'oidc' => new OidcIdentityProvider,
            default => new FakeIdentityProvider,
        });
    }
}
