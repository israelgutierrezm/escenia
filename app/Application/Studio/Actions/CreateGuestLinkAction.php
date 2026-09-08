<?php

declare(strict_types=1);

namespace App\Application\Studio\Actions;

use App\Application\Studio\DTOs\CreateGuestLinkData;
use App\Domain\Audit\Contracts\AuditLogger;
use App\Domain\Identity\Models\User;
use App\Domain\Studio\Models\Studio;
use App\Domain\Studio\Models\StudioGuestLink;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates a signed guest link. The raw token is returned once (for the invite
 * URL); only its SHA-256 hash is persisted.
 *
 * @phpstan-type GuestLinkResult array{link: StudioGuestLink, token: string}
 */
final class CreateGuestLinkAction
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @return array{link: StudioGuestLink, token: string}
     */
    public function execute(Studio $studio, User $actor, CreateGuestLinkData $data): array
    {
        $rawToken = Str::random(48);

        $link = DB::transaction(function () use ($studio, $actor, $data, $rawToken): StudioGuestLink {
            $link = StudioGuestLink::create([
                'tenant_id' => $studio->tenant_id,
                'studio_id' => $studio->getKey(),
                'created_by' => $actor->getKey(),
                'token_hash' => hash('sha256', $rawToken),
                'name' => $data->name,
                'role' => $data->role,
                'expires_at' => $data->expiresAt,
                'single_use' => $data->singleUse,
                'max_uses' => $data->maxUses,
                'uses' => 0,
            ]);

            $this->audit->log('studio.guest_link.created', actor: $actor, tenant: $studio->tenant, auditable: $link, context: [
                'role' => $data->role->value,
            ]);

            return $link;
        });

        return ['link' => $link, 'token' => $rawToken];
    }
}
