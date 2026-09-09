<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Ai;

use App\Application\Ai\SemanticSearch;
use App\Domain\Content\Models\Recording;
use App\Http\Concerns\ResolvesEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ai\SemanticSearchRequest;
use Illuminate\Http\JsonResponse;

/**
 * Semantic Replay: search an event's transcripts by meaning.
 */
class SemanticSearchController extends Controller
{
    use ResolvesEvent;

    public function search(SemanticSearchRequest $request, SemanticSearch $search, string $event): JsonResponse
    {
        $model = $this->resolveEvent($event);

        $this->authorize('viewContent', $model);

        $limit = $request->validated('limit');
        $hits = $search->search($model, (string) $request->validated('q'), $limit !== null ? (int) $limit : 8);

        $recordings = Recording::query()
            ->whereIn('id', array_map(static fn (array $hit): int => $hit['chunk']->recording_id, $hits))
            ->get()
            ->keyBy('id');

        $data = array_map(static fn (array $hit): array => [
            'id' => $hit['chunk']->ulid,
            'recording' => $recordings->get($hit['chunk']->recording_id)?->ulid,
            'start_ms' => $hit['chunk']->start_ms,
            'end_ms' => $hit['chunk']->end_ms,
            'text' => $hit['chunk']->text,
            'score' => round($hit['score'], 4),
        ], $hits);

        return response()->json(['data' => $data]);
    }
}
