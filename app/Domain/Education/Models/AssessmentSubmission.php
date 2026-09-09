<?php

declare(strict_types=1);

namespace App\Domain\Education\Models;

use App\Domain\Registration\Models\Attendee;
use App\Domain\Shared\Concerns\BelongsToTenant;
use App\Domain\Shared\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An attendee's completed assessment.
 *
 * @property int $id
 * @property string $ulid
 * @property int $tenant_id
 * @property int $assessment_id
 * @property int $attendee_id
 * @property array<string, mixed> $answers
 * @property int $score
 * @property bool $passed
 * @property Carbon $submitted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class AssessmentSubmission extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'assessment_id',
        'attendee_id',
        'answers',
        'score',
        'passed',
        'submitted_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'answers' => 'array',
            'score' => 'integer',
            'passed' => 'boolean',
            'submitted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Assessment, $this>
     */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    /**
     * @return BelongsTo<Attendee, $this>
     */
    public function attendee(): BelongsTo
    {
        return $this->belongsTo(Attendee::class);
    }
}
