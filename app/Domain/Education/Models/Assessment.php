<?php

declare(strict_types=1);

namespace App\Domain\Education\Models;

use App\Domain\Events\Models\Event;
use App\Domain\Shared\Concerns\BelongsToTenant;
use App\Domain\Shared\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A graded assessment for an event (ADR-028).
 *
 * @property int $id
 * @property string $ulid
 * @property int $tenant_id
 * @property int $event_id
 * @property string $title
 * @property int $passing_score
 * @property bool $is_published
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Assessment extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'event_id',
        'title',
        'passing_score',
        'is_published',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'passing_score' => 'integer',
            'is_published' => 'boolean',
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
     * @return HasMany<AssessmentQuestion, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(AssessmentQuestion::class)->orderBy('position');
    }

    /**
     * @return HasMany<AssessmentSubmission, $this>
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(AssessmentSubmission::class);
    }
}
