<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Billing\Models\Plan;
use App\Domain\Shared\ValueObjects\Money;
use Illuminate\Database\Seeder;

/**
 * Seeds the plan catalog. Features and limits are data; nothing in the code
 * branches on the plan key (ADR-013).
 */
class PlanSeeder extends Seeder
{
    public function run(): void
    {
        Plan::updateOrCreate(
            ['key' => 'free'],
            [
                'name' => 'Free',
                'is_active' => true,
                // Feature keys double as event capability keys for entitlement
                // gating (see ADR-013 / ADR-016).
                'features' => ['workspaces', 'registration', 'chat', 'qa', 'polls', 'replay'],
                'limits' => ['max_workspaces' => 3, 'max_events' => 5],
                'price' => new Money(0, 'USD'),
            ],
        );

        Plan::updateOrCreate(
            ['key' => 'pro'],
            [
                'name' => 'Pro',
                'is_active' => true,
                'features' => [
                    'workspaces', 'registration', 'chat', 'qa', 'polls', 'replay',
                    'recording', 'multistream', 'certificates', 'networking',
                    'white_label', 'captions', 'translation', 'automation', 'commerce',
                ],
                'limits' => ['max_workspaces' => 50, 'max_events' => 500],
                'price' => new Money(4900, 'USD'),
            ],
        );
    }
}
