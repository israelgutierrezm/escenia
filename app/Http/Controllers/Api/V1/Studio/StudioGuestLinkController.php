<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Studio;

use App\Application\Studio\Actions\CreateGuestLinkAction;
use App\Application\Studio\Actions\EnsureStudioAction;
use App\Application\Studio\DTOs\CreateGuestLinkData;
use App\Domain\Events\Models\Event;
use App\Domain\Studio\Enums\ParticipantRole;
use App\Domain\Studio\Models\Studio;
use App\Domain\Studio\Models\StudioGuestLink;
use App\Http\Controllers\Controller;
use App\Http\Requests\Studio\CreateGuestLinkRequest;
use App\Http\Resources\StudioGuestLinkResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class StudioGuestLinkController extends Controller
{
    public function index(string $event): AnonymousResourceCollection
    {
        $studio = Studio::query()->where('event_id', $this->resolveEvent($event)->getKey())->first();

        $this->authorize('manage', $studio ?? new Studio);

        $links = $studio !== null ? $studio->guestLinks()->latest()->get() : collect();

        return StudioGuestLinkResource::collection($links);
    }

    public function store(CreateGuestLinkRequest $request, EnsureStudioAction $ensure, CreateGuestLinkAction $create, string $event): JsonResponse
    {
        $studio = $ensure->execute($this->resolveEvent($event), $request->user());

        $this->authorize('manage', $studio);

        $result = $create->execute($studio, $request->user(), new CreateGuestLinkData(
            name: (string) $request->validated('name'),
            role: ParticipantRole::from((string) $request->validated('role')),
            expiresAt: $request->validated('expires_at') !== null ? (string) $request->validated('expires_at') : null,
            singleUse: (bool) $request->validated('single_use'),
            maxUses: $request->validated('max_uses') !== null ? (int) $request->validated('max_uses') : null,
        ));

        return response()->json([
            'data' => [
                'link' => StudioGuestLinkResource::make($result['link']),
                // Raw token is returned once, for the invite URL.
                'token' => $result['token'],
                'join_url' => url("/api/v1/studio/guest/{$result['token']}/join"),
            ],
        ], JsonResponse::HTTP_CREATED);
    }

    public function destroy(string $link): Response
    {
        $model = StudioGuestLink::query()->where('ulid', $link)->firstOrFail();

        $studio = Studio::query()->whereKey($model->studio_id)->firstOrFail();
        $this->authorize('manage', $studio);

        $model->update(['revoked_at' => now()]);

        return response()->noContent();
    }

    private function resolveEvent(string $ulid): Event
    {
        return Event::query()->where('ulid', $ulid)->firstOrFail();
    }
}
