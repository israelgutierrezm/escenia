<?php

declare(strict_types=1);

namespace App\Domain\Events\Models;

use App\Domain\Events\Enums\SpeakerRole;
use App\Domain\Identity\Models\User;
use App\Domain\Shared\Concerns\BelongsToTenant;
use App\Domain\Shared\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $ulid
 * @property int $tenant_id
 * @property int $event_id
 * @property int|null $user_id
 * @property string $name
 * @property string|null $email
 * @property string|null $headline
 * @property string|null $bio
 * @property string|null $avatar_url
 * @property SpeakerRole $role
 * @property int $position
 */
class EventSpeaker extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'event_id',
        'user_id',
        'name',
        'email',
        'headline',
        'bio',
        'avatar_url',
        'role',
        'position',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => SpeakerRole::class,
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
