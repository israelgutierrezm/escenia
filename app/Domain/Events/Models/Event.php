<?php

declare(strict_types=1);

namespace App\Domain\Events\Models;

use App\Domain\Events\Enums\Capability;
use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Enums\EventType;
use App\Domain\Identity\Models\User;
use App\Domain\Shared\Concerns\BelongsToTenant;
use App\Domain\Shared\Concerns\HasPublicId;
use App\Domain\Workspaces\Models\Workspace;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * The Event aggregate: source of truth for an event and its lifecycle. Behaviour
 * is composed from capabilities; the status is driven by a guarded state machine.
 *
 * @property int $id
 * @property string $ulid
 * @property int $tenant_id
 * @property int $workspace_id
 * @property int|null $template_id
 * @property int|null $created_by
 * @property EventType $type
 * @property EventStatus $status
 * @property string $title
 * @property string $slug
 * @property string|null $description
 * @property string $timezone
 * @property Carbon|null $scheduled_start_at
 * @property Carbon|null $scheduled_end_at
 * @property Carbon|null $actual_start_at
 * @property Carbon|null $actual_end_at
 * @property array<string, mixed>|null $settings
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Event extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<EventFactory> */
    use HasFactory;

    use HasPublicId;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'workspace_id',
        'template_id',
        'created_by',
        'type',
        'status',
        'title',
        'slug',
        'description',
        'timezone',
        'scheduled_start_at',
        'scheduled_end_at',
        'actual_start_at',
        'actual_end_at',
        'settings',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => EventType::class,
            'status' => EventStatus::class,
            'scheduled_start_at' => 'datetime',
            'scheduled_end_at' => 'datetime',
            'actual_start_at' => 'datetime',
            'actual_end_at' => 'datetime',
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
     * @return BelongsTo<EventTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(EventTemplate::class, 'template_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<EventCapability, $this>
     */
    public function capabilities(): HasMany
    {
        return $this->hasMany(EventCapability::class);
    }

    /**
     * @return HasMany<EventSession, $this>
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(EventSession::class);
    }

    /**
     * @return HasMany<EventSpeaker, $this>
     */
    public function speakers(): HasMany
    {
        return $this->hasMany(EventSpeaker::class);
    }

    /**
     * @return HasMany<EventScheduleItem, $this>
     */
    public function scheduleItems(): HasMany
    {
        return $this->hasMany(EventScheduleItem::class);
    }

    public function hasCapabilityEnabled(Capability $capability): bool
    {
        return $this->capabilities()
            ->where('capability', $capability->value)
            ->where('enabled', true)
            ->exists();
    }

    protected static function newFactory(): EventFactory
    {
        return EventFactory::new();
    }
}
