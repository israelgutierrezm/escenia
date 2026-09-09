<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Analytics\Contracts\AnalyticsCollector;
use App\Domain\Analytics\Contracts\AnalyticsExporter;
use App\Domain\Outbox\Contracts\EventStreamPublisher;
use App\Domain\Settings\Services\Settings;
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
        $this->app->singleton(AnalyticsCollector::class, fn (): AnalyticsCollector => match (app(Settings::class)->get('analytics.capture', config('analytics.capture'))) {
            'queue' => new QueuedAnalyticsCollector,
            default => new DatabaseAnalyticsCollector,
        });

        $this->app->singleton(AnalyticsExporter::class, function (): AnalyticsExporter {
            $settings = app(Settings::class);

            return match ($settings->get('analytics.export', config('analytics.export'))) {
                'clickhouse' => new ClickHouseAnalyticsExporter([
                    'endpoint' => $settings->get('analytics.clickhouse.endpoint', config('analytics.clickhouse.endpoint')),
                    'database' => config('analytics.clickhouse.database'),
                    'table' => config('analytics.clickhouse.table'),
                    'username' => config('analytics.clickhouse.username'),
                    'password' => $settings->get('analytics.clickhouse.password', config('analytics.clickhouse.password')),
                ]),
                default => new FakeAnalyticsExporter,
            };
        });

        $this->app->singleton(EventStreamPublisher::class, function (): EventStreamPublisher {
            $settings = app(Settings::class);

            return match ($settings->get('outbox.stream', config('outbox.stream'))) {
                'log' => new LogEventStreamPublisher,
                'kafka' => new KafkaEventStreamPublisher([
                    'rest_proxy' => $settings->get('outbox.kafka.rest_proxy', config('outbox.kafka.rest_proxy')),
                    'topic_prefix' => config('outbox.kafka.topic_prefix'),
                ]),
                default => new NullEventStreamPublisher,
            };
        });
    }
}
