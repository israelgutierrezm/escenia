<?php

declare(strict_types=1);

namespace App\Domain\Education\Support;

use App\Domain\Education\Models\Assessment;

/**
 * Scores an assessment submission server-side. A question earns its points only
 * when the attendee's selected option keys exactly match the correct keys. The
 * score is a percentage of total points; `passed` compares it to the pass mark.
 */
final class AssessmentScorer
{
    /**
     * @param  array<string, mixed>  $answers  question ULID → selected key or list of keys
     * @return array{score: int, passed: bool}
     */
    public function score(Assessment $assessment, array $answers): array
    {
        $questions = $assessment->questions()->get();

        if ($questions->isEmpty()) {
            return ['score' => 0, 'passed' => false];
        }

        $totalPoints = 0;
        $earned = 0;

        foreach ($questions as $question) {
            $totalPoints += $question->points;

            $given = $answers[$question->ulid] ?? [];
            /** @var list<string> $givenKeys */
            $givenKeys = array_map(
                static fn ($key): string => (string) $key,
                is_array($given) ? array_values($given) : [$given],
            );
            sort($givenKeys);

            $correctKeys = $question->correctKeys();

            if ($correctKeys !== [] && $givenKeys === $correctKeys) {
                $earned += $question->points;
            }
        }

        $score = $totalPoints > 0 ? (int) round($earned / $totalPoints * 100) : 0;

        return ['score' => $score, 'passed' => $score >= $assessment->passing_score];
    }
}
