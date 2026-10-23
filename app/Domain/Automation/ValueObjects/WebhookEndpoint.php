<?php

declare(strict_types=1);

namespace App\Domain\Automation\ValueObjects;

use SensitiveParameter;

/**
 * Where a webhook step posts to. Both parts are credentials: many receivers
 * (Slack, Discord, Zapier…) authenticate by the URL alone, and the secret signs
 * the payload. They live encrypted in the step's `credentials` and the API only
 * ever shows the origin (ADR-037).
 */
final class WebhookEndpoint
{
    public function __construct(
        #[SensitiveParameter] public readonly string $url,
        #[SensitiveParameter] public readonly ?string $secret = null,
    ) {}

    /**
     * @param  array<string, mixed>  $values  `url` and an optional `secret`
     */
    public static function fromArray(array $values): ?self
    {
        $url = is_string($values['url'] ?? null) ? trim($values['url']) : '';
        $secret = is_string($values['secret'] ?? null) ? $values['secret'] : '';

        return $url === '' ? null : new self($url, $secret === '' ? null : $secret);
    }

    public function isHttps(): bool
    {
        return strtolower((string) parse_url($this->url, PHP_URL_SCHEME)) === 'https';
    }

    public function host(): string
    {
        return strtolower((string) parse_url($this->url, PHP_URL_HOST));
    }

    /**
     * The part that is safe to show: scheme://host[:port], without credentials,
     * path or query.
     */
    public function origin(): string
    {
        $port = parse_url($this->url, PHP_URL_PORT);

        return strtolower((string) parse_url($this->url, PHP_URL_SCHEME)).'://'.$this->host()
            .(is_int($port) ? ':'.$port : '');
    }

    /**
     * @return array{url: string, secret?: string}
     */
    public function toArray(): array
    {
        return $this->secret === null
            ? ['url' => $this->url]
            : ['url' => $this->url, 'secret' => $this->secret];
    }
}
