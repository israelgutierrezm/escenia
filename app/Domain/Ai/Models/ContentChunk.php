<?php

declare(strict_types=1);

namespace App\Domain\Ai\Models;

use App\Domain\Content\Models\Recording;
use App\Domain\Shared\Concerns\BelongsToTenant;
use App\Domain\Shared\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An embedded transcript chunk — the searchable unit for semantic replay and RAG
 * (ADR-027).
 *
 * @property int $id
 * @property string $ulid
 * @property int $tenant_id
 * @property int $event_id
 * @property int $recording_id
 * @property int $transcript_id
 * @property int $position
 * @property int $start_ms
 * @property int $end_ms
 * @property string $text
 * @property array<int, float> $vector
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ContentChunk extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'event_id',
        'recording_id',
        'transcript_id',
        'position',
        'start_ms',
        'end_ms',
        'text',
        'vector',
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
            'vector' => 'array',
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
