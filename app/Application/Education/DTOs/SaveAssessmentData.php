<?php

declare(strict_types=1);

namespace App\Application\Education\DTOs;

final class SaveAssessmentData
{
    /**
     * @param  list<array{prompt: string, type: string, points: int, options: list<array<string, mixed>>}>  $questions
     */
    public function __construct(
        public readonly string $title,
        public readonly int $passingScore,
        public readonly bool $isPublished,
        public readonly array $questions,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $questions = [];

        foreach (is_array($data['questions'] ?? null) ? $data['questions'] : [] as $question) {
            if (! is_array($question)) {
                continue;
            }

            $questions[] = [
                'prompt' => (string) ($question['prompt'] ?? ''),
                'type' => (string) ($question['type'] ?? 'single_choice'),
                'points' => (int) ($question['points'] ?? 1),
                'options' => array_values(is_array($question['options'] ?? null) ? $question['options'] : []),
            ];
        }

        return new self(
            title: (string) $data['title'],
            passingScore: (int) ($data['passing_score'] ?? 70),
            isPublished: (bool) ($data['is_published'] ?? false),
            questions: $questions,
        );
    }
}
