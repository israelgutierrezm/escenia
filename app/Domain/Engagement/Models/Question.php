<?php

declare(strict_types=1);

namespace App\Domain\Engagement\Models;

use App\Domain\Engagement\Enums\QuestionStatus;
use App\Domain\Events\Models\Event;
use App\Domain\Identity\Models\User;
use App\Domain\Registration\Models\Attendee;
use App\Domain\Shared\Concerns\BelongsToTenant;
use App\Domain\Shared\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A Q&A question asked by an attendee. Upvoted by attendees (denormalized
 * `votes_count`) and answered by a host.
 *
 * @property int $id
 * @property string $ulid
 * @property int $tenant_id
 * @property int $event_id
 * @property int|null $attendee_id
 * @property string $author_name
 * @property string $body
 * @property QuestionStatus $status
 * @property string|null $answer
 * @property int|null $answered_by
 * @property Carbon|null $answered_at
 * @property int $votes_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Question extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'event_id',
        'attendee_id',
        'author_name',
        'body',
        'status',
        'answer',
        'answered_by',
        'answered_at',
        'votes_count',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => QuestionStatus::class,
            'answered_at' => 'datetime',
            'votes_count' => 'integer',
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
    public function attendee(): BelongsTo
    {
        return $this->belongsTo(Attendee::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function answerer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'answered_by');
    }

    /**
     * @return HasMany<QuestionVote, $this>
     */
    public function votes(): HasMany
    {
        return $this->hasMany(QuestionVote::class);
    }
}
