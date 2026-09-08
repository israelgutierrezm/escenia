<?php

declare(strict_types=1);

namespace App\Domain\Engagement\Models;

use App\Domain\Registration\Models\Attendee;
use App\Domain\Shared\Concerns\BelongsToTenant;
use App\Domain\Shared\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An attendee's upvote on a question. Unique per (question, attendee); the
 * denormalized counter lives on the question.
 *
 * @property int $id
 * @property string $ulid
 * @property int $tenant_id
 * @property int $question_id
 * @property int $attendee_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class QuestionVote extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'question_id',
        'attendee_id',
    ];

    /**
     * @return BelongsTo<Question, $this>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    /**
     * @return BelongsTo<Attendee, $this>
     */
    public function attendee(): BelongsTo
    {
        return $this->belongsTo(Attendee::class);
    }
}
