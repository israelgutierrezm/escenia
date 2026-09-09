<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Ai;

use App\Application\Ai\Actions\RequestSummaryAction;
use App\Domain\Ai\Enums\SummaryKind;
use App\Domain\Ai\Models\ContentSummary;
use App\Domain\Content\Models\Recording;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ai\RequestSummaryRequest;
use App\Http\Resources\ContentSummaryResource;
use Illuminate\Http\JsonResponse;

/**
 * Content Factory: request and read AI artifacts (summary/chapters/highlights).
 */
class ContentSummaryController extends Controller
{
    public function store(RequestSummaryRequest $request, RequestSummaryAction $action, string $recording): JsonResponse
    {
        $model = Recording::query()->where('ulid', $recording)->firstOrFail();

        $this->authorize('manageContent', $model->event()->firstOrFail());

        $summary = $action->execute($model, $request->user(), SummaryKind::from((string) $request->validated('kind')));

        return ContentSummaryResource::make($summary)
            ->response()
            ->setStatusCode(JsonResponse::HTTP_ACCEPTED);
    }

    public function show(string $summary): ContentSummaryResource
    {
        $model = ContentSummary::query()->where('ulid', $summary)->firstOrFail();

        $this->authorize('viewContent', $model->recording()->firstOrFail()->event()->firstOrFail());

        return ContentSummaryResource::make($model);
    }
}
