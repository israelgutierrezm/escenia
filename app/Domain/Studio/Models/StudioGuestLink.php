<?php

declare(strict_types=1);

namespace App\Domain\Studio\Models;

use App\Domain\Identity\Models\User;
use App\Domain\Shared\Concerns\BelongsToTenant;
use App\Domain\Shared\Concerns\HasPublicId;
use App\Domain\Studio\Enums\ParticipantRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $ulid
 * @property int $tenant_id
 * @property int $studio_id
 * @property int|null $created_by
 * @property string $token_hash
 * @property string $name
 * @property ParticipantRole $role
 * @property Carbon|null $expires_at
 * @property bool $single_use
 * @property int|null $max_uses
 * @property int $uses
 * @property Carbon|null $revoked_at
 */
class StudioGuestLink extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'studio_id',
        'created_by',
        'token_hash',
        'name',
        'role',
        'expires_at',
        'single_use',
        'max_uses',
        'uses',
        'revoked_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'token_hash',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => ParticipantRole::class,
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'single_use' => 'boolean',
            'max_uses' => 'integer',
            'uses' => 'integer',
        ];
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function hasUsesLeft(): bool
    {
        if ($this->single_use) {
            return $this->uses < 1;
        }

        return $this->max_uses === null || $this->uses < $this->max_uses;
    }

    public function isRedeemable(): bool
    {
        return ! $this->isRevoked() && ! $this->isExpired() && $this->hasUsesLeft();
    }

    /**
     * @return BelongsTo<Studio, $this>
     */
    public function studio(): BelongsTo
    {
        return $this->belongsTo(Studio::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
