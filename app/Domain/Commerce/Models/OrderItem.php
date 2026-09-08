<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Models;

use App\Domain\Shared\Concerns\BelongsToTenant;
use App\Domain\Shared\Concerns\HasPublicId;
use App\Domain\Shared\ValueObjects\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A line on an order. The unit price is snapshotted at purchase time.
 *
 * @property int $id
 * @property string $ulid
 * @property int $tenant_id
 * @property int $order_id
 * @property int $ticket_id
 * @property string $ticket_name
 * @property int $quantity
 * @property int $unit_amount_minor
 * @property int $subtotal_minor
 * @property string $currency
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class OrderItem extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'order_id',
        'ticket_id',
        'ticket_name',
        'quantity',
        'unit_amount_minor',
        'subtotal_minor',
        'currency',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_amount_minor' => 'integer',
            'subtotal_minor' => 'integer',
        ];
    }

    public function subtotal(): Money
    {
        return Money::of($this->subtotal_minor, $this->currency);
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<Ticket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }
}
