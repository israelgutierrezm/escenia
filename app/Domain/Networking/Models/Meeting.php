<?php

declare(strict_types=1);

namespace App\Domain\Networking\Models;

use App\Domain\Events\Models\Event;
use App\Domain\Networking\Enums\MeetingStatus;
use App\Domain\Registration\Models\Attendee;
use App\Domain\Shared\Concerns\BelongsToTenant;
use App\Domain\Shared\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A 1:1 meeting proposed by one attendee to another, with a proposed time and
 * duration. Guarded by MeetingStatus.
 *
 * @property int $id
 * @property string $ulid
 * @property int $tenant_id
 * @property int $event_id
 * @property int $proposer_id
 * @property int $invitee_id
 * @property MeetingStatus $status
 * @property Carbon $scheduled_at
 * @property int $duration_minutes
 * @property string|null $topic
 * @property int|null $canceled_by
 * @property Carbon|null $responded_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Meeting extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'event_id',
        'proposer_id',
        'invitee_id',
        'status',
        'scheduled_at',
        'duration_minutes',
        'topic',
        'canceled_by',
        'responded_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => MeetingStatus::class,
            'scheduled_at' => 'datetime',
            'responded_at' => 'datetime',
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
     * @return BelongsTo<Attendee, $this>
     */
    public function proposer(): BelongsTo
    {
        return $this->belongsTo(Attendee::class, 'proposer_id');
    }

    /**
     * @return BelongsTo<Attendee, $this>
     */
    public function invitee(): BelongsTo
    {
        return $this->belongsTo(Attendee::class, 'invitee_id');
    }
}
