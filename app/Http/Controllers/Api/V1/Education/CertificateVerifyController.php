<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Education;

use App\Domain\Education\Models\Certificate;
use App\Domain\Events\Models\Event;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Public certificate verification. The certificate is resolved unscoped by its
 * opaque code; only the recipient name, event title and issue date are exposed.
 */
class CertificateVerifyController extends Controller
{
    public function verify(string $code): JsonResponse
    {
        $certificate = Certificate::query()->withoutGlobalScopes()->where('code', $code)->first();

        if ($certificate === null) {
            return response()->json(['data' => ['valid' => false]], JsonResponse::HTTP_NOT_FOUND);
        }

        $event = Event::query()->withoutGlobalScopes()->whereKey($certificate->event_id)->first();

        return response()->json([
            'data' => [
                'valid' => true,
                'code' => $certificate->code,
                'recipient_name' => $certificate->recipient_name,
                'event_title' => $event?->title,
                'issued_at' => $certificate->issued_at->toIso8601String(),
            ],
        ]);
    }
}
