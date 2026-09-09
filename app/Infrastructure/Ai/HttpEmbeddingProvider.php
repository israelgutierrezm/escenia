<?php

declare(strict_types=1);

namespace App\Infrastructure\Ai;

use App\Domain\Ai\Contracts\EmbeddingProvider;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * HTTP embeddings adapter (Voyage/OpenAI-style: POST {input, model} → {data:
 * [{embedding: [...]}]}). Not integration-tested (see technical-debt); the fake
 * provider is the default. The SDK never enters the domain.
 */
final class HttpEmbeddingProvider implements EmbeddingProvider
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private readonly array $config,
    ) {}

    /**
     * @param  list<string>  $texts
     * @return list<list<float>>
     */
    public function embed(array $texts): array
    {
        $endpoint = (string) ($this->config['endpoint'] ?? '');
        $apiKey = (string) ($this->config['api_key'] ?? '');

        if ($endpoint === '') {
            throw new RuntimeException('Embedding endpoint is not configured.');
        }

        $response = Http::withToken($apiKey)
            ->timeout(60)
            ->post($endpoint, [
                'model' => (string) ($this->config['model'] ?? ''),
                'input' => $texts,
            ])
            ->throw();

        $vectors = [];
        /** @var array<int, array<string, mixed>> $rows */
        $rows = is_array($response->json('data')) ? $response->json('data') : [];

        foreach ($rows as $row) {
            /** @var list<float> $embedding */
            $embedding = array_map(
                static fn ($v): float => (float) $v,
                is_array($row['embedding'] ?? null) ? $row['embedding'] : [],
            );
            $vectors[] = $embedding;
        }

        return $vectors;
    }

    public function dimensions(): int
    {
        return (int) ($this->config['dimensions'] ?? 1024);
    }

    public function name(): string
    {
        return 'http';
    }
}
