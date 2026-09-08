<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Workspaces\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Workspace>
 */
class WorkspaceFactory extends Factory
{
    protected $model = Workspace::class;

    public function definition(): array
    {
        $name = (string) fake()->unique()->words(2, true);

        return [
            // Creates an owning tenant by default; override with ->for($tenant)
            // or ->state(['tenant_id' => $tenant->id]) in tests.
            'tenant_id' => Tenant::factory(),
            'name' => Str::ucfirst($name),
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'status' => 'active',
            'settings' => null,
        ];
    }
}
