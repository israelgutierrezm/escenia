<?php

declare(strict_types=1);

namespace App\Application\Production\Actions;

use App\Domain\Audit\Contracts\AuditLogger;
use App\Domain\Identity\Models\User;
use App\Domain\Production\Models\RunOfShowItem;
use App\Domain\Studio\Models\Studio;
use Illuminate\Support\Facades\DB;

final class ReorderRunOfShowAction
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  list<string>  $orderedUlids  run-of-show item ULIDs in the desired order
     */
    public function execute(Studio $studio, User $actor, array $orderedUlids): void
    {
        DB::transaction(function () use ($studio, $orderedUlids): void {
            foreach ($orderedUlids as $position => $ulid) {
                RunOfShowItem::query()
                    ->where('studio_id', $studio->getKey())
                    ->where('ulid', $ulid)
                    ->update(['position' => $position]);
            }
        });

        $this->audit->log('production.run_of_show.reordered', actor: $actor, tenant: $studio->tenant, auditable: $studio, context: [
            'count' => count($orderedUlids),
        ]);
    }
}
