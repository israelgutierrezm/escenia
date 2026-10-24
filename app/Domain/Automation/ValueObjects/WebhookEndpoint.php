<?php

declare(strict_types=1);

namespace App\Domain\Automation\ValueObjects;

use Carbon\CarbonImmutable;
use SensitiveParameter;

/**
 * Where a webhook step posts to and how it signs. Both the URL and the secret
 * are credentials: many receivers (Slack, Discord, Zapier…) authenticate by the
 * URL alone, and the secret signs the payload. They live encrypted in the
 * step's `credentials` and the API only ever shows the origin (ADR-037).
 *
 * After a rotation the previous secret keeps signing for a grace period, so the
 * receiver can be updated without dropping deliveries (ADR-038). The stored
 * shape stays flat (`url`, `secret`, …) so an older instance still reads it; a
 * secret without the `legacy: false` marker was saved before ADR-038.
 */
final class WebhookEndpoint
{
    public const PREVIOUS_SECRET_GRACE_SECONDS = 86_400;

    public function __construct(
        #[SensitiveParameter] public readonly string $url,
        public readonly ?WebhookSecret $secret = null,
        public readonly ?WebhookSecret $previousSecret = null,
        public readonly ?int $previousExpiresAt = null,
    ) {}

    /**
     * @param  array<string, mixed>  $values  `url`, an optional `secret` and the rotation fields
     */
    public static function fromArray(array $values): ?self
    {
        $url = is_string($values['url'] ?? null) ? trim($values['url']) : '';

        if ($url === '') {
            return null;
        }

        $previous = self::secretFrom($values['previous_secret'] ?? null, $values['previous_legacy'] ?? null);
        $expiresAt = $values['previous_expires_at'] ?? null;

        return new self(
            $url,
            self::secretFrom($values['secret'] ?? null, $values['legacy'] ?? null),
            $previous,
            $previous !== null && is_int($expiresAt) ? $expiresAt : null,
        );
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
     * The secrets that sign `webhook-signature` at `$now`: the current one, then
     * the previous one while its grace lasts.
     *
     * @return list<WebhookSecret>
     */
    public function signingSecrets(int $now): array
    {
        return array_values(array_filter(
            $this->activeSecrets($now),
            static fn (WebhookSecret $secret): bool => $secret->signsStandard(),
        ));
    }

    /**
     * The pre-ADR-038 secret that still signs `X-Escenia-Signature`, if any.
     */
    public function legacySecret(int $now): ?WebhookSecret
    {
        foreach ($this->activeSecrets($now) as $secret) {
            if (! $secret->signsStandard()) {
                return $secret;
            }
        }

        return null;
    }

    public function previousSecretExpiresAt(int $now): ?CarbonImmutable
    {
        return $this->previousIsActive($now) && $this->previousExpiresAt !== null
            ? CarbonImmutable::createFromTimestamp($this->previousExpiresAt)
            : null;
    }

    /**
     * Swaps in a new secret; the current one keeps signing for the grace period.
     * Rotating again within the grace drops the oldest at once (two keys at most).
     */
    public function withRotatedSecret(WebhookSecret $secret, int $now): self
    {
        return new self(
            $this->url,
            $secret,
            $this->secret,
            $this->secret !== null ? $now + self::PREVIOUS_SECRET_GRACE_SECONDS : null,
        );
    }

    /**
     * @return array<string, string|int|bool>
     */
    public function toArray(): array
    {
        $values = ['url' => $this->url];

        if ($this->secret !== null) {
            $values['secret'] = $this->secret->value;
            $values['legacy'] = $this->secret->legacy;
        }

        if ($this->previousSecret !== null && $this->previousExpiresAt !== null) {
            $values['previous_secret'] = $this->previousSecret->value;
            $values['previous_legacy'] = $this->previousSecret->legacy;
            $values['previous_expires_at'] = $this->previousExpiresAt;
        }

        return $values;
    }

    /**
     * @return list<WebhookSecret>
     */
    private function activeSecrets(int $now): array
    {
        $secrets = $this->secret !== null ? [$this->secret] : [];

        if ($this->previousSecret !== null && $this->previousIsActive($now)) {
            $secrets[] = $this->previousSecret;
        }

        return $secrets;
    }

    private function previousIsActive(int $now): bool
    {
        return $this->previousExpiresAt !== null && $now < $this->previousExpiresAt;
    }

    private static function secretFrom(mixed $value, mixed $legacy): ?WebhookSecret
    {
        // No marker: the secret was saved before ADR-038.
        return is_string($value) && $value !== '' ? new WebhookSecret($value, $legacy !== false) : null;
    }
}
