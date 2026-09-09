<?php

declare(strict_types=1);

namespace App\Domain\Settings\Models;

use App\Domain\Settings\Enums\SettingScope;
use App\Domain\Shared\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A stored configuration value. NOT tenant-scoped by the global scope: system
 * rows (tenant_id null) and tenant rows must both be readable when resolving a
 * key, so the repository queries scope/tenant explicitly. The value is encrypted
 * at rest (it may be a secret) and holds a JSON-encoded scalar/array.
 *
 * @property int $id
 * @property string $ulid
 * @property SettingScope $scope
 * @property int|null $tenant_id
 * @property string $key
 * @property string $value
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Setting extends Model
{
    use HasPublicId;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'scope',
        'tenant_id',
        'key',
        'value',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'value',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scope' => SettingScope::class,
            'value' => 'encrypted',
        ];
    }
}
