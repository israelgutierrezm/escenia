<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Education\Host;

use App\Domain\Education\Models\Assessment;
use App\Domain\Education\Models\AssessmentSubmission;
use App\Http\Concerns\ResolvesEvent;
use App\Http\Controllers\Controller;
use App\Http\Resources\AssessmentSubmissionResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SubmissionController extends Controller
{
    use ResolvesEvent;

    public function index(string $event): AnonymousResourceCollection
    {
        $model = $this->resolveEvent($event);

        $this->authorize('viewEducation', $model);

        $assessmentIds = Assessment::query()->where('event_id', $model->getKey())->pluck('id');

        $submissions = AssessmentSubmission::query()
            ->whereIn('assessment_id', $assessmentIds)
            ->with('attendee')
            ->latest('id')
            ->paginate(50);

        return AssessmentSubmissionResource::collection($submissions);
    }
}
