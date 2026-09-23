<?php

declare(strict_types=1);

namespace App\Application\Education\Actions;

use App\Domain\Education\Contracts\CertificateRenderer;
use App\Domain\Education\Models\Certificate;
use App\Domain\Education\ValueObjects\CertificateData;
use App\Domain\Events\Models\Event;
use App\Domain\Production\Models\BrandKit;

/**
 * Assembles a certificate's presentation data (event title, brand accent + logo,
 * verify URL, localized date) and renders it to PDF bytes via the bound renderer.
 * The renderer draws a QR of the verify URL.
 */
final class RenderCertificatePdfAction
{
    private const DEFAULT_ACCENT = '#2ea6ff';

    public function __construct(
        private readonly CertificateRenderer $renderer,
    ) {}

    public function execute(Certificate $certificate): string
    {
        $event = Event::query()->withoutGlobalScopes()->whereKey($certificate->event_id)->first();
        $brand = $this->brandKit($event);

        $data = new CertificateData(
            recipientName: $certificate->recipient_name,
            eventTitle: $event->title,
            code: $certificate->code,
            issuedOnLabel: $certificate->issued_at->locale('es')->isoFormat('LL'),
            accentColor: $this->accentColor($brand),
            verifyUrl: $this->verifyUrl($certificate->code),
            logoDataUri: $this->logoDataUri($brand),
        );

        return $this->renderer->render($data);
    }

    private function brandKit(?Event $event): ?BrandKit
    {
        if ($event === null) {
            return null;
        }

        return BrandKit::query()
            ->withoutGlobalScopes()
            ->where('workspace_id', $event->workspace_id)
            ->where('is_default', true)
            ->first();
    }

    private function accentColor(?BrandKit $brand): string
    {
        $tokens = is_array($brand?->tokens) ? $brand->tokens : [];
        $color = $tokens['primary'] ?? $tokens['accent'] ?? null;

        return is_string($color) && preg_match('/^#[0-9a-fA-F]{6}$/', $color) === 1
            ? $color
            : self::DEFAULT_ACCENT;
    }

    /**
     * A tenant-supplied logo is embedded only when it is already a data URI —
     * never fetched from a remote URL (offline render + no SSRF).
     */
    private function logoDataUri(?BrandKit $brand): ?string
    {
        $tokens = is_array($brand?->tokens) ? $brand->tokens : [];
        $logo = $tokens['logo'] ?? null;

        return is_string($logo) && str_starts_with($logo, 'data:image/') ? $logo : null;
    }

    private function verifyUrl(string $code): string
    {
        $base = config('app.frontend_url') ?? config('app.url');
        $base = is_string($base) ? rtrim($base, '/') : '';

        return $base.'/verificar/'.$code;
    }
}
