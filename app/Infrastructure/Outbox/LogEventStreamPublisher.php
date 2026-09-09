<?php

declare(strict_types=1);

namespace App\Infrastructure\Outbox;

use App\Domain\Outbox\Contracts\EventStreamPublisher;
use Illuminate\Support\Facades\Log;

/**
 * Development publisher: writes a structured log line for each event instead of
 * producing to a broker. Useful to watch the fan-out locally without any infra.
 */
final class LogEventStreamPublisher implements EventStreamPublisher
{
    public function publish(string $topic, string $key, array $payload): void
    {
        Log::info('outbox.stream.published', [
            'topic' => $topic,
            'key' => $key,
        ]);
    }
}
