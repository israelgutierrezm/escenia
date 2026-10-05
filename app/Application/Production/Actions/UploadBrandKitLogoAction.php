<?php

declare(strict_types=1);

namespace App\Application\Production\Actions;

use App\Domain\Audit\Contracts\AuditLogger;
use App\Domain\Identity\Models\User;
use App\Domain\Production\Models\BrandKit;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Stores an uploaded logo for a brand kit on the branding disk and records its
 * path + mime on the kit. Replaces any previous logo. The bytes are read back
 * server-side (data URI) for the certificate PDF and the admin preview.
 */
final class UploadBrandKitLogoAction
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    public function execute(BrandKit $kit, User $actor, UploadedFile $file): BrandKit
    {
        $disk = Storage::disk((string) config('branding.logo_disk'));

        if ($kit->logo_path !== null && $disk->exists($kit->logo_path)) {
            $disk->delete($kit->logo_path);
        }

        $extension = $file->getClientOriginalExtension() !== ''
            ? $file->getClientOriginalExtension()
            : (string) $file->extension();
        $path = "brand-kits/{$kit->ulid}/logo.".strtolower($extension);

        $disk->put($path, (string) $file->getContent());

        $kit->forceFill([
            'logo_path' => $path,
            'logo_mime' => (string) $file->getMimeType(),
        ])->save();

        $this->audit->log('brand_kit.logo_uploaded', actor: $actor, tenant: $kit->tenant, auditable: $kit);

        return $kit;
    }
}
