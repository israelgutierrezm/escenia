<?php

declare(strict_types=1);

namespace App\Application\Education\Jobs;

use App\Application\Education\Actions\RenderCertificatePdfAction;
use App\Domain\Education\Models\Certificate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

/**
 * Pre-generates a certificate's PDF off the request thread (CLAUDE.md: heavy
 * work in jobs) and stores it on the certificate disk (TD-031), recording the
 * path on the certificate. Loaded unscoped so no tenant context is needed on the
 * worker. Idempotent: re-running overwrites the stored file.
 */
class GenerateCertificatePdfJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly int $certificateId,
    ) {}

    public function handle(RenderCertificatePdfAction $render): void
    {
        $certificate = Certificate::query()->withoutGlobalScopes()->find($this->certificateId);

        if ($certificate === null) {
            return;
        }

        $path = "certificates/{$certificate->ulid}.pdf";

        Storage::disk((string) config('education.certificate_disk'))
            ->put($path, $render->execute($certificate));

        $certificate->forceFill(['pdf_path' => $path])->save();
    }
}
