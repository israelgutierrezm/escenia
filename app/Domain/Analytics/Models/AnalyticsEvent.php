<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Models;

use App\Domain\Analytics\Enums\AnalyticsEventName;
use App\Domain\Events\Models\Event;
use App\Domain\Registration\Models\Attendee;
use App\Domain\Shared\Concerns\BelongsToTenant;
use App\Domain\Shared\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A single append-only analytics record (ADR-005). Immutable: updated_at is
 * disabled and rows are never mutated after creation.
 *
 * @property int $id
 * @property string $ulid
 * @property int $tenant_id
 * @property int $event_id
 * @property int|null $attendee_id
 * @property AnalyticsEventName $name
 * @property int $version
 * @property array<string, mixed>|null $properties
 * @property Carbon $occurred_at
 * @property Carbon|null $created_at
 */
class AnalyticsEvent extends Model
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
        'name',
        'version',
        'properties',
        'occurred_at',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'name' => AnalyticsEventName::class,
            'version' => 'integer',
            'properties' => 'array',
            'occurred_at' => 'datetime',
            'created_at' => 'datetime',
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
}
