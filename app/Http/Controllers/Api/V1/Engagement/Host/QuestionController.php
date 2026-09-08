<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Engagement\Host;

use App\Application\Engagement\Actions\AnswerQuestionAction;
use App\Domain\Engagement\Models\Question;
use App\Http\Concerns\ResolvesEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Engagement\AnswerQuestionRequest;
use App\Http\Resources\QuestionResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Host side of Q&A: read the queue (most-voted first) and post answers.
 */
class QuestionController extends Controller
{
    use ResolvesEvent;

    public function index(string $event): AnonymousResourceCollection
    {
        $model = $this->resolveEvent($event);

        $this->authorize('viewEngagement', $model);

        $questions = Question::query()
            ->where('event_id', $model->getKey())
            ->orderByDesc('votes_count')
            ->orderByDesc('id')
            ->paginate(50);

        return QuestionResource::collection($questions);
    }

    public function answer(AnswerQuestionRequest $request, AnswerQuestionAction $action, string $question): QuestionResource
    {
        $model = Question::query()->where('ulid', $question)->firstOrFail();

        $this->authorize('manageEngagement', $model->event()->firstOrFail());

        return QuestionResource::make(
            $action->execute($model, $request->user(), (string) $request->validated('answer'))
        );
    }
}
