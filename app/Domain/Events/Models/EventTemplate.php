<?php

declare(strict_types=1);

namespace App\Domain\Events\Models;

use App\Domain\Events\Enums\EventType;
use App\Domain\Shared\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * An event template. A null tenant_id is a system template visible to everyone;
 * a set tenant_id is tenant-owned. Not blanket tenant-scoped (system + own), so
 * queries use {@see visibleTo()} instead of the global tenant scope.
 *
 * @property int $id
 * @property string $ulid
 * @property int|null $tenant_id
 * @property string $name
 * @property EventType $type
 * @property string|null $description
 * @property list<string>|null $default_capabilities
 * @property array<string, mixed>|null $default_settings
 * @property bool $is_system
 */
class EventTemplate extends Model
{
    use HasPublicId;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'name',
        'type',
        'description',
        'default_capabilities',
        'default_settings',
        'is_system',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => EventType::class,
            'default_capabilities' => 'array',
            'default_settings' => 'array',
            'is_system' => 'boolean',
        ];
    }

    /**
     * Scope to the templates a tenant may use: its own plus system templates.
     *
     * @param  Builder<EventTemplate>  $query
     * @return Builder<EventTemplate>
     */
    public function scopeVisibleTo(Builder $query, int $tenantId): Builder
    {
        return $query->where(function (Builder $inner) use ($tenantId): void {
            $inner->whereNull('tenant_id')->orWhere('tenant_id', $tenantId);
        });
    }

    /**
     * @return list<string>
     */
    public function defaultCapabilities(): array
    {
        return $this->default_capabilities ?? [];
    }
}
