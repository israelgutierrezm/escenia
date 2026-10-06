<?php

declare(strict_types=1);

namespace App\Application\Production\Actions;

use App\Domain\Audit\Contracts\AuditLogger;
use App\Domain\Identity\Models\User;
use App\Domain\Production\Models\BrandKit;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

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

        // The extension comes from the sniffed content type (validated upstream),
        // never from the client's file name: a real PNG named `logo.html` stays `.png`.
        $mime = (string) $file->getMimeType();
        $extension = BrandKit::LOGO_MIME_EXTENSIONS[$mime]
            ?? throw new InvalidArgumentException("Unsupported logo type [{$mime}].");
        $path = "brand-kits/{$kit->ulid}/logo.{$extension}";
        $previous = $kit->logo_path;

        // Write first, repoint the kit, then drop the old file: the kit never
        // references a file that is already gone.
        $disk->put($path, (string) $file->getContent());

        $kit->forceFill([
            'logo_path' => $path,
            'logo_mime' => $mime,
        ])->save();

        if ($previous !== null && $previous !== $path) {
            $disk->delete($previous);
        }

        $this->audit->log('brand_kit.logo_uploaded', actor: $actor, tenant: $kit->tenant, auditable: $kit);

        return $kit;
    }
}
