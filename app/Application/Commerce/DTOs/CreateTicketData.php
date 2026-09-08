<?php

declare(strict_types=1);

namespace App\Application\Commerce\DTOs;

final class CreateTicketData
{
    public function __construct(
        public readonly string $name,
        public readonly int $amountMinor,
        public readonly string $currency,
        public readonly ?string $description = null,
        public readonly ?int $compareAtMinor = null,
        public readonly ?int $capacity = null,
        public readonly ?string $salesStartAt = null,
        public readonly ?string $salesEndAt = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: (string) $data['name'],
            amountMinor: (int) $data['amount_minor'],
            currency: strtoupper((string) $data['currency']),
            description: isset($data['description']) ? (string) $data['description'] : null,
            compareAtMinor: isset($data['compare_at_minor']) ? (int) $data['compare_at_minor'] : null,
            capacity: isset($data['capacity']) ? (int) $data['capacity'] : null,
            salesStartAt: isset($data['sales_start_at']) ? (string) $data['sales_start_at'] : null,
            salesEndAt: isset($data['sales_end_at']) ? (string) $data['sales_end_at'] : null,
        );
    }
}
