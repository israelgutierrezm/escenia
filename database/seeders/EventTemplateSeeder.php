<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Events\Enums\Capability;
use App\Domain\Events\Enums\EventType;
use App\Domain\Events\Models\EventTemplate;
use Illuminate\Database\Seeder;

/**
 * System event templates (tenant_id null) available to every tenant. They seed
 * an event's default capabilities; capabilities the tenant's plan does not
 * entitle are skipped at creation time.
 */
class EventTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'name' => 'Webinar',
                'type' => EventType::Webinar,
                'description' => 'Single-session webinar with registration and engagement.',
                'capabilities' => [Capability::Registration, Capability::Chat, Capability::Qa, Capability::Polls, Capability::Replay],
            ],
            [
                'name' => 'Live Studio',
                'type' => EventType::LiveStudio,
                'description' => 'Multi-destination live production.',
                'capabilities' => [Capability::Chat, Capability::Recording, Capability::Multistream],
            ],
            [
                'name' => 'Training',
                'type' => EventType::Training,
                'description' => 'Training session with assessment and certification.',
                'capabilities' => [Capability::Registration, Capability::Qa, Capability::Tests, Capability::Certificates, Capability::Replay],
            ],
            [
                'name' => 'Product Launch',
                'type' => EventType::ProductLaunch,
                'description' => 'Launch event with commerce and engagement.',
                'capabilities' => [Capability::Registration, Capability::Chat, Capability::Qa, Capability::Commerce, Capability::Replay],
            ],
        ];

        foreach ($templates as $template) {
            EventTemplate::updateOrCreate(
                ['name' => $template['name'], 'is_system' => true],
                [
                    'tenant_id' => null,
                    'type' => $template['type'],
                    'description' => $template['description'],
                    'default_capabilities' => array_map(
                        static fn (Capability $capability): string => $capability->value,
                        $template['capabilities'],
                    ),
                ],
            );
        }
    }
}
