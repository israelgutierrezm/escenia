<?php

declare(strict_types=1);

namespace App\Domain\Sponsorship\Models;

use App\Domain\Shared\Concerns\BelongsToTenant;
use App\Domain\Shared\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An expo booth for a sponsor. `leads_count` is the denormalized tally.
 *
 * @property int $id
 * @property string $ulid
 * @property int $tenant_id
 * @property int $event_id
 * @property int $sponsor_id
 * @property string $name
 * @property string|null $description
 * @property string|null $url
 * @property int $leads_count
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Booth extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'event_id',
        'sponsor_id',
        'name',
        'description',
        'url',
        'leads_count',
        'position',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'leads_count' => 'integer',
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Sponsor, $this>
     */
    public function sponsor(): BelongsTo
    {
        return $this->belongsTo(Sponsor::class);
    }

    /**
     * @return HasMany<BoothLead, $this>
     */
    public function leads(): HasMany
    {
        return $this->hasMany(BoothLead::class);
    }
}
