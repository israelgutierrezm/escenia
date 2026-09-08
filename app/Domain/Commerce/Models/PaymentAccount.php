<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Models;

use App\Domain\Commerce\Enums\PaymentGatewayName;
use App\Domain\Shared\Concerns\BelongsToTenant;
use App\Domain\Shared\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A tenant's connected payment gateway. Credentials and the webhook secret are
 * encrypted at rest, hidden from serialization, and never logged (like stream
 * keys — CLAUDE.md / ADR-021).
 *
 * @property int $id
 * @property string $ulid
 * @property int $tenant_id
 * @property PaymentGatewayName $gateway
 * @property string $display_name
 * @property array<string, mixed> $credentials
 * @property string|null $webhook_secret
 * @property string $currency
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class PaymentAccount extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'gateway',
        'display_name',
        'credentials',
        'webhook_secret',
        'currency',
        'is_active',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'credentials',
        'webhook_secret',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gateway' => PaymentGatewayName::class,
            'credentials' => 'encrypted:array',
            'webhook_secret' => 'encrypted',
            'is_active' => 'boolean',
        ];
    }
}
