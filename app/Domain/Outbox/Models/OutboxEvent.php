<?php

declare(strict_types=1);

namespace App\Domain\Outbox\Models;

use App\Domain\Shared\Concerns\BelongsToTenant;
use App\Domain\Shared\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A transactional-outbox record (ADR-007). Written inside the same transaction
 * that changes state; published at-least-once by the dispatcher, so consumers
 * must be idempotent.
 *
 * @property int $id
 * @property string $ulid
 * @property int|null $tenant_id
 * @property string $topic
 * @property array<string, mixed> $payload
 * @property int $attempts
 * @property Carbon $available_at
 * @property Carbon|null $processed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class OutboxEvent extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'topic',
        'payload',
        'attempts',
        'available_at',
        'processed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'attempts' => 'integer',
            'available_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function isProcessed(): bool
    {
        return $this->processed_at !== null;
    }
}
