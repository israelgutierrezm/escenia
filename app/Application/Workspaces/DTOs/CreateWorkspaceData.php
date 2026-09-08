<?php

declare(strict_types=1);

namespace App\Application\Workspaces\DTOs;

final class CreateWorkspaceData
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $slug = null,
    ) {}

    /**
     * @param  array{name: string, slug?: string|null}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            slug: $data['slug'] ?? null,
        );
    }
}
