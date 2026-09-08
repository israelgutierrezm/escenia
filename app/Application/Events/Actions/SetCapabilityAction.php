<?php

declare(strict_types=1);

namespace App\Application\Events\Actions;

use App\Domain\Audit\Contracts\AuditLogger;
use App\Domain\Billing\Contracts\EntitlementResolver;
use App\Domain\Events\Enums\Capability;
use App\Domain\Events\Exceptions\CapabilityNotEntitledException;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventCapability;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Enables or disables a capability on an event. Enabling is gated by the
 * tenant's plan entitlements.
 */
final class SetCapabilityAction
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly EntitlementResolver $entitlements,
    ) {}

    /**
     * @param  array<string, mixed>|null  $settings
     */
    public function execute(
        Event $event,
        User $actor,
        Capability $capability,
        bool $enabled,
        ?array $settings = null,
    ): EventCapability {
        if ($enabled && ! $this->entitlements->allows($event->tenant, $capability->value)) {
            throw new CapabilityNotEntitledException($capability);
        }

        return DB::transaction(function () use ($event, $actor, $capability, $enabled, $settings): EventCapability {
            $record = EventCapability::updateOrCreate(
                ['event_id' => $event->getKey(), 'capability' => $capability->value],
                ['tenant_id' => $event->tenant_id, 'enabled' => $enabled, 'settings' => $settings],
            );

            $this->audit->log(
                'event.capability.'.($enabled ? 'enabled' : 'disabled'),
                actor: $actor,
                tenant: $event->tenant,
                auditable: $event,
                context: ['capability' => $capability->value],
            );

            return $record;
        });
    }
}
