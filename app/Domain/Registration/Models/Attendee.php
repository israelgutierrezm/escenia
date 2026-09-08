<?php

declare(strict_types=1);

namespace App\Domain\Registration\Models;

use App\Domain\Engagement\Models\AttendeeSession;
use App\Domain\Events\Models\Event;
use App\Domain\Shared\Concerns\BelongsToTenant;
use App\Domain\Shared\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An audience member for a specific event. Authenticates to attendee-facing
 * endpoints with a join token, of which only the hash is stored (like guest
 * links). The token itself is the credential, returned once at registration.
 *
 * @property int $id
 * @property string $ulid
 * @property int $tenant_id
 * @property int $event_id
 * @property int $contact_id
 * @property int|null $registration_id
 * @property string $name
 * @property string|null $email
 * @property string $join_token_hash
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Attendee extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'event_id',
        'contact_id',
        'registration_id',
        'name',
        'email',
        'join_token_hash',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'join_token_hash',
    ];

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * @return BelongsTo<Registration, $this>
     */
    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }

    /**
     * @return HasMany<AttendeeSession, $this>
     */
    public function attendeeSessions(): HasMany
    {
        return $this->hasMany(AttendeeSession::class);
    }
}
