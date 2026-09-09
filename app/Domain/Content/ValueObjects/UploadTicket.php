<?php

declare(strict_types=1);

namespace App\Domain\Content\ValueObjects;

use Carbon\CarbonInterface;

/**
 * A pre-authorized direct-to-storage upload: the client PUTs/POSTs the binary to
 * `url` with `headers`, so the large file never passes through Laravel
 * (CLAUDE.md). `disk` + `key` are where it lands, echoed back on completion.
 */
final class UploadTicket
{
    /**
     * @param  array<string, string>  $headers
     */
    public function __construct(
        public readonly string $url,
        public readonly string $method,
        public readonly array $headers,
        public readonly string $disk,
        public readonly string $key,
        public readonly CarbonInterface $expiresAt,
    ) {}
}
