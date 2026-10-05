<?php

declare(strict_types=1);

use App\Application\Education\Actions\RenderCertificatePdfAction;
use App\Application\Education\Jobs\GenerateCertificatePdfJob;
use App\Domain\Education\Models\Certificate;
use App\Domain\Registration\Models\Attendee;
use App\Domain\Shared\Contracts\QrCodeGenerator;
use Illuminate\Support\Facades\Storage;

/**
 * @return array{0: Certificate}
 */
function makeCertificate(string $code): array
{
    [, $tenant, $event] = makeWebinarHost();
    registerAttendee($event->ulid, 'Ada Lovelace', 'ada@example.com');
    $attendee = Attendee::withoutGlobalScopes()->where('email', 'ada@example.com')->firstOrFail();

    return [Certificate::create([
        'tenant_id' => $tenant->id,
        'event_id' => $event->id,
        'attendee_id' => $attendee->id,
        'code' => $code,
        'recipient_name' => 'Ada Lovelace',
        'issued_at' => now(),
    ])];
}

it('renders a certificate as a downloadable PDF by code', function () {
    [, $tenant, $event] = makeWebinarHost();
    registerAttendee($event->ulid, 'Ada Lovelace', 'ada@example.com');
    $attendee = Attendee::withoutGlobalScopes()->where('email', 'ada@example.com')->firstOrFail();

    $certificate = Certificate::create([
        'tenant_id' => $tenant->id,
        'event_id' => $event->id,
        'attendee_id' => $attendee->id,
        'code' => 'CERT-ADA-1',
        'recipient_name' => 'Ada Lovelace',
        'issued_at' => now(),
    ]);

    $response = $this->get("/api/v1/certificates/{$certificate->code}/pdf")->assertOk();

    expect($response->headers->get('content-type'))->toContain('application/pdf')
        ->and($response->headers->get('content-disposition'))->toContain('certificado-CERT-ADA-1.pdf')
        ->and(substr((string) $response->getContent(), 0, 4))->toBe('%PDF');
});

it('returns 404 for an unknown certificate code', function () {
    $this->get('/api/v1/certificates/NOPE/pdf')->assertNotFound();
});

it('generates the verification QR as a PNG data URI', function () {
    $qr = app(QrCodeGenerator::class)
        ->dataUri('https://app.escenia.com/verificar/ABC123', 160);

    expect($qr)->toStartWith('data:image/png;base64,');
});

it('pre-generates and stores the certificate PDF on the certificate disk', function () {
    Storage::fake('local');
    [$certificate] = makeCertificate('CERT-JOB-1');

    (new GenerateCertificatePdfJob($certificate->id))->handle(app(RenderCertificatePdfAction::class));

    $certificate->refresh();
    $path = "certificates/{$certificate->ulid}.pdf";

    expect($certificate->pdf_path)->toBe($path);
    Storage::disk('local')->assertExists($path);
    expect(substr((string) Storage::disk('local')->get($path), 0, 4))->toBe('%PDF');
});

it('serves the pre-generated PDF from storage instead of re-rendering', function () {
    Storage::fake('local');
    [$certificate] = makeCertificate('CERT-STORED-1');

    $path = "certificates/{$certificate->ulid}.pdf";
    Storage::disk('local')->put($path, '%PDF-STORED-SENTINEL');
    $certificate->forceFill(['pdf_path' => $path])->save();

    $response = $this->get("/api/v1/certificates/{$certificate->code}/pdf")->assertOk();

    // The exact stored bytes are returned (not a fresh render).
    expect((string) $response->getContent())->toBe('%PDF-STORED-SENTINEL');
});
