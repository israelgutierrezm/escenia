<?php

declare(strict_types=1);

namespace App\Domain\Education\Contracts;

use App\Domain\Education\ValueObjects\CertificateData;

/**
 * Renders a certificate to PDF bytes. The concrete engine (dompdf) is bound in
 * infrastructure so the domain never depends on it — swappable like the media,
 * payments and storage providers.
 */
interface CertificateRenderer
{
    /**
     * @return string raw PDF bytes
     */
    public function render(CertificateData $data): string;
}
