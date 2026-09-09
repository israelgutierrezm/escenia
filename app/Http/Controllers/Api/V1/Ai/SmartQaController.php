<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Ai;

use App\Application\Ai\AskContentAction;
use App\Http\Concerns\ResolvesEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ai\AskContentRequest;
use Illuminate\Http\JsonResponse;

/**
 * Smart Q&A: ask a question, answered from the event's transcripts (RAG).
 */
class SmartQaController extends Controller
{
    use ResolvesEvent;

    public function ask(AskContentRequest $request, AskContentAction $action, string $event): JsonResponse
    {
        $model = $this->resolveEvent($event);

        $this->authorize('viewContent', $model);

        $result = $action->execute($model, (string) $request->validated('question'));

        return response()->json([
            'data' => [
                'answer' => $result['answer'],
                'model' => $result['model'],
                'citations' => array_map(static fn ($chunk): array => [
                    'id' => $chunk->ulid,
                    'start_ms' => $chunk->start_ms,
                    'end_ms' => $chunk->end_ms,
                    'text' => $chunk->text,
                ], $result['citations']),
            ],
        ]);
    }
}
