<?php

declare(strict_types=1);

namespace App\Domain\Studio\Models;

use App\Domain\Identity\Models\User;
use App\Domain\Shared\Concerns\BelongsToTenant;
use App\Domain\Shared\Concerns\HasPublicId;
use App\Domain\Studio\Enums\ParticipantRole;
use App\Domain\Studio\Enums\ParticipantStage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $ulid
 * @property int $tenant_id
 * @property int $studio_session_id
 * @property int|null $user_id
 * @property int|null $guest_link_id
 * @property string $identity
 * @property string $name
 * @property ParticipantRole $role
 * @property ParticipantStage $stage
 * @property bool $device_checked
 * @property Carbon|null $joined_at
 * @property Carbon|null $left_at
 */
class StudioParticipant extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'studio_session_id',
        'user_id',
        'guest_link_id',
        'identity',
        'name',
        'role',
        'stage',
        'device_checked',
        'joined_at',
        'left_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => ParticipantRole::class,
            'stage' => ParticipantStage::class,
            'device_checked' => 'boolean',
            'joined_at' => 'datetime',
            'left_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<StudioSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(StudioSession::class, 'studio_session_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<StudioGuestLink, $this>
     */
    public function guestLink(): BelongsTo
    {
        return $this->belongsTo(StudioGuestLink::class, 'guest_link_id');
    }
}
