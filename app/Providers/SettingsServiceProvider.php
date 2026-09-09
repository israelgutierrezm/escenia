<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Settings\Contracts\SettingsRepository;
use App\Domain\Settings\Services\Settings;
use App\Infrastructure\Settings\DatabaseSettingsRepository;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the runtime configuration store (ADR-033). Bound per request (scoped) so
 * the repository's resolution memo lives for one request and never leaks across
 * requests under Octane.
 */
class SettingsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(SettingsRepository::class, DatabaseSettingsRepository::class);
        $this->app->scoped(Settings::class);
    }
}
