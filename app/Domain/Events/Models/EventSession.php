<?php

declare(strict_types=1);

namespace App\Domain\Events\Models;

use App\Domain\Agenda\Models\Track;
use App\Domain\Events\Enums\SessionStatus;
use App\Domain\Shared\Concerns\BelongsToTenant;
use App\Domain\Shared\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $ulid
 * @property int $tenant_id
 * @property int $event_id
 * @property int|null $track_id
 * @property string $title
 * @property string|null $room
 * @property int|null $capacity
 * @property int $registered_count
 * @property SessionStatus $status
 * @property Carbon|null $scheduled_start_at
 * @property Carbon|null $scheduled_end_at
 * @property int $position
 * @property array<string, mixed>|null $settings
 */
class EventSession extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'event_id',
        'track_id',
        'title',
        'room',
        'capacity',
        'registered_count',
        'status',
        'scheduled_start_at',
        'scheduled_end_at',
        'position',
        'settings',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'track_id' => 'integer',
            'capacity' => 'integer',
            'registered_count' => 'integer',
            'status' => SessionStatus::class,
            'scheduled_start_at' => 'datetime',
            'scheduled_end_at' => 'datetime',
            'position' => 'integer',
            'settings' => 'array',
        ];
    }

    public function hasCapacityLeft(): bool
    {
        return $this->capacity === null || $this->registered_count < $this->capacity;
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @return BelongsTo<Track, $this>
     */
    public function track(): BelongsTo
    {
        return $this->belongsTo(Track::class);
    }
}
