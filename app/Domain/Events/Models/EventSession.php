<?php

declare(strict_types=1);

namespace App\Domain\Events\Models;

use App\Domain\Events\Enums\SessionStatus;
use App\Domain\Shared\Concerns\BelongsToTenant;
use App\Domain\Shared\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $ulid
 * @property int $tenant_id
 * @property int $event_id
 * @property string $title
 * @property SessionStatus $status
 * @property Carbon|null $scheduled_start_at
 * @property Carbon|null $scheduled_end_at
 * @property int $position
 * @property array<string, mixed>|null $settings
 */
class EventSession extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'event_id',
        'title',
        'status',
        'scheduled_start_at',
        'scheduled_end_at',
        'position',
        'settings',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SessionStatus::class,
            'scheduled_start_at' => 'datetime',
            'scheduled_end_at' => 'datetime',
            'position' => 'integer',
            'settings' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
