<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Studio;

use App\Application\Studio\Actions\RedeemGuestLinkAction;
use App\Http\Concerns\FormatsAccessToken;
use App\Http\Controllers\Controller;
use App\Http\Requests\Studio\RedeemGuestLinkRequest;
use App\Http\Resources\StudioParticipantResource;
use App\Http\Resources\StudioSessionResource;
use Illuminate\Http\JsonResponse;

/**
 * Public endpoint: a guest redeems a link to join a studio. Unauthenticated —
 * the link token is the credential. Tenant context is derived from the link
 * inside the action (never from the request).
 */
class GuestJoinController extends Controller
{
    use FormatsAccessToken;

    public function __invoke(RedeemGuestLinkRequest $request, RedeemGuestLinkAction $redeem, string $token): JsonResponse
    {
        $result = $redeem->execute($token, (string) $request->validated('name'));

        return response()->json([
            'data' => [
                'participant' => StudioParticipantResource::make($result['participant']),
                'session' => StudioSessionResource::make($result['session']),
                'access' => $this->accessTokenArray($result['token']),
            ],
        ], JsonResponse::HTTP_CREATED);
    }
}
