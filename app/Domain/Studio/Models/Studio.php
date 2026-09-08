<?php

declare(strict_types=1);

namespace App\Domain\Studio\Models;

use App\Domain\Events\Models\Event;
use App\Domain\Shared\Concerns\BelongsToTenant;
use App\Domain\Shared\Concerns\HasPublicId;
use App\Domain\Studio\Enums\StudioStatus;
use App\Domain\Workspaces\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $ulid
 * @property int $tenant_id
 * @property int $workspace_id
 * @property int|null $event_id
 * @property string $name
 * @property StudioStatus $status
 * @property string $provider
 * @property array<string, mixed>|null $settings
 */
class Studio extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'workspace_id',
        'event_id',
        'name',
        'status',
        'provider',
        'settings',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => StudioStatus::class,
            'settings' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @return HasMany<StudioSession, $this>
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(StudioSession::class);
    }

    /**
     * @return HasMany<StudioGuestLink, $this>
     */
    public function guestLinks(): HasMany
    {
        return $this->hasMany(StudioGuestLink::class);
    }

    public function currentSession(): ?StudioSession
    {
        return $this->sessions()->where('status', 'live')->latest()->first();
    }
}
