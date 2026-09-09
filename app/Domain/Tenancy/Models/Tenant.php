<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Models;

use App\Domain\Billing\Models\Plan;
use App\Domain\Enterprise\Enums\DataRegion;
use App\Domain\Identity\Models\User;
use App\Domain\Shared\Concerns\HasPublicId;
use App\Domain\Tenancy\Enums\TenantStatus;
use App\Domain\Workspaces\Models\Workspace;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * The root of the tenancy hierarchy. Owns workspaces and reaches users through
 * memberships. Tenants themselves are NOT tenant-scoped (they are the scope).
 *
 * @property int $id
 * @property string $ulid
 * @property string $name
 * @property string $slug
 * @property TenantStatus $status
 * @property DataRegion $data_region
 * @property bool $is_dedicated
 * @property int|null $plan_id
 * @property array<string, mixed>|null $settings
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Pivot $pivot Present when loaded through a membership pivot.
 */
class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory;

    use HasPublicId;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'status',
        'data_region',
        'is_dedicated',
        'plan_id',
        'settings',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TenantStatus::class,
            'data_region' => DataRegion::class,
            'is_dedicated' => 'boolean',
            'settings' => 'array',
        ];
    }

    /**
     * @return HasMany<TenantMembership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(TenantMembership::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tenant_memberships')
            ->withPivot(['role', 'status'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<Workspace, $this>
     */
    public function workspaces(): HasMany
    {
        return $this->hasMany(Workspace::class);
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    protected static function newFactory(): TenantFactory
    {
        return TenantFactory::new();
    }
}
