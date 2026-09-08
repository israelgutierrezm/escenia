<?php

declare(strict_types=1);

namespace App\Application\Registration\DTOs;

final class RegisterAttendeeData
{
    /**
     * @param  array<string, mixed>  $answers
     */
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly array $answers = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        /** @var array<string, mixed> $answers */
        $answers = is_array($data['answers'] ?? null) ? $data['answers'] : [];

        return new self(
            name: (string) $data['name'],
            email: (string) $data['email'],
            answers: $answers,
        );
    }
}
