<?php

declare(strict_types=1);

namespace App\Domain\Content\Models;

use App\Domain\Content\Enums\ClipStatus;
use App\Domain\Identity\Models\User;
use App\Domain\Shared\Concerns\BelongsToTenant;
use App\Domain\Shared\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A clip cut from a recording (content studio / editor). Stores the in/out
 * range; rendering the trimmed asset is future.
 *
 * @property int $id
 * @property string $ulid
 * @property int $tenant_id
 * @property int $recording_id
 * @property string $title
 * @property int $start_ms
 * @property int $end_ms
 * @property ClipStatus $status
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Clip extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'recording_id',
        'title',
        'start_ms',
        'end_ms',
        'status',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_ms' => 'integer',
            'end_ms' => 'integer',
            'status' => ClipStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Recording, $this>
     */
    public function recording(): BelongsTo
    {
        return $this->belongsTo(Recording::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
