<?php

declare(strict_types=1);

namespace App\Application\Production\DTOs;

final class CreateBrandKitData
{
    /**
     * @param  array<string, mixed>|null  $tokens
     */
    public function __construct(
        public readonly string $name,
        public readonly ?array $tokens = null,
        public readonly bool $isDefault = false,
    ) {}
}
