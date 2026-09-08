<?php

declare(strict_types=1);

namespace App\Application\Events\Actions;

use App\Application\Events\DTOs\CreateEventData;
use App\Domain\Audit\Contracts\AuditLogger;
use App\Domain\Billing\Contracts\EntitlementResolver;
use App\Domain\Events\Enums\Capability;
use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Enums\EventType;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventCapability;
use App\Domain\Events\Models\EventTemplate;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Workspaces\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates an event in Draft state. When a template is supplied, its default
 * capabilities are applied — but only those the tenant's plan entitles
 * (ADR-013 / ADR-016).
 */
final class CreateEventAction
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly EntitlementResolver $entitlements,
    ) {}

    public function execute(Tenant $tenant, Workspace $workspace, User $actor, CreateEventData $data): Event
    {
        return DB::transaction(function () use ($tenant, $workspace, $actor, $data): Event {
            $template = $this->resolveTemplate($data->templateId, $tenant->getKey());

            $type = $data->type ?? EventType::Webinar;
            if ($data->type === null && $template !== null) {
                $type = $template->type;
            }

            $event = Event::create([
                'tenant_id' => $tenant->getKey(),
                'workspace_id' => $workspace->getKey(),
                'template_id' => $template?->getKey(),
                'created_by' => $actor->getKey(),
                'type' => $type,
                'status' => EventStatus::Draft,
                'title' => $data->title,
                'slug' => $this->uniqueSlug($workspace, $data->slug ?? $data->title),
                'description' => $data->description,
                'timezone' => $data->timezone ?? 'UTC',
                'scheduled_start_at' => $data->scheduledStartAt,
                'scheduled_end_at' => $data->scheduledEndAt,
            ]);

            foreach ($template?->defaultCapabilities() ?? [] as $capabilityValue) {
                $capability = Capability::tryFrom($capabilityValue);

                if ($capability !== null && $this->entitlements->allows($tenant, $capability->value)) {
                    EventCapability::create([
                        'tenant_id' => $tenant->getKey(),
                        'event_id' => $event->getKey(),
                        'capability' => $capability,
                        'enabled' => true,
                    ]);
                }
            }

            $this->audit->log('event.created', actor: $actor, tenant: $tenant, auditable: $event, context: [
                'type' => $type->value,
                'slug' => $event->slug,
                'from_template' => $template?->ulid,
            ]);

            return $event;
        });
    }

    private function resolveTemplate(?string $ulid, int $tenantId): ?EventTemplate
    {
        if ($ulid === null) {
            return null;
        }

        return EventTemplate::query()
            ->visibleTo($tenantId)
            ->where('ulid', $ulid)
            ->first();
    }

    private function uniqueSlug(Workspace $workspace, string $value): string
    {
        $base = Str::slug($value) ?: 'event';
        $slug = $base;
        $suffix = 1;

        while (
            Event::query()
                ->where('workspace_id', $workspace->getKey())
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = $base.'-'.(++$suffix);
        }

        return $slug;
    }
}
