<?php

declare(strict_types=1);

namespace App\Application\Engagement\DTOs;

final class CreatePollData
{
    /**
     * @param  list<string>  $options
     */
    public function __construct(
        public readonly string $question,
        public readonly array $options,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        /** @var list<string> $options */
        $options = array_values(array_map(
            static fn (mixed $option): string => (string) $option,
            is_array($data['options'] ?? null) ? $data['options'] : [],
        ));

        return new self(
            question: (string) $data['question'],
            options: $options,
        );
    }
}
