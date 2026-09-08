<?php

declare(strict_types=1);

namespace App\Application\Commerce\DTOs;

use App\Domain\Commerce\Enums\PaymentGatewayName;

final class SavePaymentAccountData
{
    /**
     * @param  array<string, string>  $credentials
     */
    public function __construct(
        public readonly PaymentGatewayName $gateway,
        public readonly string $displayName,
        public readonly array $credentials,
        public readonly string $currency,
        public readonly ?string $webhookSecret = null,
        public readonly bool $isActive = true,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        /** @var array<string, string> $credentials */
        $credentials = [];
        if (is_array($data['credentials'] ?? null)) {
            foreach ($data['credentials'] as $key => $value) {
                if (is_scalar($value)) {
                    $credentials[(string) $key] = (string) $value;
                }
            }
        }

        return new self(
            gateway: PaymentGatewayName::from((string) $data['gateway']),
            displayName: (string) $data['display_name'],
            credentials: $credentials,
            currency: strtoupper((string) ($data['currency'] ?? 'USD')),
            webhookSecret: isset($data['webhook_secret']) ? (string) $data['webhook_secret'] : null,
            isActive: (bool) ($data['is_active'] ?? true),
        );
    }
}
