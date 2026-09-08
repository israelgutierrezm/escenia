<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Models;

use App\Domain\Identity\Models\User;
use App\Domain\Shared\Concerns\HasPublicId;
use App\Domain\Tenancy\Enums\MembershipStatus;
use App\Domain\Tenancy\Enums\TenantRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $ulid
 * @property int $tenant_id
 * @property int $user_id
 * @property TenantRole $role
 * @property MembershipStatus $status
 * @property Carbon|null $invited_at
 * @property Carbon|null $joined_at
 */
class TenantMembership extends Model
{
    use HasPublicId;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'user_id',
        'role',
        'status',
        'invited_at',
        'joined_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => TenantRole::class,
            'status' => MembershipStatus::class,
            'invited_at' => 'datetime',
            'joined_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
