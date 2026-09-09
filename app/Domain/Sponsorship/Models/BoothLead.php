<?php

declare(strict_types=1);

namespace App\Domain\Sponsorship\Models;

use App\Domain\Registration\Models\Attendee;
use App\Domain\Shared\Concerns\BelongsToTenant;
use App\Domain\Shared\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A lead captured when an attendee visits a booth. Unique per (booth, attendee).
 *
 * @property int $id
 * @property string $ulid
 * @property int $tenant_id
 * @property int $booth_id
 * @property int $attendee_id
 * @property string|null $note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class BoothLead extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'booth_id',
        'attendee_id',
        'note',
    ];

    /**
     * @return BelongsTo<Booth, $this>
     */
    public function booth(): BelongsTo
    {
        return $this->belongsTo(Booth::class);
    }

    /**
     * @return BelongsTo<Attendee, $this>
     */
    public function attendee(): BelongsTo
    {
        return $this->belongsTo(Attendee::class);
    }
}
