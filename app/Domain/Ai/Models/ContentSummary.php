<?php

declare(strict_types=1);

namespace App\Domain\Ai\Models;

use App\Domain\Ai\Enums\SummaryKind;
use App\Domain\Ai\Enums\SummaryStatus;
use App\Domain\Content\Models\Recording;
use App\Domain\Shared\Concerns\BelongsToTenant;
use App\Domain\Shared\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An AI-generated artifact for a recording (Content Factory).
 *
 * @property int $id
 * @property string $ulid
 * @property int $tenant_id
 * @property int $recording_id
 * @property SummaryKind $kind
 * @property SummaryStatus $status
 * @property string|null $provider
 * @property string|null $model
 * @property string|null $content
 * @property string|null $failed_reason
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ContentSummary extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'recording_id',
        'kind',
        'status',
        'provider',
        'model',
        'content',
        'failed_reason',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => SummaryKind::class,
            'status' => SummaryStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Recording, $this>
     */
    public function recording(): BelongsTo
    {
        return $this->belongsTo(Recording::class);
    }
}
