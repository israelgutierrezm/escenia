<?php

declare(strict_types=1);

namespace App\Application\Enterprise\Actions;

use App\Domain\Audit\Contracts\AuditLogger;
use App\Domain\Enterprise\Enums\DomainStatus;
use App\Domain\Enterprise\Models\CustomDomain;
use App\Domain\Identity\Models\User;
use App\Domain\Workspaces\Models\Workspace;
use Illuminate\Support\Str;

/**
 * Registers a custom domain for the current tenant in the `pending` state with
 * a fresh DNS challenge token. The tenant proves ownership via
 * {@see VerifyCustomDomainAction} before the domain is allowed to serve.
 */
final class CreateCustomDomainAction
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    public function execute(User $actor, string $hostname, ?Workspace $workspace = null): CustomDomain
    {
        $domain = CustomDomain::query()->create([
            'workspace_id' => $workspace?->getKey(),
            'hostname' => Str::lower($hostname),
            'status' => DomainStatus::Pending,
            'verification_token' => Str::lower(Str::random(40)),
            'target' => (string) config('enterprise.domain_target'),
        ]);

        $this->audit->log('enterprise.domain.created', actor: $actor, auditable: $domain, context: [
            'hostname' => $domain->hostname,
        ]);

        return $domain;
    }
}
