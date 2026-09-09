<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Content;

use App\Application\Content\Actions\CompleteRecordingAction;
use App\Application\Content\Actions\RequestRecordingUploadAction;
use App\Domain\Content\Models\Recording;
use App\Http\Concerns\ResolvesEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Content\CompleteRecordingRequest;
use App\Http\Requests\Content\RequestRecordingUploadRequest;
use App\Http\Resources\RecordingResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RecordingController extends Controller
{
    use ResolvesEvent;

    public function index(string $event): AnonymousResourceCollection
    {
        $model = $this->resolveEvent($event);

        $this->authorize('viewContent', $model);

        $recordings = Recording::query()
            ->where('event_id', $model->getKey())
            ->with('tracks')
            ->latest('id')
            ->get();

        return RecordingResource::collection($recordings);
    }

    public function store(RequestRecordingUploadRequest $request, RequestRecordingUploadAction $action, string $event): JsonResponse
    {
        $model = $this->resolveEvent($event);

        $this->authorize('manageContent', $model);

        $title = $request->validated('title');
        $result = $action->execute($model, $request->user(), (string) $request->validated('content_type'), $title !== null ? (string) $title : null);
        $ticket = $result['ticket'];

        return response()->json([
            'data' => [
                'recording' => RecordingResource::make($result['recording']),
                'upload' => [
                    'url' => $ticket->url,
                    'method' => $ticket->method,
                    'headers' => $ticket->headers,
                    'key' => $ticket->key,
                    'expires_at' => $ticket->expiresAt->toIso8601String(),
                ],
            ],
        ], JsonResponse::HTTP_CREATED);
    }

    public function show(string $recording): RecordingResource
    {
        $model = Recording::query()
            ->where('ulid', $recording)
            ->with(['tracks', 'transcripts', 'clips'])
            ->firstOrFail();

        $this->authorize('viewContent', $model->event()->firstOrFail());

        return RecordingResource::make($model);
    }

    public function complete(CompleteRecordingRequest $request, CompleteRecordingAction $action, string $recording): RecordingResource
    {
        $model = Recording::query()->where('ulid', $recording)->firstOrFail();

        $this->authorize('manageContent', $model->event()->firstOrFail());

        $duration = $request->validated('duration_ms');
        $size = $request->validated('size_bytes');
        $format = $request->validated('format');

        return RecordingResource::make($action->execute(
            $model,
            $request->user(),
            $duration !== null ? (int) $duration : null,
            $size !== null ? (int) $size : null,
            $format !== null ? (string) $format : null,
        ));
    }
}
