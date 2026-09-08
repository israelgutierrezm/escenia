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
                'features' => ['workspaces'],
                'limits' => ['max_workspaces' => 3],
                'price' => new Money(0, 'USD'),
            ],
        );

        Plan::updateOrCreate(
            ['key' => 'pro'],
            [
                'name' => 'Pro',
                'is_active' => true,
                'features' => ['workspaces', 'white_label', 'recording'],
                'limits' => ['max_workspaces' => 50],
                'price' => new Money(4900, 'USD'),
            ],
        );
    }
}
