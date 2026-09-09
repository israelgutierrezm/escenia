<?php

declare(strict_types=1);

namespace App\Domain\Content\Models;

use App\Domain\Content\Enums\TranscriptStatus;
use App\Domain\Shared\Concerns\BelongsToTenant;
use App\Domain\Shared\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A transcript of a recording, produced by a Transcriber in a background job.
 *
 * @property int $id
 * @property string $ulid
 * @property int $tenant_id
 * @property int $recording_id
 * @property string $provider
 * @property string $language
 * @property TranscriptStatus $status
 * @property string|null $failed_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Transcript extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'recording_id',
        'provider',
        'language',
        'status',
        'failed_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TranscriptStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Recording, $this>
     */
    public function recording(): BelongsTo
    {
        return $this->belongsTo(Recording::class);
    }

    /**
     * @return HasMany<TranscriptSegment, $this>
     */
    public function segments(): HasMany
    {
        return $this->hasMany(TranscriptSegment::class)->orderBy('position');
    }
}
