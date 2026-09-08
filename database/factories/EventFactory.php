<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Events\Models\Event;
use App\Domain\Workspaces\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    protected $model = Event::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = Str::ucfirst((string) fake()->unique()->words(3, true));

        return [
            // tenant_id is derived from the (possibly overridden) workspace so
            // the event always belongs to the workspace's tenant.
            'workspace_id' => Workspace::factory(),
            'tenant_id' => fn (array $attributes): int => Workspace::withoutGlobalScopes()
                ->findOrFail($attributes['workspace_id'])->tenant_id,
            'type' => 'webinar',
            'status' => 'draft',
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(6)),
            'timezone' => 'UTC',
        ];
    }
}
