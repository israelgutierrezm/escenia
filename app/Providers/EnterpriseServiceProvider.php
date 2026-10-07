<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Enterprise\Contracts\DomainVerifier;
use App\Domain\Enterprise\Contracts\IdentityProvider;
use App\Domain\Settings\Services\Settings;
use App\Infrastructure\Enterprise\Domains\DisabledDomainVerifier;
use App\Infrastructure\Enterprise\Domains\DnsDomainVerifier;
use App\Infrastructure\Enterprise\Domains\FakeDomainVerifier;
use App\Infrastructure\Enterprise\Sso\DisabledIdentityProvider;
use App\Infrastructure\Enterprise\Sso\FakeIdentityProvider;
use App\Infrastructure\Enterprise\Sso\RoutingIdentityProvider;
use App\Infrastructure\Http\DnsHostResolver;
use App\Infrastructure\Http\HostResolver;
use App\Infrastructure\Http\OutboundUrlGuard;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

/**
 * Binds the enterprise providers selected by config/enterprise.php. Keeps the
 * DNS/OIDC/SAML details out of the domain (ADR-008/ADR-030), with network-free,
 * deterministic fakes by default so dev/tests need no external services.
 *
 * Both are security controls (who may log in, who owns a domain), so they fail
 * closed: the fakes approve anything and are refused in production, and an
 * unknown driver binds the disabled implementation (ADR-034).
 */
class EnterpriseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(HostResolver::class, DnsHostResolver::class);
        $this->app->bind(OutboundUrlGuard::class, fn (): OutboundUrlGuard => new OutboundUrlGuard(
            $this->app->make(HostResolver::class),
            (bool) config('enterprise.egress_allow_private_networks'),
        ));

        $this->app->singleton(DomainVerifier::class, fn (): DomainVerifier => match ($this->driver('enterprise.domain_verifier')) {
            'dns' => new DnsDomainVerifier,
            'fake' => $this->fakeAllowed('enterprise.domain_verifier') ? new FakeDomainVerifier : new DisabledDomainVerifier,
            default => new DisabledDomainVerifier,
        });

        $this->app->singleton(IdentityProvider::class, fn (): IdentityProvider => match ($this->driver('enterprise.identity_provider')) {
            'real', 'oidc' => $this->app->make(RoutingIdentityProvider::class),
            'fake' => $this->fakeAllowed('enterprise.identity_provider') ? new FakeIdentityProvider : new DisabledIdentityProvider,
            default => new DisabledIdentityProvider,
        });
    }

    private function driver(string $key): string
    {
        $driver = $this->app->make(Settings::class)->get($key, config($key));

        return is_string($driver) ? $driver : '';
    }

    private function fakeAllowed(string $key): bool
    {
        if (! $this->app->isProduction()) {
            return true;
        }

        Log::warning('Refusing the fake enterprise provider in production; binding the disabled one.', ['setting' => $key]);

        return false;
    }
}
