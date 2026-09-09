<?php

declare(strict_types=1);

namespace App\Domain\Gamification\Models;

use App\Domain\Gamification\Enums\PointsAction;
use App\Domain\Registration\Models\Attendee;
use App\Domain\Shared\Concerns\BelongsToTenant;
use App\Domain\Shared\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An append-only gamification award. Immutable; unique per
 * (attendee, action, subject) so an action is counted once.
 *
 * @property int $id
 * @property string $ulid
 * @property int $tenant_id
 * @property int $event_id
 * @property int $attendee_id
 * @property PointsAction $action
 * @property string|null $subject
 * @property int $points
 * @property Carbon|null $created_at
 */
class PointsAward extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'event_id',
        'attendee_id',
        'action',
        'subject',
        'points',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action' => PointsAction::class,
            'points' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Attendee, $this>
     */
    public function attendee(): BelongsTo
    {
        return $this->belongsTo(Attendee::class);
    }
}
