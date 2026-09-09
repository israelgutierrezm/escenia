<?php

declare(strict_types=1);

namespace App\Infrastructure\Ai;

use App\Domain\Ai\Contracts\EmbeddingProvider;

/**
 * Deterministic, network-free embeddings for dev and tests. Hashes each word
 * into a fixed-dimension bag-of-words vector and L2-normalizes it, so texts that
 * share words are genuinely close under cosine similarity — semantic search
 * behaves for real without an external model.
 */
final class FakeEmbeddingProvider implements EmbeddingProvider
{
    private const DIMENSIONS = 64;

    /**
     * @param  list<string>  $texts
     * @return list<list<float>>
     */
    public function embed(array $texts): array
    {
        return array_map(fn (string $text): array => $this->vectorFor($text), $texts);
    }

    public function dimensions(): int
    {
        return self::DIMENSIONS;
    }

    public function name(): string
    {
        return 'fake';
    }

    /**
     * @return list<float>
     */
    private function vectorFor(string $text): array
    {
        $vector = array_fill(0, self::DIMENSIONS, 0.0);

        preg_match_all('/[a-z0-9]+/', strtolower($text), $matches);

        foreach ($matches[0] as $word) {
            $bucket = (int) (hexdec(substr(md5($word), 0, 8)) % self::DIMENSIONS);
            $vector[$bucket] += 1.0;
        }

        $norm = sqrt(array_sum(array_map(static fn (float $v): float => $v * $v, $vector)));

        if ($norm > 0.0) {
            $vector = array_map(static fn (float $v): float => $v / $norm, $vector);
        }

        return array_values($vector);
    }
}
