<?php

declare(strict_types=1);

namespace App\Domain\Education\Models;

use App\Domain\Events\Models\Event;
use App\Domain\Registration\Models\Attendee;
use App\Domain\Shared\Concerns\BelongsToTenant;
use App\Domain\Shared\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A certificate issued to an attendee. `code` is an opaque, publicly verifiable
 * identifier.
 *
 * @property int $id
 * @property string $ulid
 * @property int $tenant_id
 * @property int $event_id
 * @property int $attendee_id
 * @property string $code
 * @property string $recipient_name
 * @property Carbon $issued_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Certificate extends Model
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
        'code',
        'recipient_name',
        'issued_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
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
