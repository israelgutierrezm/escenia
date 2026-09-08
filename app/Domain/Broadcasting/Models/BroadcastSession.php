<?php

declare(strict_types=1);

namespace App\Domain\Broadcasting\Models;

use App\Domain\Broadcasting\Enums\BroadcastHealth;
use App\Domain\Broadcasting\Enums\BroadcastStatus;
use App\Domain\Shared\Concerns\BelongsToTenant;
use App\Domain\Shared\Concerns\HasPublicId;
use App\Domain\Studio\Models\StudioSession;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $ulid
 * @property int $tenant_id
 * @property int $studio_session_id
 * @property BroadcastStatus $status
 * @property BroadcastHealth $health
 * @property bool $record
 * @property string|null $egress_ref
 * @property int|null $started_by
 * @property Carbon|null $started_at
 * @property Carbon|null $ended_at
 */
class BroadcastSession extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'studio_session_id',
        'status',
        'health',
        'record',
        'egress_ref',
        'started_by',
        'started_at',
        'ended_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => BroadcastStatus::class,
            'health' => BroadcastHealth::class,
            'record' => 'boolean',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<StudioSession, $this>
     */
    public function studioSession(): BelongsTo
    {
        return $this->belongsTo(StudioSession::class);
    }

    /**
     * @return HasMany<BroadcastDestination, $this>
     */
    public function destinations(): HasMany
    {
        return $this->hasMany(BroadcastDestination::class);
    }
}
