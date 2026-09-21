<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Education;

use App\Application\Education\Actions\RenderCertificatePdfAction;
use App\Domain\Education\Models\Certificate;
use App\Http\Controllers\Controller;
use Symfony\Component\HttpFoundation\Response;

/**
 * Public certificate PDF download. Resolved unscoped by the opaque code (the
 * shareable credential), like verification. Rendered on demand.
 */
class CertificateDownloadController extends Controller
{
    public function download(RenderCertificatePdfAction $action, string $code): Response
    {
        $certificate = Certificate::query()->withoutGlobalScopes()->where('code', $code)->firstOrFail();

        $pdf = $action->execute($certificate);

        return response($pdf, Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="certificado-'.$certificate->code.'.pdf"',
        ]);
    }
}
