<?php

declare(strict_types=1);

namespace App\Domain\Identity\Models;

use App\Domain\Shared\Concerns\HasPublicId;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Models\TenantMembership;
use App\Domain\Workspaces\Models\WorkspaceMembership;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * A global identity. A user is shared across tenants (never duplicated per
 * tenant) and reaches a tenant only through a {@see TenantMembership}.
 *
 * @property int $id
 * @property string $ulid
 * @property string $name
 * @property string $email
 * @property string|null $timezone
 * @property string|null $locale
 * @property Carbon|null $email_verified_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class User extends Authenticatable
{
    use HasApiTokens;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasPublicId;
    use HasRoles;
    use Notifiable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'timezone',
        'locale',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Default attribute values. Guarantees the optional i18n columns are always
     * present on freshly instantiated models (so strict attribute access holds).
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'timezone' => null,
        'locale' => null,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * @return HasMany<TenantMembership, $this>
     */
    public function tenantMemberships(): HasMany
    {
        return $this->hasMany(TenantMembership::class);
    }

    /**
     * @return HasMany<WorkspaceMembership, $this>
     */
    public function workspaceMemberships(): HasMany
    {
        return $this->hasMany(WorkspaceMembership::class);
    }

    /**
     * @return BelongsToMany<Tenant, $this>
     */
    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'tenant_memberships')
            ->withPivot(['role', 'status'])
            ->withTimestamps();
    }

    public function tenantMembershipFor(Tenant $tenant): ?TenantMembership
    {
        return $this->tenantMemberships()
            ->where('tenant_id', $tenant->getKey())
            ->first();
    }

    public function belongsToTenant(Tenant $tenant): bool
    {
        return $this->tenantMemberships()
            ->where('tenant_id', $tenant->getKey())
            ->exists();
    }

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }
}
