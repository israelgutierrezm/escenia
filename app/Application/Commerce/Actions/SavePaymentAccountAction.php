<?php

declare(strict_types=1);

namespace App\Application\Commerce\Actions;

use App\Application\Commerce\DTOs\SavePaymentAccountData;
use App\Domain\Audit\Contracts\AuditLogger;
use App\Domain\Commerce\Models\PaymentAccount;
use App\Domain\Identity\Models\User;

/**
 * Connects (or updates) a tenant's payment gateway. Credentials and the webhook
 * secret are encrypted by the model cast; they are never written to the audit
 * trail or logs.
 */
final class SavePaymentAccountAction
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    public function execute(User $actor, SavePaymentAccountData $data): PaymentAccount
    {
        $account = PaymentAccount::query()->updateOrCreate(
            ['gateway' => $data->gateway->value],
            [
                'display_name' => $data->displayName,
                'credentials' => $data->credentials,
                'webhook_secret' => $data->webhookSecret,
                'currency' => $data->currency,
                'is_active' => $data->isActive,
            ],
        );

        // Never log the credentials — only which gateway was connected.
        $this->audit->log('commerce.payment_account.saved', actor: $actor, tenant: $account->tenant, auditable: $account, context: [
            'gateway' => $data->gateway->value,
        ]);

        return $account;
    }
}
