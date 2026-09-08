<?php

declare(strict_types=1);

namespace App\Domain\Registration\Models;

use App\Domain\Shared\Concerns\BelongsToTenant;
use App\Domain\Shared\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A CRM tag on a contact (audience segmentation). Unique per (contact, tag).
 *
 * @property int $id
 * @property string $ulid
 * @property int $tenant_id
 * @property int $contact_id
 * @property string $tag
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ContactTag extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'contact_id',
        'tag',
    ];

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
