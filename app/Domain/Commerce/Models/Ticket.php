<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Models;

use App\Domain\Events\Models\Event;
use App\Domain\Shared\Concerns\BelongsToTenant;
use App\Domain\Shared\Concerns\HasPublicId;
use App\Domain\Shared\ValueObjects\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A ticket type for an event. Price is integer minor units + currency (never a
 * float); `compare_at_minor` is an optional strike-through price for offers.
 *
 * @property int $id
 * @property string $ulid
 * @property int $tenant_id
 * @property int $event_id
 * @property string $name
 * @property string|null $description
 * @property int $amount_minor
 * @property string $currency
 * @property int|null $compare_at_minor
 * @property int|null $capacity
 * @property int $sold_count
 * @property bool $is_active
 * @property Carbon|null $sales_start_at
 * @property Carbon|null $sales_end_at
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Ticket extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'event_id',
        'name',
        'description',
        'amount_minor',
        'currency',
        'compare_at_minor',
        'capacity',
        'sold_count',
        'is_active',
        'sales_start_at',
        'sales_end_at',
        'position',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'compare_at_minor' => 'integer',
            'capacity' => 'integer',
            'sold_count' => 'integer',
            'is_active' => 'boolean',
            'sales_start_at' => 'datetime',
            'sales_end_at' => 'datetime',
            'position' => 'integer',
        ];
    }

    public function price(): Money
    {
        return Money::of($this->amount_minor, $this->currency);
    }

    /**
     * Whether the ticket is currently purchasable: active, within its sales
     * window, and not sold out.
     */
    public function isOnSale(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $now = now();

        if ($this->sales_start_at !== null && $this->sales_start_at->isAfter($now)) {
            return false;
        }

        if ($this->sales_end_at !== null && $this->sales_end_at->isBefore($now)) {
            return false;
        }

        return true;
    }

    public function remaining(): ?int
    {
        return $this->capacity === null ? null : max(0, $this->capacity - $this->sold_count);
    }

    public function hasStockFor(int $quantity): bool
    {
        $remaining = $this->remaining();

        return $remaining === null || $remaining >= $quantity;
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
