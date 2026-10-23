<?php

declare(strict_types=1);

namespace App\Providers;

use App\Infrastructure\Http\DnsHostResolver;
use App\Infrastructure\Http\HostResolver;
use App\Infrastructure\Http\OutboundUrlGuard;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->registerEgressGuard();
    }

    /**
     * Every server-side request to a tenant-configured URL (IdP endpoints,
     * automation webhooks) goes through one guard (ADR-035, ADR-037).
     */
    private function registerEgressGuard(): void
    {
        $this->app->singleton(HostResolver::class, DnsHostResolver::class);
        $this->app->bind(OutboundUrlGuard::class, fn (): OutboundUrlGuard => new OutboundUrlGuard(
            $this->app->make(HostResolver::class),
            $this->allowsPrivateEgress(),
        ));
    }

    /**
     * Private networks are a local-development escape hatch: in production the
     * flag is ignored, so the guard fails closed (like the SSO fakes, ADR-034).
     */
    private function allowsPrivateEgress(): bool
    {
        if (! (bool) config('egress.allow_private_networks')) {
            return false;
        }

        if ($this->app->isProduction()) {
            Log::warning('Ignoring EGRESS_ALLOW_PRIVATE in production: server-side requests stay restricted to public addresses.');

            return false;
        }

        return true;
    }

    public function boot(): void
    {
        $this->configureModels();
        $this->configureRateLimiting();
    }

    private function configureModels(): void
    {
        // Catch developer mistakes early outside production: missing attributes
        // and silently discarded mass-assignment values become exceptions.
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());
        Model::preventAccessingMissingAttributes(! $this->app->isProduction());
        Model::unguard(false);
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', fn (Request $request): Limit => Limit::perMinute(60)
            ->by($request->user()?->getAuthIdentifier() ?? $request->ip()));

        RateLimiter::for('login', fn (Request $request): Limit => Limit::perMinute(5)
            ->by(Str::lower((string) $request->input('email')).'|'.$request->ip()));
    }
}
