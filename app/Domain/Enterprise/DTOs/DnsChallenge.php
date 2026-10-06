<?php

declare(strict_types=1);

namespace App\Domain\Enterprise\DTOs;

/**
 * A DNS TXT ownership challenge: the tenant publishes `value` at `name`. Shared
 * by custom domains and SSO email domains, so the {@see
 * \App\Domain\Enterprise\Contracts\DomainVerifier} never depends on either model.
 */
final class DnsChallenge
{
    public function __construct(
        public readonly string $name,
        public readonly string $value,
    ) {}

    /**
     * @return array{type: string, name: string, value: string}
     */
    public function toArray(): array
    {
        return ['type' => 'TXT', 'name' => $this->name, 'value' => $this->value];
    }
}
