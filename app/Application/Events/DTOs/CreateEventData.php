<?php

declare(strict_types=1);

namespace App\Application\Events\DTOs;

use App\Domain\Events\Enums\EventType;

final class CreateEventData
{
    public function __construct(
        public readonly string $title,
        public readonly ?string $slug = null,
        public readonly ?EventType $type = null,
        public readonly ?string $description = null,
        public readonly ?string $timezone = null,
        public readonly ?string $scheduledStartAt = null,
        public readonly ?string $scheduledEndAt = null,
        public readonly ?string $templateId = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            title: (string) $data['title'],
            slug: isset($data['slug']) ? (string) $data['slug'] : null,
            type: isset($data['type']) ? EventType::from((string) $data['type']) : null,
            description: isset($data['description']) ? (string) $data['description'] : null,
            timezone: isset($data['timezone']) ? (string) $data['timezone'] : null,
            scheduledStartAt: isset($data['scheduled_start_at']) ? (string) $data['scheduled_start_at'] : null,
            scheduledEndAt: isset($data['scheduled_end_at']) ? (string) $data['scheduled_end_at'] : null,
            templateId: isset($data['template_id']) ? (string) $data['template_id'] : null,
        );
    }
}
