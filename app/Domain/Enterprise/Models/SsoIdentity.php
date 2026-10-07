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
 * Which local user an IdP subject is, for one SSO connection (ADR-035). Logins
 * resolve by subject first; a user is linked to at most one subject per
 * connection, so an email reassigned at the IdP (a new subject) never inherits
 * the previous holder's account.
 *
 * @property int $id
 * @property string $ulid
 * @property int $tenant_id
 * @property int $sso_connection_id
 * @property int $user_id
 * @property string $subject
 * @property Carbon|null $last_login_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class SsoIdentity extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'sso_connection_id',
        'user_id',
        'subject',
        'last_login_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
