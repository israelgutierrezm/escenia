<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Analytics\Contracts\AnalyticsCollector;
use App\Domain\Audit\Contracts\AuditLogger;
use App\Domain\Billing\Contracts\EntitlementResolver;
use App\Domain\Events\Models\Event;
use App\Domain\FeatureManagement\Contracts\FeatureFlagResolver;
use App\Domain\Notifications\Contracts\Notifier;
use App\Domain\Production\Contracts\SceneDefinitionMigrator;
use App\Domain\Registration\Context\AttendeeContext;
use App\Domain\Studio\Models\Studio;
use App\Domain\Tenancy\Context\TenantContext;
use App\Domain\Workspaces\Models\Workspace;
use App\Http\Policies\EventPolicy;
use App\Http\Policies\StudioPolicy;
use App\Http\Policies\WorkspacePolicy;
use App\Infrastructure\Analytics\DatabaseAnalyticsCollector;
use App\Infrastructure\Audit\DatabaseAuditLogger;
use App\Infrastructure\Billing\PlanEntitlementResolver;
use App\Infrastructure\FeatureManagement\DatabaseFeatureFlagResolver;
use App\Infrastructure\Logging\RequestContext;
use App\Infrastructure\Notifications\LogNotifier;
use App\Infrastructure\Production\DefaultSceneDefinitionMigrator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Wires domain contracts to their infrastructure implementations and registers
 * the request-scoped context singletons and policies. Provider abstractions
 * keep the domain free of SDK-specific types (ADR-008).
 */
class DomainServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        AnalyticsCollector::class => DatabaseAnalyticsCollector::class,
        AuditLogger::class => DatabaseAuditLogger::class,
        EntitlementResolver::class => PlanEntitlementResolver::class,
        FeatureFlagResolver::class => DatabaseFeatureFlagResolver::class,
        Notifier::class => LogNotifier::class,
        SceneDefinitionMigrator::class => DefaultSceneDefinitionMigrator::class,
    ];

    public function register(): void
    {
        // Reset per request (Octane-safe) and shared across a single request.
        $this->app->scoped(TenantContext::class);
        $this->app->scoped(RequestContext::class);
        $this->app->scoped(AttendeeContext::class);
    }

    public function boot(): void
    {
        Gate::policy(Workspace::class, WorkspacePolicy::class);
        Gate::policy(Event::class, EventPolicy::class);
        Gate::policy(Studio::class, StudioPolicy::class);
    }
}
