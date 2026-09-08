<?php

declare(strict_types=1);

namespace App\Domain\Production\Models;

use App\Domain\Shared\Concerns\BelongsToTenant;
use App\Domain\Shared\Concerns\HasPublicId;
use App\Domain\Studio\Models\Studio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property string $ulid
 * @property int $tenant_id
 * @property int $studio_id
 * @property string $name
 * @property int $position
 */
class Scene extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'studio_id',
        'name',
        'position',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Studio, $this>
     */
    public function studio(): BelongsTo
    {
        return $this->belongsTo(Studio::class);
    }

    /**
     * @return HasMany<SceneVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(SceneVersion::class);
    }

    /**
     * @return HasOne<SceneVersion, $this>
     */
    public function currentVersion(): HasOne
    {
        return $this->hasOne(SceneVersion::class)->where('is_current', true);
    }
}
