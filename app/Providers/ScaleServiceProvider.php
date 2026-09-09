<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Analytics\Contracts\AnalyticsCollector;
use App\Domain\Analytics\Contracts\AnalyticsExporter;
use App\Domain\Outbox\Contracts\EventStreamPublisher;
use App\Infrastructure\Analytics\ClickHouseAnalyticsExporter;
use App\Infrastructure\Analytics\DatabaseAnalyticsCollector;
use App\Infrastructure\Analytics\FakeAnalyticsExporter;
use App\Infrastructure\Analytics\QueuedAnalyticsCollector;
use App\Infrastructure\Outbox\KafkaEventStreamPublisher;
use App\Infrastructure\Outbox\LogEventStreamPublisher;
use App\Infrastructure\Outbox\NullEventStreamPublisher;
use Illuminate\Support\ServiceProvider;

/**
 * Binds the scale seams selected by config (ADR-031): synchronous vs queued
 * analytics capture, the warehouse exporter, and the outbox event-stream
 * publisher. Every default is network-free (sync/fake/null) so no infra is
 * introduced before it is needed (CLAUDE.md §3).
 */
class ScaleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AnalyticsCollector::class, fn (): AnalyticsCollector => match (config('analytics.capture')) {
            'queue' => new QueuedAnalyticsCollector,
            default => new DatabaseAnalyticsCollector,
        });

        $this->app->singleton(AnalyticsExporter::class, fn (): AnalyticsExporter => match (config('analytics.export')) {
            'clickhouse' => new ClickHouseAnalyticsExporter((array) config('analytics.clickhouse')),
            default => new FakeAnalyticsExporter,
        });

        $this->app->singleton(EventStreamPublisher::class, fn (): EventStreamPublisher => match (config('outbox.stream')) {
            'log' => new LogEventStreamPublisher,
            'kafka' => new KafkaEventStreamPublisher((array) config('outbox.kafka')),
            default => new NullEventStreamPublisher,
        });
    }
}
