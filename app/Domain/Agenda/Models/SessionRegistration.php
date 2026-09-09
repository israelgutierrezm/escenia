<?php

declare(strict_types=1);

namespace App\Domain\Agenda\Models;

use App\Domain\Events\Models\EventSession;
use App\Domain\Registration\Models\Attendee;
use App\Domain\Shared\Concerns\BelongsToTenant;
use App\Domain\Shared\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An attendee's registration for a session (their personal agenda). Unique per
 * (session, attendee).
 *
 * @property int $id
 * @property string $ulid
 * @property int $tenant_id
 * @property int $event_session_id
 * @property int $attendee_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class SessionRegistration extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'event_session_id',
        'attendee_id',
    ];

    /**
     * @return BelongsTo<EventSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(EventSession::class, 'event_session_id');
    }

    /**
     * @return BelongsTo<Attendee, $this>
     */
    public function attendee(): BelongsTo
    {
        return $this->belongsTo(Attendee::class);
    }
}
