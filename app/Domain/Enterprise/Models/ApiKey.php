<?php

declare(strict_types=1);

namespace App\Domain\Enterprise\Models;

use App\Domain\Identity\Models\User;
use App\Domain\Shared\Concerns\BelongsToTenant;
use App\Domain\Shared\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A tenant's programmatic API credential. The raw key is shown exactly once at
 * creation; only its SHA-256 hash lives in the database (token-credential
 * pattern). The hash is hidden from serialization and never logged.
 *
 * @property int $id
 * @property string $ulid
 * @property int $tenant_id
 * @property int|null $created_by
 * @property string $name
 * @property string $prefix
 * @property string $token_hash
 * @property list<string> $scopes
 * @property Carbon|null $last_used_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $revoked_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ApiKey extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    /**
     * The scopes a key may be granted. Kept as data here (not magic strings
     * scattered in code) so the catalog has one source of truth.
     *
     * @var list<string>
     */
    public const SCOPES = [
        'events.read',
        'events.write',
        'analytics.read',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'created_by',
        'name',
        'prefix',
        'token_hash',
        'scopes',
        'last_used_at',
        'expires_at',
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
            'scopes' => 'array',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * Usable = not revoked and not past its expiry.
     */
    public function isUsable(): bool
    {
        if ($this->revoked_at !== null) {
            return false;
        }

        return $this->expires_at === null || $this->expires_at->isFuture();
    }

    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->scopes, true);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
