<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Publish transactional-outbox events (ADR-007) at-least-once, every minute.
Schedule::command('outbox:dispatch')->everyMinute()->withoutOverlapping(10);

// Resume automation runs that are due (ADR-025, ADR-038): waits, retries and
// runs whose worker died. The mutex expires after 10 minutes (not 24 h) so a
// crashed process does not block it; the per-run claim keeps it correct.
Schedule::command('automations:resume')->everyMinute()->withoutOverlapping(10);

// Extract analytics rows to the warehouse sink (ADR-031), every five minutes.
Schedule::command('analytics:extract')->everyFiveMinutes()->withoutOverlapping();

// Re-check verified SSO domains (ADR-035): hourly sweep, each domain about daily.
Schedule::command('enterprise:reverify-sso-domains')->hourly()->withoutOverlapping();
