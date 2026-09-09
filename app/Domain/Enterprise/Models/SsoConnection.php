<?php

declare(strict_types=1);

namespace App\Domain\Enterprise\Models;

use App\Domain\Enterprise\Enums\SsoProvider;
use App\Domain\Shared\Concerns\BelongsToTenant;
use App\Domain\Shared\Concerns\HasPublicId;
use App\Domain\Tenancy\Enums\TenantRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A tenant's SSO connection. The provider config (client secret, endpoints,
 * certificates) is encrypted at rest, hidden from serialization, and never
 * logged — the same treatment as gateway credentials and stream keys.
 *
 * @property int $id
 * @property string $ulid
 * @property int $tenant_id
 * @property SsoProvider $provider
 * @property string $display_name
 * @property string|null $domain
 * @property array<string, mixed> $config
 * @property TenantRole $default_role
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class SsoConnection extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'provider',
        'display_name',
        'domain',
        'config',
        'default_role',
        'is_active',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'config',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'provider' => SsoProvider::class,
            'config' => 'encrypted:array',
            'default_role' => TenantRole::class,
            'is_active' => 'boolean',
        ];
    }
}
