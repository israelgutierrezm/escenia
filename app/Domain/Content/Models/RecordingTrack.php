<?php

declare(strict_types=1);

namespace App\Domain\Content\Models;

use App\Domain\Content\Enums\RecordingStatus;
use App\Domain\Content\Enums\TrackKind;
use App\Domain\Shared\Concerns\BelongsToTenant;
use App\Domain\Shared\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An isolated (ISO) track of a recording.
 *
 * @property int $id
 * @property string $ulid
 * @property int $tenant_id
 * @property int $recording_id
 * @property TrackKind $kind
 * @property string|null $label
 * @property RecordingStatus $status
 * @property string|null $disk
 * @property string|null $storage_key
 * @property int|null $size_bytes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class RecordingTrack extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'recording_id',
        'kind',
        'label',
        'status',
        'disk',
        'storage_key',
        'size_bytes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => TrackKind::class,
            'status' => RecordingStatus::class,
            'size_bytes' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Recording, $this>
     */
    public function recording(): BelongsTo
    {
        return $this->belongsTo(Recording::class);
    }
}
