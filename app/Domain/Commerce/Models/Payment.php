<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Models;

use App\Domain\Commerce\Enums\PaymentGatewayName;
use App\Domain\Commerce\Enums\PaymentStatus;
use App\Domain\Shared\Concerns\BelongsToTenant;
use App\Domain\Shared\Concerns\HasPublicId;
use App\Domain\Shared\ValueObjects\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A payment attempt against an order via a gateway. The (gateway,
 * gateway_reference) pair is unique so a replayed webhook is idempotent.
 *
 * @property int $id
 * @property string $ulid
 * @property int $tenant_id
 * @property int $order_id
 * @property PaymentGatewayName $gateway
 * @property string $gateway_reference
 * @property PaymentStatus $status
 * @property int $amount_minor
 * @property string $currency
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Payment extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'order_id',
        'gateway',
        'gateway_reference',
        'status',
        'amount_minor',
        'currency',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gateway' => PaymentGatewayName::class,
            'status' => PaymentStatus::class,
            'amount_minor' => 'integer',
        ];
    }

    public function amount(): Money
    {
        return Money::of($this->amount_minor, $this->currency);
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
