<?php

declare(strict_types=1);

namespace App\Domain\Registration\Models;

use App\Domain\Events\Models\Event;
use App\Domain\Shared\Concerns\BelongsToTenant;
use App\Domain\Shared\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Per-event public registration form. `fields` holds the ordered custom field
 * definitions (beyond the always-present name + email).
 *
 * @property int $id
 * @property string $ulid
 * @property int $tenant_id
 * @property int $event_id
 * @property bool $is_open
 * @property array<int, array<string, mixed>> $fields
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class RegistrationForm extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'event_id',
        'is_open',
        'fields',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_open' => 'boolean',
            'fields' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
