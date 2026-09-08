<?php

declare(strict_types=1);

namespace App\Application\Identity\Actions;

use App\Application\Identity\DTOs\RegisterUserData;
use App\Domain\Audit\Contracts\AuditLogger;
use App\Domain\Billing\Models\Plan;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Enums\MembershipStatus;
use App\Domain\Tenancy\Enums\TenantRole;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Models\TenantMembership;
use App\Domain\Workspaces\Enums\WorkspaceRole;
use App\Domain\Workspaces\Models\Workspace;
use App\Domain\Workspaces\Models\WorkspaceMembership;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

/**
 * Registers a new user together with their first tenant. This is the canonical
 * bootstrap path: user -> tenant (on default plan) -> owner membership ->
 * default workspace -> owner role assignment, all in one transaction.
 */
final class RegisterUserAction
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    public function execute(RegisterUserData $data): User
    {
        return DB::transaction(function () use ($data): User {
            $user = User::create([
                'name' => $data->name,
                'email' => $data->email,
                'password' => $data->password,
            ]);

            $plan = Plan::query()->where('key', config('escenia.default_plan'))->first();

            $tenant = Tenant::create([
                'name' => $data->tenantName,
                'slug' => $this->uniqueSlug($data->tenantName),
                'status' => 'active',
                'plan_id' => $plan?->getKey(),
            ]);

            TenantMembership::create([
                'tenant_id' => $tenant->getKey(),
                'user_id' => $user->getKey(),
                'role' => TenantRole::Owner,
                'status' => MembershipStatus::Active,
                'joined_at' => now(),
            ]);

            $workspace = Workspace::create([
                'tenant_id' => $tenant->getKey(),
                'name' => 'Default',
                'slug' => 'default',
                'status' => 'active',
            ]);

            WorkspaceMembership::create([
                'tenant_id' => $tenant->getKey(),
                'workspace_id' => $workspace->getKey(),
                'user_id' => $user->getKey(),
                'role' => WorkspaceRole::Manager,
            ]);

            // Mirror the tenant membership role to a Spatie role assignment
            // scoped to this tenant's team (ADR-011).
            app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->getKey());
            $user->assignRole(TenantRole::Owner->value);

            $this->audit->log('user.registered', actor: $user, tenant: $tenant, auditable: $user);
            $this->audit->log('tenant.created', actor: $user, tenant: $tenant, auditable: $tenant, context: [
                'slug' => $tenant->slug,
            ]);

            return $user;
        });
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'tenant';

        do {
            $slug = $base.'-'.Str::lower(Str::random(6));
        } while (Tenant::query()->where('slug', $slug)->exists());

        return $slug;
    }
}
