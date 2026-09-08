<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Publish transactional-outbox events (ADR-007) at-least-once, every minute.
Schedule::command('outbox:dispatch')->everyMinute()->withoutOverlapping();
