<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Enterprise\Jobs\ReverifySsoDomainJob;
use App\Domain\Enterprise\Models\SsoConnection;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * Queues the DNS re-check of every verified SSO domain not checked in the last
 * day (ADR-035). Scheduled hourly, so checks spread out instead of bunching.
 */
class ReverifySsoDomainsCommand extends Command
{
    protected $signature = 'enterprise:reverify-sso-domains';

    protected $description = 'Queue the daily DNS re-check of verified SSO domains';

    public function handle(): int
    {
        $due = SsoConnection::query()
            ->withoutGlobalScopes()
            ->whereNotNull('domain_verified_at')
            ->where(function (Builder $query): void {
                $query->whereNull('domain_checked_at')->orWhere('domain_checked_at', '<=', now()->subDay());
            })
            ->pluck('id');

        foreach ($due as $id) {
            ReverifySsoDomainJob::dispatch((int) $id);
        }

        $this->info("Queued {$due->count()} SSO domain re-check(s).");

        return self::SUCCESS;
    }
}
