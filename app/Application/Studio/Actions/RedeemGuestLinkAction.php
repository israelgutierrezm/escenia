<?php

declare(strict_types=1);

namespace App\Application\Studio\Actions;

use App\Domain\Audit\Contracts\AuditLogger;
use App\Domain\Media\ValueObjects\AccessToken;
use App\Domain\Studio\Enums\ParticipantStage;
use App\Domain\Studio\Exceptions\GuestLinkInvalidException;
use App\Domain\Studio\Models\Studio;
use App\Domain\Studio\Models\StudioGuestLink;
use App\Domain\Studio\Models\StudioParticipant;
use App\Domain\Studio\Models\StudioSession;
use App\Domain\Tenancy\Context\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Public flow: a guest redeems a link to join a studio. There is no tenant
 * session here — the link IS the credential. The link is looked up unscoped by
 * its token hash, then the tenant context is established from the link so all
 * subsequent tenant-owned writes are correctly scoped.
 *
 * @phpstan-type RedeemResult array{participant: StudioParticipant, token: AccessToken, session: StudioSession}
 */
final class RedeemGuestLinkAction
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly IssueParticipantTokenAction $issueToken,
        private readonly TenantContext $tenantContext,
    ) {}

    /**
     * @return array{participant: StudioParticipant, token: AccessToken, session: StudioSession}
     */
    public function execute(string $rawToken, string $guestName): array
    {
        $link = StudioGuestLink::query()
            ->withoutGlobalScopes()
            ->where('token_hash', hash('sha256', $rawToken))
            ->first();

        if ($link === null || ! $link->isRedeemable()) {
            throw new GuestLinkInvalidException;
        }

        return $this->tenantContext->runFor($link->tenant, function () use ($link, $guestName): array {
            $studio = Studio::query()->whereKey($link->studio_id)->firstOrFail();
            $session = $studio->currentSession();

            if ($session === null) {
                throw new GuestLinkInvalidException;
            }

            return DB::transaction(function () use ($link, $guestName, $session): array {
                $participant = StudioParticipant::create([
                    'tenant_id' => $link->tenant_id,
                    'studio_session_id' => $session->getKey(),
                    'guest_link_id' => $link->getKey(),
                    'identity' => 'g_'.strtolower((string) Str::ulid()),
                    'name' => $guestName,
                    'role' => $link->role,
                    'stage' => ParticipantStage::GreenRoom,
                    'joined_at' => now(),
                ]);

                $link->increment('uses');

                $this->audit->log('studio.guest.joined', tenant: $link->tenant, auditable: $participant, context: [
                    'guest_link' => $link->ulid,
                ]);

                return [
                    'participant' => $participant,
                    'token' => $this->issueToken->execute($participant),
                    'session' => $session,
                ];
            });
        });
    }
}
