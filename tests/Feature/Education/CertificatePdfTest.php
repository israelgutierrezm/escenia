<?php

declare(strict_types=1);

use App\Domain\Education\Models\Certificate;
use App\Domain\Registration\Models\Attendee;
use App\Domain\Shared\Contracts\QrCodeGenerator;

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
