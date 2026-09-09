<?php

declare(strict_types=1);

namespace App\Infrastructure\Ai;

use App\Domain\Ai\Contracts\VectorIndex;
use App\Domain\Ai\Models\ContentChunk;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Qdrant vector index adapter (ADR-005/027). Upserts each chunk's vector keyed
 * by the chunk id with tenant/event payload for filtering, and searches with a
 * payload filter. Chunk text still lives in `content_chunks`; search returns
 * chunk ids + scores. Not integration-tested (see technical-debt); the DB index
 * is the default.
 */
final class QdrantVectorIndex implements VectorIndex
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private readonly array $config,
    ) {}

    public function index(ContentChunk $chunk): void
    {
        $this->request()->put($this->url("/collections/{$this->collection()}/points"), [
            'points' => [[
                'id' => $chunk->getKey(),
                'vector' => array_values($chunk->vector),
                'payload' => [
                    'tenant_id' => $chunk->tenant_id,
                    'event_id' => $chunk->event_id,
                ],
            ]],
        ])->throw();
    }

    /**
     * @param  list<float>  $vector
     * @return list<array{chunk_id: int, score: float}>
     */
    public function search(int $tenantId, ?int $eventId, array $vector, int $limit): array
    {
        $must = [['key' => 'tenant_id', 'match' => ['value' => $tenantId]]];

        if ($eventId !== null) {
            $must[] = ['key' => 'event_id', 'match' => ['value' => $eventId]];
        }

        $response = $this->request()->post($this->url("/collections/{$this->collection()}/points/search"), [
            'vector' => $vector,
            'limit' => $limit,
            'filter' => ['must' => $must],
        ])->throw();

        $matches = [];
        /** @var array<int, array<string, mixed>> $rows */
        $rows = is_array($response->json('result')) ? $response->json('result') : [];

        foreach ($rows as $row) {
            $matches[] = ['chunk_id' => (int) ($row['id'] ?? 0), 'score' => (float) ($row['score'] ?? 0.0)];
        }

        return $matches;
    }

    private function request(): PendingRequest
    {
        return Http::withHeaders(['api-key' => (string) ($this->config['api_key'] ?? '')])->timeout(30);
    }

    private function url(string $path): string
    {
        return rtrim((string) ($this->config['url'] ?? ''), '/').$path;
    }

    private function collection(): string
    {
        return (string) ($this->config['collection'] ?? 'content');
    }
}
