<?php

declare(strict_types=1);

namespace App\Domain\Networking\Models;

use App\Domain\Events\Models\Event;
use App\Domain\Networking\Enums\ConnectionStatus;
use App\Domain\Registration\Models\Attendee;
use App\Domain\Shared\Concerns\BelongsToTenant;
use App\Domain\Shared\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A connection between two attendees of an event, requested by one and accepted
 * or declined by the other. Unique per (event, requester, addressee).
 *
 * @property int $id
 * @property string $ulid
 * @property int $tenant_id
 * @property int $event_id
 * @property int $requester_id
 * @property int $addressee_id
 * @property ConnectionStatus $status
 * @property string|null $message
 * @property Carbon|null $responded_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Connection extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'event_id',
        'requester_id',
        'addressee_id',
        'status',
        'message',
        'responded_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ConnectionStatus::class,
            'responded_at' => 'datetime',
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
    public function requester(): BelongsTo
    {
        return $this->belongsTo(Attendee::class, 'requester_id');
    }

    /**
     * @return BelongsTo<Attendee, $this>
     */
    public function addressee(): BelongsTo
    {
        return $this->belongsTo(Attendee::class, 'addressee_id');
    }
}
