<?php

declare(strict_types=1);

namespace App\Application\Workspaces\Actions;

use App\Application\Workspaces\DTOs\CreateWorkspaceData;
use App\Domain\Audit\Contracts\AuditLogger;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Workspaces\Enums\WorkspaceRole;
use App\Domain\Workspaces\Models\Workspace;
use App\Domain\Workspaces\Models\WorkspaceMembership;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CreateWorkspaceAction
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    public function execute(Tenant $tenant, User $actor, CreateWorkspaceData $data): Workspace
    {
        return DB::transaction(function () use ($tenant, $actor, $data): Workspace {
            $workspace = Workspace::create([
                'tenant_id' => $tenant->getKey(),
                'name' => $data->name,
                'slug' => $this->uniqueSlug($data->slug ?? $data->name),
                'status' => 'active',
            ]);

            WorkspaceMembership::create([
                'tenant_id' => $tenant->getKey(),
                'workspace_id' => $workspace->getKey(),
                'user_id' => $actor->getKey(),
                'role' => WorkspaceRole::Manager,
            ]);

            $this->audit->log('workspace.created', actor: $actor, tenant: $tenant, auditable: $workspace, context: [
                'slug' => $workspace->slug,
            ]);

            return $workspace;
        });
    }

    /**
     * Slugs are unique per tenant. The tenant global scope is active here, so
     * the existence check is already constrained to the current tenant.
     */
    private function uniqueSlug(string $value): string
    {
        $base = Str::slug($value) ?: 'workspace';
        $slug = $base;
        $suffix = 1;

        while (Workspace::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$suffix);
        }

        return $slug;
    }
}
