<?php

declare(strict_types=1);

namespace App\Application\Education\Actions;

use App\Domain\Education\Contracts\CertificateRenderer;
use App\Domain\Education\Models\Certificate;
use App\Domain\Education\ValueObjects\CertificateData;
use App\Domain\Events\Models\Event;
use App\Domain\Production\Models\BrandKit;

/**
 * Assembles a certificate's presentation data (event title, brand accent, verify
 * URL, localized date) and renders it to PDF bytes via the bound renderer.
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

        $data = new CertificateData(
            recipientName: $certificate->recipient_name,
            eventTitle: $event->title,
            code: $certificate->code,
            issuedOnLabel: $certificate->issued_at->locale('es')->isoFormat('LL'),
            accentColor: $this->accentColor($event),
            verifyUrl: $this->verifyUrl($certificate->code),
        );

        return $this->renderer->render($data);
    }

    private function accentColor(?Event $event): string
    {
        if ($event === null) {
            return self::DEFAULT_ACCENT;
        }

        $brand = BrandKit::query()
            ->withoutGlobalScopes()
            ->where('workspace_id', $event->workspace_id)
            ->where('is_default', true)
            ->first();

        $tokens = is_array($brand?->tokens) ? $brand->tokens : [];
        $color = $tokens['primary'] ?? $tokens['accent'] ?? null;

        return is_string($color) && preg_match('/^#[0-9a-fA-F]{6}$/', $color) === 1
            ? $color
            : self::DEFAULT_ACCENT;
    }

    private function verifyUrl(string $code): ?string
    {
        $base = config('app.frontend_url');

        if (! is_string($base) || $base === '') {
            return null;
        }

        return rtrim($base, '/').'/verificar/'.$code;
    }
}
