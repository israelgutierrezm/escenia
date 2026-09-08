<?php

declare(strict_types=1);

namespace App\Domain\Engagement\Models;

use App\Domain\Registration\Models\Attendee;
use App\Domain\Shared\Concerns\BelongsToTenant;
use App\Domain\Shared\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One row per attendee download of a resource, so the tally stays idempotent
 * per attendee. Unique per (resource, attendee).
 *
 * @property int $id
 * @property string $ulid
 * @property int $tenant_id
 * @property int $resource_id
 * @property int $attendee_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ResourceDownload extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'resource_id',
        'attendee_id',
    ];

    /**
     * @return BelongsTo<\App\Domain\Engagement\Models\Resource, $this>
     */
    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class);
    }

    /**
     * @return BelongsTo<Attendee, $this>
     */
    public function attendee(): BelongsTo
    {
        return $this->belongsTo(Attendee::class);
    }
}
