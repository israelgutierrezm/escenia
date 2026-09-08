<?php

declare(strict_types=1);

namespace App\Application\Commerce\DTOs;

final class CreateCtaData
{
    public function __construct(
        public readonly string $title,
        public readonly ?string $body = null,
        public readonly ?string $url = null,
        public readonly ?string $ticket = null,
        public readonly ?string $startsAt = null,
        public readonly ?string $endsAt = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            title: (string) $data['title'],
            body: isset($data['body']) ? (string) $data['body'] : null,
            url: isset($data['url']) ? (string) $data['url'] : null,
            ticket: isset($data['ticket']) ? (string) $data['ticket'] : null,
            startsAt: isset($data['starts_at']) ? (string) $data['starts_at'] : null,
            endsAt: isset($data['ends_at']) ? (string) $data['ends_at'] : null,
        );
    }
}
