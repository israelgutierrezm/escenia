<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Models;

use App\Domain\Registration\Models\Attendee;
use App\Domain\Shared\Concerns\BelongsToTenant;
use App\Domain\Shared\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One row per CTA click by an attendee. The denormalized counter lives on the
 * CTA.
 *
 * @property int $id
 * @property string $ulid
 * @property int $tenant_id
 * @property int $cta_id
 * @property int|null $attendee_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CtaClick extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'cta_id',
        'attendee_id',
    ];

    /**
     * @return BelongsTo<Cta, $this>
     */
    public function cta(): BelongsTo
    {
        return $this->belongsTo(Cta::class);
    }

    /**
     * @return BelongsTo<Attendee, $this>
     */
    public function attendee(): BelongsTo
    {
        return $this->belongsTo(Attendee::class);
    }
}
