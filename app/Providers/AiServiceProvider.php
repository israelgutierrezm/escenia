<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Ai\Contracts\AiCompletionProvider;
use App\Domain\Ai\Contracts\EmbeddingProvider;
use App\Domain\Ai\Contracts\VectorIndex;
use App\Infrastructure\Ai\ClaudeCompletionProvider;
use App\Infrastructure\Ai\DatabaseVectorIndex;
use App\Infrastructure\Ai\FakeCompletionProvider;
use App\Infrastructure\Ai\FakeEmbeddingProvider;
use App\Infrastructure\Ai\HttpEmbeddingProvider;
use App\Infrastructure\Ai\QdrantVectorIndex;
use Illuminate\Support\ServiceProvider;

/**
 * Binds the AI providers selected by config/ai.php. Keeps the LLM/embeddings/
 * vector SDKs out of the domain (ADR-027), with network-free, deterministic
 * fakes by default so dev/tests need no external services.
 */
class AiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AiCompletionProvider::class, fn (): AiCompletionProvider => match (config('ai.completion')) {
            'claude' => new ClaudeCompletionProvider((array) config('ai.claude')),
            default => new FakeCompletionProvider,
        });

        $this->app->singleton(EmbeddingProvider::class, fn (): EmbeddingProvider => match (config('ai.embedding')) {
            'http' => new HttpEmbeddingProvider((array) config('ai.embeddings')),
            default => new FakeEmbeddingProvider,
        });

        $this->app->singleton(VectorIndex::class, fn (): VectorIndex => match (config('ai.vector')) {
            'qdrant' => new QdrantVectorIndex((array) config('ai.qdrant')),
            default => new DatabaseVectorIndex,
        });
    }
}
