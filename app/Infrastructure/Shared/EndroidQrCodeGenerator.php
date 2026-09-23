<?php

declare(strict_types=1);

namespace App\Infrastructure\Shared;

use App\Domain\Shared\Contracts\QrCodeGenerator;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;

/**
 * QR codes via endroid/qr-code (PNG data URI, rendered offline with GD). Kept
 * behind {@see QrCodeGenerator} so the library never leaks past infrastructure.
 */
final class EndroidQrCodeGenerator implements QrCodeGenerator
{
    public function dataUri(string $text, int $size = 220): string
    {
        return (new Builder(
            writer: new PngWriter,
            data: $text,
            size: $size,
            margin: 8,
        ))->build()->getDataUri();
    }
}
