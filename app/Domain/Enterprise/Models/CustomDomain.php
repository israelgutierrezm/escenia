<?php

declare(strict_types=1);

namespace App\Domain\Enterprise\Models;

use App\Domain\Enterprise\Enums\DomainStatus;
use App\Domain\Shared\Concerns\BelongsToTenant;
use App\Domain\Shared\Concerns\HasPublicId;
use App\Domain\Workspaces\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A tenant's custom (white-label) domain. Ownership is proven with a DNS TXT
 * challenge (the verification_token) before the domain is allowed to serve
 * traffic. The hostname is globally unique.
 *
 * @property int $id
 * @property string $ulid
 * @property int $tenant_id
 * @property int|null $workspace_id
 * @property string $hostname
 * @property DomainStatus $status
 * @property string $verification_token
 * @property string|null $target
 * @property Carbon|null $verified_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CustomDomain extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'workspace_id',
        'hostname',
        'status',
        'verification_token',
        'target',
        'verified_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DomainStatus::class,
            'verified_at' => 'datetime',
        ];
    }

    /**
     * The DNS record the tenant must create to prove ownership.
     *
     * @return array{type: string, name: string, value: string}
     */
    public function dnsChallenge(): array
    {
        return [
            'type' => 'TXT',
            'name' => '_escenia-challenge.'.$this->hostname,
            'value' => $this->verification_token,
        ];
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
