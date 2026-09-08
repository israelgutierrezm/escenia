<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Foundation seeds only the data the platform requires to function.
        $this->call([
            PlanSeeder::class,
            RolePermissionSeeder::class,
            EventTemplateSeeder::class,
        ]);
    }
}
