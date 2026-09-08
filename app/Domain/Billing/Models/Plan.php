<?php

declare(strict_types=1);

namespace App\Domain\Billing\Models;

use App\Domain\Shared\Casts\MoneyCast;
use App\Domain\Shared\Concerns\HasPublicId;
use App\Domain\Shared\ValueObjects\Money;
use Illuminate\Database\Eloquent\Model;

/**
 * A plan in the catalog. Entitlements (features + limits) live here as data so
 * the rest of the system asks the EntitlementResolver instead of branching on
 * a plan key. Billing/subscriptions are out of scope for Foundation.
 *
 * @property int $id
 * @property string $ulid
 * @property string $key
 * @property string $name
 * @property bool $is_active
 * @property list<string>|null $features
 * @property array<string, int|null>|null $limits
 * @property Money|null $price
 */
class Plan extends Model
{
    use HasPublicId;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'name',
        'is_active',
        'features',
        'limits',
        'price',
        'price_currency',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'features' => 'array',
            'limits' => 'array',
            'price' => MoneyCast::class,
        ];
    }

    /**
     * @return list<string>
     */
    public function features(): array
    {
        return $this->features ?? [];
    }

    /**
     * @return array<string, int|null>
     */
    public function limits(): array
    {
        return $this->limits ?? [];
    }
}
