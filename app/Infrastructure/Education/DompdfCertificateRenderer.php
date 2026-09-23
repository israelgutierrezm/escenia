<?php

declare(strict_types=1);

namespace App\Infrastructure\Education;

use App\Domain\Education\Contracts\CertificateRenderer;
use App\Domain\Education\ValueObjects\CertificateData;
use App\Domain\Shared\Contracts\QrCodeGenerator;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Renders the certificate Blade view to a landscape A4 PDF with dompdf, drawing
 * a QR of the verify URL. The dompdf/QR SDKs stay here, behind their contracts,
 * never in the domain.
 */
final class DompdfCertificateRenderer implements CertificateRenderer
{
    public function __construct(
        private readonly QrCodeGenerator $qr,
    ) {}

    public function render(CertificateData $data): string
    {
        $qrDataUri = $data->verifyUrl !== null && $data->verifyUrl !== ''
            ? $this->qr->dataUri($data->verifyUrl, 200)
            : null;

        return Pdf::loadView('certificates.default', [
            'recipientName' => $data->recipientName,
            'eventTitle' => $data->eventTitle,
            'code' => $data->code,
            'issuedOnLabel' => $data->issuedOnLabel,
            'accentColor' => $data->accentColor,
            'verifyUrl' => $data->verifyUrl,
            'logoDataUri' => $data->logoDataUri,
            'qrDataUri' => $qrDataUri,
        ])->setPaper('a4', 'landscape')->output();
    }
}
