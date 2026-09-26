<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Education;

use App\Application\Education\Actions\RenderCertificatePdfAction;
use App\Domain\Education\Models\Certificate;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
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

        // Prefer the pre-generated PDF; fall back to an on-demand render while the
        // generation job has not yet stored it (TD-031).
        $disk = Storage::disk((string) config('education.certificate_disk'));
        $pdf = $certificate->pdf_path !== null && $disk->exists($certificate->pdf_path)
            ? (string) $disk->get($certificate->pdf_path)
            : $action->execute($certificate);

        return response($pdf, Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="certificado-'.$certificate->code.'.pdf"',
        ]);
    }
}
