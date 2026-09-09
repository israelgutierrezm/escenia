<?php

declare(strict_types=1);

namespace App\Infrastructure\Outbox;

use App\Domain\Outbox\Contracts\EventStreamPublisher;
use Illuminate\Support\Facades\Http;

/**
 * Produces events to Kafka/Redpanda through its REST proxy (ADR-031). Not
 * integration-tested in this environment (no broker); the default publisher is
 * the null one. The domain never sees a broker SDK — only the
 * {@see EventStreamPublisher} contract. The event ulid is the partition key, so
 * per-aggregate ordering is preserved and consumers can dedupe (at-least-once).
 *
 * @phpstan-type KafkaConfig array{rest_proxy?: string, topic_prefix?: string}
 */
final class KafkaEventStreamPublisher implements EventStreamPublisher
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private readonly array $config,
    ) {}

    public function publish(string $topic, string $key, array $payload): void
    {
        $proxy = (string) ($this->config['rest_proxy'] ?? '');

        if ($proxy === '') {
            return;
        }

        $prefix = (string) ($this->config['topic_prefix'] ?? '');
        $streamTopic = $prefix.str_replace('.', '_', $topic);

        Http::withHeaders(['Content-Type' => 'application/vnd.kafka.json.v2+json'])
            ->post(rtrim($proxy, '/')."/topics/{$streamTopic}", [
                'records' => [
                    ['key' => $key, 'value' => $payload],
                ],
            ])
            ->throw();
    }
}
