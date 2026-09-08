<?php

declare(strict_types=1);

namespace App\Domain\Broadcasting\Models;

use App\Domain\Broadcasting\Enums\BroadcastDestinationStatus;
use App\Domain\Shared\Concerns\BelongsToTenant;
use App\Domain\Shared\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $ulid
 * @property int $tenant_id
 * @property int $broadcast_session_id
 * @property int $stream_destination_id
 * @property BroadcastDestinationStatus $status
 */
class BroadcastDestination extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'broadcast_session_id',
        'stream_destination_id',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => BroadcastDestinationStatus::class,
        ];
    }

    /**
     * @return BelongsTo<BroadcastSession, $this>
     */
    public function broadcast(): BelongsTo
    {
        return $this->belongsTo(BroadcastSession::class, 'broadcast_session_id');
    }

    /**
     * @return BelongsTo<StreamDestination, $this>
     */
    public function destination(): BelongsTo
    {
        return $this->belongsTo(StreamDestination::class, 'stream_destination_id');
    }
}
