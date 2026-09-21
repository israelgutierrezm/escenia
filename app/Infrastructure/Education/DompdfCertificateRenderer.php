<?php

declare(strict_types=1);

namespace App\Infrastructure\Education;

use App\Domain\Education\Contracts\CertificateRenderer;
use App\Domain\Education\ValueObjects\CertificateData;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Renders the certificate Blade view to a landscape A4 PDF with dompdf. The
 * dompdf SDK stays here, behind {@see CertificateRenderer}, never in the domain.
 */
final class DompdfCertificateRenderer implements CertificateRenderer
{
    public function render(CertificateData $data): string
    {
        return Pdf::loadView('certificates.default', [
            'recipientName' => $data->recipientName,
            'eventTitle' => $data->eventTitle,
            'code' => $data->code,
            'issuedOnLabel' => $data->issuedOnLabel,
            'accentColor' => $data->accentColor,
            'verifyUrl' => $data->verifyUrl,
        ])->setPaper('a4', 'landscape')->output();
    }
}
