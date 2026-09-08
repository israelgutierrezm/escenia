<?php

declare(strict_types=1);

namespace App\Application\Identity\DTOs;

use App\Application\Identity\Actions\RegisterUserAction;

/**
 * Immutable input for {@see RegisterUserAction}.
 */
final class RegisterUserData
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly string $password,
        public readonly string $tenantName,
    ) {}

    /**
     * @param  array{name: string, email: string, password: string, tenant_name: string}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            email: $data['email'],
            password: $data['password'],
            tenantName: $data['tenant_name'],
        );
    }
}
