<?php

declare(strict_types=1);

namespace App\Domain\Engagement\Models;

use App\Domain\Shared\Concerns\BelongsToTenant;
use App\Domain\Shared\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A selectable option on a poll. `votes_count` is the denormalized tally.
 *
 * @property int $id
 * @property string $ulid
 * @property int $tenant_id
 * @property int $poll_id
 * @property string $label
 * @property int $position
 * @property int $votes_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class PollOption extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'poll_id',
        'label',
        'position',
        'votes_count',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'votes_count' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Poll, $this>
     */
    public function poll(): BelongsTo
    {
        return $this->belongsTo(Poll::class);
    }
}
