<?php

declare(strict_types=1);

namespace App\Domain\Enterprise\Models;

use App\Domain\Enterprise\DTOs\DnsChallenge;
use App\Domain\Enterprise\Enums\SsoProvider;
use App\Domain\Shared\Concerns\BelongsToTenant;
use App\Domain\Shared\Concerns\HasPublicId;
use App\Domain\Tenancy\Enums\TenantRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A tenant's SSO connection. The provider config (client secret, endpoints,
 * certificates) is encrypted at rest, hidden from serialization, and never
 * logged — the same treatment as gateway credentials and stream keys.
 *
 * The connection only vouches for emails in its `domain`, and only once the
 * tenant has proven it controls that domain (DNS TXT, ADR-034). The IdP is
 * tenant-configured, so its claims are never trusted for anyone else's email.
 *
 * @property int $id
 * @property string $ulid
 * @property int $tenant_id
 * @property SsoProvider $provider
 * @property string $display_name
 * @property string|null $domain
 * @property string|null $domain_verification_token
 * @property Carbon|null $domain_verified_at
 * @property array<string, mixed> $config
 * @property TenantRole $default_role
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class SsoConnection extends Model
{
    use BelongsToTenant;
    use HasPublicId;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'provider',
        'display_name',
        'domain',
        'config',
        'default_role',
        'is_active',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'config',
        'domain_verification_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'provider' => SsoProvider::class,
            'config' => 'encrypted:array',
            'default_role' => TenantRole::class,
            'is_active' => 'boolean',
            'domain_verified_at' => 'datetime',
        ];
    }

    /**
     * The DNS record that proves the tenant controls the email domain, or null
     * while the connection has no domain.
     */
    public function dnsChallenge(): ?DnsChallenge
    {
        if ($this->domain === null || $this->domain_verification_token === null) {
            return null;
        }

        return new DnsChallenge('_escenia-sso.'.$this->domain, $this->domain_verification_token);
    }

    public function hasVerifiedDomain(): bool
    {
        return $this->domain !== null && $this->domain_verified_at !== null;
    }

    /**
     * Whether this connection may authenticate the given email: only addresses
     * in its own, verified domain.
     */
    public function vouchesFor(string $email): bool
    {
        return $this->hasVerifiedDomain()
            && Str::lower(Str::afterLast($email, '@')) === $this->domain;
    }

    /**
     * Point the connection at a (new) email domain: it starts unverified with a
     * fresh challenge token. No domain means no challenge.
     */
    public function assignDomain(?string $domain): void
    {
        $domain = $domain !== null ? Str::lower($domain) : null;

        $this->forceFill([
            'domain' => $domain,
            'domain_verification_token' => $domain !== null ? Str::lower(Str::random(40)) : null,
            'domain_verified_at' => null,
        ]);
    }
}
