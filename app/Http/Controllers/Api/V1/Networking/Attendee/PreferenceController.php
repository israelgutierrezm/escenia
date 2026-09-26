<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Networking\Attendee;

use App\Domain\Registration\Context\AttendeeContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * This attendee's networking preference: whether they're discoverable in the
 * directory and reachable for connection/meeting requests (opt-in, privacy).
 */
class PreferenceController extends Controller
{
    public function __construct(
        private readonly AttendeeContext $context,
    ) {}

    public function show(): JsonResponse
    {
        return response()->json(['data' => ['opt_in' => $this->context->attendeeOrFail()->networking_opt_in]]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate(['opt_in' => ['required', 'boolean']]);

        $attendee = $this->context->attendeeOrFail();
        $attendee->forceFill(['networking_opt_in' => (bool) $validated['opt_in']])->save();

        return response()->json(['data' => ['opt_in' => $attendee->networking_opt_in]]);
    }
}
