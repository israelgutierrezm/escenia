<?php

declare(strict_types=1);

namespace App\Domain\Education\Models;

use App\Domain\Events\Models\Event;
use App\Domain\Shared\Concerns\BelongsToTenant;
use App\Domain\Shared\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The completion rule for an event: minimum watched time and/or passing the
 * published assessment.
 *
 * @property int $id
 * @property string $ulid
 * @property int $tenant_id
 * @property int $event_id
 * @property int|null $min_watch_seconds
 * @property bool $require_assessment
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CompletionRule extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'event_id',
        'min_watch_seconds',
        'require_assessment',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'min_watch_seconds' => 'integer',
            'require_assessment' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
