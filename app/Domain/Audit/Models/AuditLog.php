<?php

declare(strict_types=1);

namespace App\Domain\Audit\Models;

use App\Domain\Shared\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Immutable audit record. Append-only: updated_at is disabled and rows are
 * never mutated after creation.
 *
 * @property int $id
 * @property string $ulid
 * @property int|null $tenant_id
 * @property int|null $actor_id
 * @property string|null $actor_label
 * @property string $action
 * @property string|null $auditable_type
 * @property int|null $auditable_id
 * @property array<string, mixed>|null $context
 * @property string|null $ip_address
 * @property string|null $request_id
 * @property string|null $correlation_id
 * @property Carbon|null $created_at
 */
class AuditLog extends Model
{
    use HasPublicId;

    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'actor_id',
        'actor_label',
        'action',
        'auditable_type',
        'auditable_id',
        'context',
        'ip_address',
        'request_id',
        'correlation_id',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'context' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
