<?php

declare(strict_types=1);

namespace App\Domain\Content\Models;

use App\Domain\Content\Enums\RecordingSource;
use App\Domain\Content\Enums\RecordingStatus;
use App\Domain\Events\Models\Event;
use App\Domain\Shared\Concerns\BelongsToTenant;
use App\Domain\Shared\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A recording asset for an event. The binary lives in object storage (disk +
 * storage_key); Laravel never receives it (ADR-026).
 *
 * @property int $id
 * @property string $ulid
 * @property int $tenant_id
 * @property int $event_id
 * @property int|null $broadcast_session_id
 * @property RecordingSource $source
 * @property RecordingStatus $status
 * @property string|null $title
 * @property string|null $disk
 * @property string|null $storage_key
 * @property int|null $duration_ms
 * @property int|null $size_bytes
 * @property string|null $format
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Recording extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'event_id',
        'broadcast_session_id',
        'source',
        'status',
        'title',
        'disk',
        'storage_key',
        'duration_ms',
        'size_bytes',
        'format',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => RecordingSource::class,
            'status' => RecordingStatus::class,
            'duration_ms' => 'integer',
            'size_bytes' => 'integer',
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
     * @return HasMany<RecordingTrack, $this>
     */
    public function tracks(): HasMany
    {
        return $this->hasMany(RecordingTrack::class);
    }

    /**
     * @return HasMany<Transcript, $this>
     */
    public function transcripts(): HasMany
    {
        return $this->hasMany(Transcript::class);
    }

    /**
     * @return HasMany<Clip, $this>
     */
    public function clips(): HasMany
    {
        return $this->hasMany(Clip::class);
    }
}
