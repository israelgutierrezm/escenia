<?php

declare(strict_types=1);

namespace App\Infrastructure\Ai;

use App\Domain\Ai\Contracts\AiCompletionProvider;
use App\Domain\Ai\ValueObjects\CompletionResult;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Anthropic Messages API adapter. Uses raw HTTP (Laravel's client) to match the
 * codebase convention for external adapters — the SDK never enters the domain.
 * Not integration-tested against the API (see technical-debt); the fake provider
 * is the default in dev/tests. Defaults to Claude Opus 5.
 */
final class ClaudeCompletionProvider implements AiCompletionProvider
{
    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';

    private const VERSION = '2023-06-01';

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private readonly array $config,
    ) {}

    public function complete(string $prompt, ?string $system = null): CompletionResult
    {
        $apiKey = (string) ($this->config['api_key'] ?? '');
        $model = (string) ($this->config['model'] ?? 'claude-opus-5');

        if ($apiKey === '') {
            throw new RuntimeException('Anthropic API key is not configured.');
        }

        $payload = [
            'model' => $model,
            'max_tokens' => (int) ($this->config['max_tokens'] ?? 4096),
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
        ];

        if ($system !== null && $system !== '') {
            $payload['system'] = $system;
        }

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => self::VERSION,
            'content-type' => 'application/json',
        ])->timeout(120)->post(self::ENDPOINT, $payload)->throw();

        // The response content is a list of blocks; concatenate the text blocks.
        $text = '';
        /** @var array<int, array<string, mixed>> $blocks */
        $blocks = is_array($response->json('content')) ? $response->json('content') : [];

        foreach ($blocks as $block) {
            if (($block['type'] ?? null) === 'text') {
                $text .= (string) ($block['text'] ?? '');
            }
        }

        return new CompletionResult(
            text: $text,
            model: (string) ($response->json('model') ?? $model),
        );
    }

    public function name(): string
    {
        return 'claude';
    }
}
