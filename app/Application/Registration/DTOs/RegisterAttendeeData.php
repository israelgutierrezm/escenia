<?php

declare(strict_types=1);

namespace App\Application\Registration\DTOs;

final class RegisterAttendeeData
{
    /**
     * @param  array<string, mixed>  $answers
     * @param  array<string, string>  $attribution  utm_source/medium/campaign/term/content, referrer, landing_path
     */
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly array $answers = [],
        public readonly array $attribution = [],
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
            attribution: self::normalizeAttribution($data['attribution'] ?? null),
        );
    }

    /**
     * Keep only known attribution keys, as strings, so nothing arbitrary lands
     * in the analytics payload.
     *
     * @return array<string, string>
     */
    private static function normalizeAttribution(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $allowed = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'referrer', 'landing_path'];
        $attribution = [];

        foreach ($allowed as $key) {
            if (isset($raw[$key]) && is_scalar($raw[$key]) && (string) $raw[$key] !== '') {
                $attribution[$key] = (string) $raw[$key];
            }
        }

        return $attribution;
    }
}
