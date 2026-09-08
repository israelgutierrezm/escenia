<?php

declare(strict_types=1);

namespace App\Application\Production\Actions;

use App\Application\Production\DTOs\CreateBrandKitData;
use App\Domain\Audit\Contracts\AuditLogger;
use App\Domain\Identity\Models\User;
use App\Domain\Production\Models\BrandKit;
use App\Domain\Workspaces\Models\Workspace;
use Illuminate\Support\Facades\DB;

final class CreateBrandKitAction
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    public function execute(Workspace $workspace, User $actor, CreateBrandKitData $data): BrandKit
    {
        return DB::transaction(function () use ($workspace, $actor, $data): BrandKit {
            if ($data->isDefault) {
                BrandKit::query()->where('workspace_id', $workspace->getKey())->update(['is_default' => false]);
            }

            $kit = BrandKit::create([
                'tenant_id' => $workspace->tenant_id,
                'workspace_id' => $workspace->getKey(),
                'name' => $data->name,
                'tokens' => $data->tokens,
                'is_default' => $data->isDefault,
            ]);

            $this->audit->log('brand_kit.created', actor: $actor, tenant: $workspace->tenant, auditable: $kit);

            return $kit;
        });
    }
}
