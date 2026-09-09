<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Education\Host;

use App\Application\Education\Actions\SaveAssessmentAction;
use App\Application\Education\DTOs\SaveAssessmentData;
use App\Domain\Education\Models\Assessment;
use App\Http\Concerns\ResolvesEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Education\SaveAssessmentRequest;
use App\Http\Resources\AssessmentResource;
use Illuminate\Http\JsonResponse;

class AssessmentController extends Controller
{
    use ResolvesEvent;

    public function show(string $event): JsonResponse
    {
        $model = $this->resolveEvent($event);

        $this->authorize('viewEducation', $model);

        $assessment = Assessment::query()->where('event_id', $model->getKey())->with('questions')->first();

        return response()->json([
            'data' => $assessment !== null ? AssessmentResource::make($assessment) : null,
        ]);
    }

    public function save(SaveAssessmentRequest $request, SaveAssessmentAction $action, string $event): JsonResponse
    {
        $model = $this->resolveEvent($event);

        $this->authorize('manageEducation', $model);

        $assessment = $action->execute($model, $request->user(), SaveAssessmentData::fromArray($request->validated()));

        // PUT upsert: always 200.
        return AssessmentResource::make($assessment)
            ->response()
            ->setStatusCode(JsonResponse::HTTP_OK);
    }
}
