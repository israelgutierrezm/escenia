<?php

declare(strict_types=1);

namespace App\Domain\Enterprise\Support;

use Illuminate\Support\Str;

/**
 * Generates and hashes programmatic API keys. The raw key is returned once to
 * the caller; only its SHA-256 hash is ever persisted. The short prefix is kept
 * in clear so a key can be identified in a list and the hash lookup narrowed.
 */
final class ApiKeyToken
{
    private function __construct(
        public readonly string $raw,
        public readonly string $prefix,
        public readonly string $hash,
    ) {}

    public static function generate(): self
    {
        $raw = 'esk_'.Str::lower(Str::random(40));

        return new self(
            raw: $raw,
            prefix: substr($raw, 0, 12),
            hash: self::hash($raw),
        );
    }

    public static function hash(string $raw): string
    {
        return hash('sha256', $raw);
    }
}
