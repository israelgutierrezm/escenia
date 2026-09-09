<?php

declare(strict_types=1);

namespace App\Domain\Content\Models;

use App\Domain\Shared\Concerns\BelongsToTenant;
use App\Domain\Shared\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A time-coded segment of a transcript.
 *
 * @property int $id
 * @property string $ulid
 * @property int $tenant_id
 * @property int $transcript_id
 * @property int $position
 * @property int $start_ms
 * @property int $end_ms
 * @property string|null $speaker
 * @property string $text
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class TranscriptSegment extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'transcript_id',
        'position',
        'start_ms',
        'end_ms',
        'speaker',
        'text',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'start_ms' => 'integer',
            'end_ms' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Transcript, $this>
     */
    public function transcript(): BelongsTo
    {
        return $this->belongsTo(Transcript::class);
    }
}
