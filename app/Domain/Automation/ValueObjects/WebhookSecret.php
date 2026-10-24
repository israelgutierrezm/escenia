<?php

declare(strict_types=1);

namespace App\Domain\Automation\ValueObjects;

use SensitiveParameter;

/**
 * The signing secret of a webhook step (ADR-038). Escenia issues them in the
 * Standard Webhooks format: `whsec_` + base64 of 32 random bytes, the HMAC key
 * being the decoded bytes. A step saved before ADR-038 may hold a free-form
 * secret chosen by the tenant: that one is `legacy` and only signs the old
 * `X-Escenia-Signature` header, until the secret is rotated.
 */
final class WebhookSecret
{
    public const PREFIX = 'whsec_';

    private const BYTES = 32;

    private const MIN_BYTES = 24;

    private const MAX_BYTES = 64;

    public function __construct(
        #[SensitiveParameter] public readonly string $value,
        public readonly bool $legacy = false,
    ) {}

    public static function generate(): self
    {
        return new self(self::PREFIX.base64_encode(random_bytes(self::BYTES)));
    }

    /**
     * `whsec_` + strict base64 of 24 to 64 bytes.
     */
    public static function isStandard(string $value): bool
    {
        if (! str_starts_with($value, self::PREFIX)) {
            return false;
        }

        $bytes = base64_decode(substr($value, strlen(self::PREFIX)), true);

        return $bytes !== false && strlen($bytes) >= self::MIN_BYTES && strlen($bytes) <= self::MAX_BYTES;
    }

    /**
     * Whether it signs the Standard Webhooks `webhook-signature` header.
     */
    public function signsStandard(): bool
    {
        return ! $this->legacy && self::isStandard($this->value);
    }

    /**
     * The HMAC key: the decoded bytes of a standard secret, the raw string of
     * a legacy one (as the pre-ADR-038 header was signed).
     */
    public function key(): string
    {
        return $this->signsStandard()
            ? (string) base64_decode(substr($this->value, strlen(self::PREFIX)), true)
            : $this->value;
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return ['value' => '***', 'legacy' => $this->legacy];
    }
}
