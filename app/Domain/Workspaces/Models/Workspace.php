<?php

declare(strict_types=1);

namespace App\Domain\Workspaces\Models;

use App\Domain\Shared\Concerns\BelongsToTenant;
use App\Domain\Shared\Concerns\HasPublicId;
use App\Domain\Tenancy\Models\Tenant;
use Database\Factories\WorkspaceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Operational grouping inside a tenant. Tenant-owned: every query is
 * automatically constrained to the resolved tenant by {@see BelongsToTenant}.
 *
 * @property int $id
 * @property string $ulid
 * @property int $tenant_id
 * @property string $name
 * @property string $slug
 * @property string $status
 * @property array<string, mixed>|null $settings
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Workspace extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<WorkspaceFactory> */
    use HasFactory;

    use HasPublicId;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'status',
        'settings',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }

    /**
     * @return HasMany<WorkspaceMembership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(WorkspaceMembership::class);
    }

    protected static function newFactory(): WorkspaceFactory
    {
        return WorkspaceFactory::new();
    }
}
