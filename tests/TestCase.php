<?php

declare(strict_types=1);

namespace Tests;

use Database\Seeders\PlanSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Seed the foundation data (plans + roles/permissions) after the
        // RefreshDatabase transaction has begun, so every feature test starts
        // from a functional platform baseline. Unit tests (no RefreshDatabase)
        // are skipped.
        if (in_array(RefreshDatabase::class, class_uses_recursive(static::class), true)) {
            $this->seed([
                PlanSeeder::class,
                RolePermissionSeeder::class,
            ]);
        }
    }
}
