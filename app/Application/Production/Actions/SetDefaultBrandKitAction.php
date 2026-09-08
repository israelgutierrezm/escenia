<?php

declare(strict_types=1);

namespace App\Application\Production\Actions;

use App\Domain\Audit\Contracts\AuditLogger;
use App\Domain\Identity\Models\User;
use App\Domain\Production\Models\BrandKit;
use Illuminate\Support\Facades\DB;

final class SetDefaultBrandKitAction
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    public function execute(BrandKit $kit, User $actor): BrandKit
    {
        return DB::transaction(function () use ($kit, $actor): BrandKit {
            BrandKit::query()->where('workspace_id', $kit->workspace_id)->update(['is_default' => false]);

            $kit->update(['is_default' => true]);

            $this->audit->log('brand_kit.set_default', actor: $actor, tenant: $kit->tenant, auditable: $kit);

            return $kit;
        });
    }
}
