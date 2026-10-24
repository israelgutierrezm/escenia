<?php

declare(strict_types=1);

namespace App\Application\Automation\Actions;

use App\Application\Automation\DTOs\IssuedWebhookSecret;
use App\Domain\Audit\Contracts\AuditLogger;
use App\Domain\Automation\Models\AutomationStep;
use App\Domain\Automation\ValueObjects\WebhookSecret;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Issues a new signing secret for a webhook step (ADR-038). The previous one
 * keeps signing for a grace period so the receiver can be updated without
 * dropping deliveries; on a step that had none, this creates the first. It is
 * also how a pre-ADR-038 step moves to the Standard Webhooks signature.
 */
final class RotateWebhookSecretAction
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    public function execute(User $actor, AutomationStep $step): IssuedWebhookSecret
    {
        return DB::transaction(function () use ($actor, $step): IssuedWebhookSecret {
            $locked = AutomationStep::query()->whereKey($step->getKey())->lockForUpdate()->firstOrFail();
            $endpoint = $locked->webhookEndpoint();

            if ($endpoint === null) {
                throw ValidationException::withMessages(['step' => 'Only webhook steps have a signing secret.']);
            }

            $now = now()->getTimestamp();
            $secret = WebhookSecret::generate();
            $rotated = $endpoint->withRotatedSecret($secret, $now);

            $locked->forceFill(['config' => null, 'credentials' => $rotated->toArray()])->save();

            $this->audit->log('automation.webhook_secret_rotated', actor: $actor, tenant: $locked->tenant, auditable: $locked->automation, context: [
                'step' => $locked->ulid,
                'position' => $locked->position,
                'from_legacy' => $endpoint->secret->legacy ?? false,
                'previous_valid_until' => $rotated->previousSecretExpiresAt($now)?->toIso8601String(),
            ]);

            return new IssuedWebhookSecret($locked, $secret->value);
        });
    }
}
