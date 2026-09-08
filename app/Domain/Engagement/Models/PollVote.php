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
 * An attendee's vote on a poll. Unique per (poll, attendee); records which
 * option was chosen.
 *
 * @property int $id
 * @property string $ulid
 * @property int $tenant_id
 * @property int $poll_id
 * @property int $poll_option_id
 * @property int $attendee_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class PollVote extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'poll_id',
        'poll_option_id',
        'attendee_id',
    ];

    /**
     * @return BelongsTo<Poll, $this>
     */
    public function poll(): BelongsTo
    {
        return $this->belongsTo(Poll::class);
    }

    /**
     * @return BelongsTo<PollOption, $this>
     */
    public function option(): BelongsTo
    {
        return $this->belongsTo(PollOption::class, 'poll_option_id');
    }

    /**
     * @return BelongsTo<Attendee, $this>
     */
    public function attendee(): BelongsTo
    {
        return $this->belongsTo(Attendee::class);
    }
}
