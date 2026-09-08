<?php

declare(strict_types=1);

namespace App\Application\Commerce\DTOs;

final class CheckoutData
{
    /**
     * @param  list<array{ticket: string, quantity: int}>  $items
     */
    public function __construct(
        public readonly string $buyerName,
        public readonly string $buyerEmail,
        public readonly array $items,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        /** @var list<array{ticket: string, quantity: int}> $items */
        $items = array_values(array_map(
            static fn (mixed $item): array => [
                'ticket' => (string) (is_array($item) ? ($item['ticket'] ?? '') : ''),
                'quantity' => (int) (is_array($item) ? ($item['quantity'] ?? 0) : 0),
            ],
            is_array($data['items'] ?? null) ? $data['items'] : [],
        ));

        return new self(
            buyerName: (string) $data['buyer_name'],
            buyerEmail: (string) $data['buyer_email'],
            items: $items,
        );
    }
}
