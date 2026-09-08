<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Models;

use App\Domain\Registration\Models\Contact;
use App\Domain\Shared\Concerns\BelongsToTenant;
use App\Domain\Shared\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A notification produced by the platform. The default `log` channel records it
 * here; real delivery (email/SMS) is a future channel behind the Notifier
 * contract.
 *
 * @property int $id
 * @property string $ulid
 * @property int $tenant_id
 * @property int|null $contact_id
 * @property string $channel
 * @property string $to
 * @property string $subject
 * @property string $body
 * @property Carbon|null $sent_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Notification extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'contact_id',
        'channel',
        'to',
        'subject',
        'body',
        'sent_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
