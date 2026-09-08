<?php

declare(strict_types=1);

namespace App\Domain\Production\Models;

use App\Domain\Shared\Concerns\BelongsToTenant;
use App\Domain\Shared\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $ulid
 * @property int $tenant_id
 * @property int $scene_id
 * @property int $version
 * @property string $schema_version
 * @property array<string, mixed> $definition
 * @property bool $is_current
 * @property int|null $created_by
 */
class SceneVersion extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'scene_id',
        'version',
        'schema_version',
        'definition',
        'is_current',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'definition' => 'array',
            'is_current' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Scene, $this>
     */
    public function scene(): BelongsTo
    {
        return $this->belongsTo(Scene::class);
    }
}
