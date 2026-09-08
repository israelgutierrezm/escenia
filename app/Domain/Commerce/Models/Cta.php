<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Models;

use App\Domain\Events\Models\Event;
use App\Domain\Identity\Models\User;
use App\Domain\Shared\Concerns\BelongsToTenant;
use App\Domain\Shared\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An in-event call-to-action. May link to a ticket (a buy CTA / offer) or an
 * external URL. Active within an optional window; clicks are tracked.
 *
 * @property int $id
 * @property string $ulid
 * @property int $tenant_id
 * @property int $event_id
 * @property int|null $ticket_id
 * @property string $title
 * @property string|null $body
 * @property string|null $url
 * @property bool $is_active
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property int $clicks_count
 * @property int $position
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Cta extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'event_id',
        'ticket_id',
        'title',
        'body',
        'url',
        'is_active',
        'starts_at',
        'ends_at',
        'clicks_count',
        'position',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'clicks_count' => 'integer',
            'position' => 'integer',
        ];
    }

    public function isLive(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $now = now();

        if ($this->starts_at !== null && $this->starts_at->isAfter($now)) {
            return false;
        }

        if ($this->ends_at !== null && $this->ends_at->isBefore($now)) {
            return false;
        }

        return true;
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @return BelongsTo<Ticket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
