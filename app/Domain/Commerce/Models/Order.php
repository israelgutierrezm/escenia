<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Models;

use App\Domain\Commerce\Enums\OrderStatus;
use App\Domain\Events\Models\Event;
use App\Domain\Registration\Models\Attendee;
use App\Domain\Registration\Models\Contact;
use App\Domain\Shared\Concerns\BelongsToTenant;
use App\Domain\Shared\Concerns\HasPublicId;
use App\Domain\Shared\ValueObjects\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A purchase order for tickets to an event. Guarded lifecycle
 * (pending → paid → refunded, or pending → canceled). Totals are integer minor
 * units in the order currency.
 *
 * @property int $id
 * @property string $ulid
 * @property int $tenant_id
 * @property int $event_id
 * @property int|null $contact_id
 * @property int|null $attendee_id
 * @property string $buyer_name
 * @property string $buyer_email
 * @property OrderStatus $status
 * @property string $currency
 * @property int $subtotal_minor
 * @property int $total_minor
 * @property string $gateway
 * @property Carbon|null $paid_at
 * @property Carbon|null $canceled_at
 * @property Carbon|null $refunded_at
 * @property Carbon|null $fulfilled_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Order extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'event_id',
        'contact_id',
        'attendee_id',
        'buyer_name',
        'buyer_email',
        'status',
        'currency',
        'subtotal_minor',
        'total_minor',
        'gateway',
        'paid_at',
        'canceled_at',
        'refunded_at',
        'fulfilled_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'subtotal_minor' => 'integer',
            'total_minor' => 'integer',
            'paid_at' => 'datetime',
            'canceled_at' => 'datetime',
            'refunded_at' => 'datetime',
            'fulfilled_at' => 'datetime',
        ];
    }

    public function total(): Money
    {
        return Money::of($this->total_minor, $this->currency);
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * @return BelongsTo<Attendee, $this>
     */
    public function attendee(): BelongsTo
    {
        return $this->belongsTo(Attendee::class);
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
