<?php

declare(strict_types=1);

namespace App\Domain\Shared\Contracts;

/**
 * Generates a QR code as a data: URI. The concrete library stays in
 * infrastructure so callers (and the domain) never depend on it.
 */
interface QrCodeGenerator
{
    /**
     * @return string a data URI, e.g. `data:image/png;base64,...`
     */
    public function dataUri(string $text, int $size = 220): string;
}
