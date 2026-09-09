<?php

declare(strict_types=1);

namespace App\Infrastructure\Analytics;

use App\Domain\Analytics\Contracts\AnalyticsExporter;
use Illuminate\Support\Facades\Http;

/**
 * Ships analytics rows to ClickHouse via its HTTP interface (JSONEachRow).
 * Not integration-tested in this environment (no ClickHouse); the default
 * exporter is the fake. The domain never sees the HTTP client — only the
 * {@see AnalyticsExporter} contract.
 *
 * @phpstan-type ClickHouseConfig array{endpoint?: string, database?: string, table?: string, username?: string, password?: string}
 */
final class ClickHouseAnalyticsExporter implements AnalyticsExporter
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private readonly array $config,
    ) {}

    public function export(array $rows): void
    {
        if ($rows === []) {
            return;
        }

        $endpoint = (string) ($this->config['endpoint'] ?? '');

        if ($endpoint === '') {
            return;
        }

        $table = (string) ($this->config['table'] ?? 'analytics_events');
        $database = (string) ($this->config['database'] ?? 'default');

        $body = implode("\n", array_map(
            static fn (array $row): string => (string) json_encode($row),
            $rows,
        ));

        Http::withBasicAuth(
            (string) ($this->config['username'] ?? ''),
            (string) ($this->config['password'] ?? ''),
        )
            ->withBody($body, 'application/x-ndjson')
            ->post($endpoint, [
                'query' => "INSERT INTO {$database}.{$table} FORMAT JSONEachRow",
            ])
            ->throw();
    }
}
