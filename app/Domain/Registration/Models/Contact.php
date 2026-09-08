<?php

declare(strict_types=1);

namespace App\Domain\Registration\Models;

use App\Domain\Shared\Concerns\BelongsToTenant;
use App\Domain\Shared\Concerns\HasPublicId;
use App\Domain\Workspaces\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A person known to a workspace (the audience CRM root). Unique per
 * (workspace, email); reused across the events they register for.
 *
 * @property int $id
 * @property string $ulid
 * @property int $tenant_id
 * @property int $workspace_id
 * @property string $email
 * @property string $name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Contact extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'workspace_id',
        'email',
        'name',
    ];

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * @return HasMany<Registration, $this>
     */
    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    /**
     * @return HasMany<ContactTag, $this>
     */
    public function tags(): HasMany
    {
        return $this->hasMany(ContactTag::class);
    }
}
