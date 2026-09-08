<?php

declare(strict_types=1);

namespace App\Application\Broadcasting\DTOs;

use App\Domain\Broadcasting\Enums\DestinationProtocol;

final class CreateStreamDestinationData
{
    public function __construct(
        public readonly string $name,
        public readonly DestinationProtocol $protocol,
        public readonly string $url,
        public readonly string $streamKey,
    ) {}
}
