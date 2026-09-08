<?php

declare(strict_types=1);

namespace App\Domain\FeatureManagement\Models;

use App\Domain\FeatureManagement\Enums\FeatureFlagScope;
use App\Domain\Shared\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;

/**
 * A single feature flag row. Not blanket tenant-scoped: a flag may be global
 * (all owner ids null) or targeted at a tenant/workspace/user. Resolution
 * precedence is handled by the FeatureFlagResolver, not a global scope.
 *
 * @property int $id
 * @property string $ulid
 * @property string $key
 * @property FeatureFlagScope $scope
 * @property int|null $tenant_id
 * @property int|null $workspace_id
 * @property int|null $user_id
 * @property bool $enabled
 * @property int|null $rollout_percentage
 * @property array<string, mixed>|null $value
 */
class FeatureFlag extends Model
{
    use HasPublicId;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'scope',
        'tenant_id',
        'workspace_id',
        'user_id',
        'enabled',
        'rollout_percentage',
        'value',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scope' => FeatureFlagScope::class,
            'enabled' => 'boolean',
            'rollout_percentage' => 'integer',
            'value' => 'array',
        ];
    }
}
