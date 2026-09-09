<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Content;

use App\Application\Content\Actions\RequestTranscriptionAction;
use App\Domain\Content\Models\Recording;
use App\Domain\Content\Models\Transcript;
use App\Http\Controllers\Controller;
use App\Http\Requests\Content\RequestTranscriptionRequest;
use App\Http\Resources\TranscriptResource;
use Illuminate\Http\JsonResponse;

class TranscriptController extends Controller
{
    public function store(RequestTranscriptionRequest $request, RequestTranscriptionAction $action, string $recording): JsonResponse
    {
        $model = Recording::query()->where('ulid', $recording)->firstOrFail();

        $this->authorize('manageContent', $model->event()->firstOrFail());

        $language = $request->validated('language');
        $transcript = $action->execute($model, $request->user(), $language !== null ? (string) $language : 'en');

        return TranscriptResource::make($transcript)
            ->response()
            ->setStatusCode(JsonResponse::HTTP_ACCEPTED);
    }

    public function show(string $transcript): TranscriptResource
    {
        $model = Transcript::query()->where('ulid', $transcript)->with('segments')->firstOrFail();

        $this->authorize('viewContent', $model->recording()->firstOrFail()->event()->firstOrFail());

        return TranscriptResource::make($model);
    }
}
