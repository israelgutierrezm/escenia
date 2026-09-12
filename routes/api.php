<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AccessControl\MemberController;
use App\Http\Controllers\Api\V1\AccessControl\PermissionController;
use App\Http\Controllers\Api\V1\AccessControl\RoleController;
use App\Http\Controllers\Api\V1\Ai\ContentSummaryController;
use App\Http\Controllers\Api\V1\Ai\EventArchitectController;
use App\Http\Controllers\Api\V1\Ai\SemanticSearchController;
use App\Http\Controllers\Api\V1\Ai\SmartQaController;
use App\Http\Controllers\Api\V1\Analytics\AnalyticsController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\MeController;
use App\Http\Controllers\Api\V1\Auth\RegisterController;
use App\Http\Controllers\Api\V1\Automation\AutomationController;
use App\Http\Controllers\Api\V1\Automation\AutomationRunController;
use App\Http\Controllers\Api\V1\Broadcasting\BroadcastController;
use App\Http\Controllers\Api\V1\Broadcasting\StreamDestinationController;
use App\Http\Controllers\Api\V1\Commerce\Attendee\CtaController as AttendeeCtaController;
use App\Http\Controllers\Api\V1\Commerce\Host\CommerceReportController;
use App\Http\Controllers\Api\V1\Commerce\Host\CtaController as HostCtaController;
use App\Http\Controllers\Api\V1\Commerce\Host\OrderController as HostOrderController;
use App\Http\Controllers\Api\V1\Commerce\Host\PaymentAccountController;
use App\Http\Controllers\Api\V1\Commerce\Host\TicketController as HostTicketController;
use App\Http\Controllers\Api\V1\Commerce\PublicCheckoutController;
use App\Http\Controllers\Api\V1\Commerce\WebhookController;
use App\Http\Controllers\Api\V1\Content\ClipController;
use App\Http\Controllers\Api\V1\Content\RecordingController;
use App\Http\Controllers\Api\V1\Content\TranscriptController;
use App\Http\Controllers\Api\V1\Education\Attendee\AssessmentController as AttendeeAssessmentController;
use App\Http\Controllers\Api\V1\Education\CertificateVerifyController;
use App\Http\Controllers\Api\V1\Education\Host\AssessmentController as HostAssessmentController;
use App\Http\Controllers\Api\V1\Education\Host\CertificateController as HostCertificateController;
use App\Http\Controllers\Api\V1\Education\Host\CompletionRuleController;
use App\Http\Controllers\Api\V1\Education\Host\SubmissionController;
use App\Http\Controllers\Api\V1\Engagement\Attendee\ChatController as AttendeeChatController;
use App\Http\Controllers\Api\V1\Engagement\Attendee\PollController as AttendeePollController;
use App\Http\Controllers\Api\V1\Engagement\Attendee\PresenceController as AttendeePresenceController;
use App\Http\Controllers\Api\V1\Engagement\Attendee\QuestionController as AttendeeQuestionController;
use App\Http\Controllers\Api\V1\Engagement\Attendee\ResourceController as AttendeeResourceController;
use App\Http\Controllers\Api\V1\Engagement\Host\ChatController as HostChatController;
use App\Http\Controllers\Api\V1\Engagement\Host\PollController as HostPollController;
use App\Http\Controllers\Api\V1\Engagement\Host\QuestionController as HostQuestionController;
use App\Http\Controllers\Api\V1\Engagement\Host\ResourceController as HostResourceController;
use App\Http\Controllers\Api\V1\Enterprise\Attendee\AgendaController as AttendeeAgendaController;
use App\Http\Controllers\Api\V1\Enterprise\Attendee\ExpoController as AttendeeExpoController;
use App\Http\Controllers\Api\V1\Enterprise\Attendee\GamificationController as AttendeeGamificationController;
use App\Http\Controllers\Api\V1\Enterprise\DomainResolveController;
use App\Http\Controllers\Api\V1\Enterprise\Host\ApiKeyController;
use App\Http\Controllers\Api\V1\Enterprise\Host\AuditLogController;
use App\Http\Controllers\Api\V1\Enterprise\Host\BoothController as HostBoothController;
use App\Http\Controllers\Api\V1\Enterprise\Host\CustomDomainController;
use App\Http\Controllers\Api\V1\Enterprise\Host\LeadController;
use App\Http\Controllers\Api\V1\Enterprise\Host\LeaderboardController;
use App\Http\Controllers\Api\V1\Enterprise\Host\SessionAgendaController;
use App\Http\Controllers\Api\V1\Enterprise\Host\SponsorController as HostSponsorController;
use App\Http\Controllers\Api\V1\Enterprise\Host\SsoConnectionController;
use App\Http\Controllers\Api\V1\Enterprise\Host\TenantSettingsController;
use App\Http\Controllers\Api\V1\Enterprise\Host\TrackController as HostTrackController;
use App\Http\Controllers\Api\V1\Enterprise\Programmatic\EventController as ProgrammaticEventController;
use App\Http\Controllers\Api\V1\Enterprise\SsoController;
use App\Http\Controllers\Api\V1\Events\EventCapabilityController;
use App\Http\Controllers\Api\V1\Events\EventController;
use App\Http\Controllers\Api\V1\Events\EventScheduleController;
use App\Http\Controllers\Api\V1\Events\EventSessionController;
use App\Http\Controllers\Api\V1\Events\EventSpeakerController;
use App\Http\Controllers\Api\V1\Events\EventTemplateController;
use App\Http\Controllers\Api\V1\Events\EventTransitionController;
use App\Http\Controllers\Api\V1\FeatureManagement\FeatureFlagController;
use App\Http\Controllers\Api\V1\Production\BrandKitController;
use App\Http\Controllers\Api\V1\Production\ProductionMixerController;
use App\Http\Controllers\Api\V1\Production\RunOfShowController;
use App\Http\Controllers\Api\V1\Production\SceneController;
use App\Http\Controllers\Api\V1\Registration\PublicRegistrationController;
use App\Http\Controllers\Api\V1\Registration\RegistrantController;
use App\Http\Controllers\Api\V1\Registration\RegistrationFormController;
use App\Http\Controllers\Api\V1\Settings\SystemSettingsController;
use App\Http\Controllers\Api\V1\Settings\TenantSettingsController as TenantConfigController;
use App\Http\Controllers\Api\V1\Studio\GuestJoinController;
use App\Http\Controllers\Api\V1\Studio\StudioController;
use App\Http\Controllers\Api\V1\Studio\StudioGuestLinkController;
use App\Http\Controllers\Api\V1\Studio\StudioParticipantController;
use App\Http\Controllers\Api\V1\Tenancy\TenantController;
use App\Http\Controllers\Api\V1\Workspaces\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    // ---- Public authentication ----
    Route::post('auth/register', RegisterController::class)->middleware('throttle:login');
    Route::post('auth/login', LoginController::class)->middleware('throttle:login');

    // ---- Public guest join (the guest-link token is the credential) ----
    Route::post('studio/guest/{token}/join', GuestJoinController::class)->middleware('throttle:30,1');

    // ---- Public registration (Fase 5 — Webinar). Event resolved unscoped. ----
    Route::get('events/{event}/registration', [PublicRegistrationController::class, 'show'])->middleware('throttle:60,1');
    Route::post('events/{event}/register', [PublicRegistrationController::class, 'store'])->middleware('throttle:20,1');

    // ---- Public checkout (Fase 7 — Commerce). Event resolved unscoped. ----
    Route::get('events/{event}/tickets', [PublicCheckoutController::class, 'tickets'])->middleware('throttle:60,1');
    Route::post('events/{event}/checkout', [PublicCheckoutController::class, 'store'])->middleware('throttle:20,1');
    // Gateway webhook (authenticity from the signature, not the URL).
    Route::post('checkout/webhooks/{account}', [WebhookController::class, 'handle'])->middleware('throttle:120,1');

    // ---- Public certificate verification (Fase 11 — Education). ----
    Route::get('certificates/verify/{code}', [CertificateVerifyController::class, 'verify'])->middleware('throttle:60,1');

    // ---- Enterprise: custom-domain routing resolution + SSO (public, Fase 13) ----
    Route::get('domains/resolve', [DomainResolveController::class, 'resolve'])->middleware('throttle:120,1');
    Route::get('sso/{connection}', [SsoController::class, 'metadata'])->middleware('throttle:60,1');
    Route::post('sso/{connection}/callback', [SsoController::class, 'callback'])->middleware('throttle:20,1');

    // ---- Developer Platform: programmatic API (API-key auth, Fase 13) ----
    Route::middleware(['api.key', 'throttle:120,1'])->prefix('programmatic')->group(function (): void {
        Route::get('events', [ProgrammaticEventController::class, 'index']);
    });

    // ---- Attendee live surface (the join token is the credential) ----
    Route::middleware(['attendee', 'throttle:120,1'])->prefix('attend')->group(function (): void {
        Route::post('presence/join', [AttendeePresenceController::class, 'join']);
        Route::post('presence/leave', [AttendeePresenceController::class, 'leave']);

        Route::get('chat', [AttendeeChatController::class, 'index']);
        Route::post('chat', [AttendeeChatController::class, 'store']);

        Route::get('questions', [AttendeeQuestionController::class, 'index']);
        Route::post('questions', [AttendeeQuestionController::class, 'store']);
        Route::post('questions/{question}/vote', [AttendeeQuestionController::class, 'vote']);

        Route::get('polls', [AttendeePollController::class, 'index']);
        Route::post('polls/{poll}/vote', [AttendeePollController::class, 'vote']);

        Route::get('resources', [AttendeeResourceController::class, 'index']);
        Route::post('resources/{resource}/download', [AttendeeResourceController::class, 'download']);

        Route::get('ctas', [AttendeeCtaController::class, 'index']);
        Route::post('ctas/{cta}/click', [AttendeeCtaController::class, 'click']);

        Route::get('assessment', [AttendeeAssessmentController::class, 'show']);
        Route::post('assessment', [AttendeeAssessmentController::class, 'submit']);

        // ---- Enterprise: agenda, expo, gamification (Fase 12) ----
        Route::get('agenda', [AttendeeAgendaController::class, 'index']);
        Route::get('agenda/mine', [AttendeeAgendaController::class, 'mine']);
        Route::post('agenda/{session}/register', [AttendeeAgendaController::class, 'register']);
        Route::delete('agenda/{session}/register', [AttendeeAgendaController::class, 'unregister']);

        Route::get('expo/sponsors', [AttendeeExpoController::class, 'sponsors']);
        Route::get('expo/booths', [AttendeeExpoController::class, 'booths']);
        Route::post('expo/booths/{booth}/visit', [AttendeeExpoController::class, 'visit']);

        Route::get('gamification', [AttendeeGamificationController::class, 'index']);
    });

    // ---- Authenticated (any tenant) ----
    Route::middleware(['auth:sanctum', 'share.user'])->group(function (): void {
        Route::post('auth/logout', LogoutController::class);
        Route::get('auth/me', MeController::class);
        Route::get('tenants', [TenantController::class, 'index']);

        // ---- Platform super-admin: system-wide configuration (no tenant) ----
        Route::middleware('super.admin')->group(function (): void {
            Route::get('system/settings', [SystemSettingsController::class, 'index']);
            Route::match(['put', 'patch'], 'system/settings', [SystemSettingsController::class, 'update']);
        });

        // ---- Tenant-scoped ----
        Route::middleware(['tenant', 'tenant.required'])->group(function (): void {
            Route::get('workspaces', [WorkspaceController::class, 'index']);
            Route::post('workspaces', [WorkspaceController::class, 'store']);
            Route::get('workspaces/{workspace}', [WorkspaceController::class, 'show']);
            Route::match(['put', 'patch'], 'workspaces/{workspace}', [WorkspaceController::class, 'update']);
            Route::delete('workspaces/{workspace}', [WorkspaceController::class, 'destroy']);

            Route::get('feature-flags', FeatureFlagController::class);

            // ---- RBAC: custom roles & per-user permissions (members.manage) ----
            Route::get('permissions', [PermissionController::class, 'index']);
            Route::get('roles', [RoleController::class, 'index']);
            Route::post('roles', [RoleController::class, 'store']);
            Route::match(['put', 'patch'], 'roles/{role}', [RoleController::class, 'update']);
            Route::delete('roles/{role}', [RoleController::class, 'destroy']);

            Route::get('members', [MemberController::class, 'index']);
            Route::get('members/{member}', [MemberController::class, 'show']);
            Route::put('members/{member}/membership-role', [MemberController::class, 'membershipRole']);
            Route::put('members/{member}/roles', [MemberController::class, 'syncRoles']);
            Route::put('members/{member}/permissions', [MemberController::class, 'syncPermissions']);

            // ---- Tenant configuration / integrations (tenant.manage) ----
            Route::get('settings', [TenantConfigController::class, 'index']);
            Route::match(['put', 'patch'], 'settings', [TenantConfigController::class, 'update']);

            // ---- Events (Fase 1 — Event Core) ----
            Route::get('event-templates', [EventTemplateController::class, 'index']);

            Route::get('events', [EventController::class, 'index']);
            Route::post('events', [EventController::class, 'store']);
            Route::get('events/{event}', [EventController::class, 'show']);
            Route::match(['put', 'patch'], 'events/{event}', [EventController::class, 'update']);
            Route::delete('events/{event}', [EventController::class, 'destroy']);

            Route::post('events/{event}/transition', EventTransitionController::class);

            Route::get('events/{event}/capabilities', [EventCapabilityController::class, 'index']);
            Route::put('events/{event}/capabilities/{capability}', [EventCapabilityController::class, 'update']);

            Route::get('events/{event}/sessions', [EventSessionController::class, 'index']);
            Route::post('events/{event}/sessions', [EventSessionController::class, 'store']);

            Route::get('events/{event}/speakers', [EventSpeakerController::class, 'index']);
            Route::post('events/{event}/speakers', [EventSpeakerController::class, 'store']);

            Route::get('events/{event}/schedule', [EventScheduleController::class, 'index']);
            Route::post('events/{event}/schedule', [EventScheduleController::class, 'store']);

            // ---- Studio (Fase 2 — Studio MVP) ----
            Route::get('events/{event}/studio', [StudioController::class, 'show']);
            Route::post('events/{event}/studio/start', [StudioController::class, 'start']);
            Route::post('events/{event}/studio/end', [StudioController::class, 'end']);

            Route::get('events/{event}/studio/participants', [StudioParticipantController::class, 'index']);
            Route::post('events/{event}/studio/participants', [StudioParticipantController::class, 'store']);
            Route::post('studio-participants/{participant}/move', [StudioParticipantController::class, 'move']);
            Route::post('studio-participants/{participant}/token', [StudioParticipantController::class, 'token']);

            Route::get('events/{event}/studio/guest-links', [StudioGuestLinkController::class, 'index']);
            Route::post('events/{event}/studio/guest-links', [StudioGuestLinkController::class, 'store']);
            Route::delete('studio-guest-links/{link}', [StudioGuestLinkController::class, 'destroy']);

            // ---- Production Engine (Fase 3) ----
            Route::get('events/{event}/studio/scenes', [SceneController::class, 'index']);
            Route::post('events/{event}/studio/scenes', [SceneController::class, 'store']);
            Route::get('scenes/{scene}', [SceneController::class, 'show']);
            Route::match(['put', 'patch'], 'scenes/{scene}', [SceneController::class, 'update']);
            Route::get('scenes/{scene}/versions', [SceneController::class, 'versions']);

            Route::post('events/{event}/studio/preview', [ProductionMixerController::class, 'preview']);
            Route::post('events/{event}/studio/take', [ProductionMixerController::class, 'take']);

            Route::get('events/{event}/studio/brand-kits', [BrandKitController::class, 'index']);
            Route::post('events/{event}/studio/brand-kits', [BrandKitController::class, 'store']);
            Route::post('brand-kits/{kit}/default', [BrandKitController::class, 'setDefault']);

            Route::get('events/{event}/studio/run-of-show', [RunOfShowController::class, 'index']);
            Route::post('events/{event}/studio/run-of-show', [RunOfShowController::class, 'store']);
            Route::post('events/{event}/studio/run-of-show/reorder', [RunOfShowController::class, 'reorder']);

            // ---- Broadcast (Fase 4) ----
            Route::get('events/{event}/studio/destinations', [StreamDestinationController::class, 'index']);
            Route::post('events/{event}/studio/destinations', [StreamDestinationController::class, 'store']);

            Route::get('events/{event}/studio/broadcast', [BroadcastController::class, 'show']);
            Route::post('events/{event}/studio/broadcast/start', [BroadcastController::class, 'start']);
            Route::post('broadcasts/{broadcast}/stop', [BroadcastController::class, 'stop']);
            Route::post('broadcasts/{broadcast}/health', [BroadcastController::class, 'health']);

            // ---- Registration & Engagement host side (Fase 5 — Webinar) ----
            Route::get('events/{event}/registration-form', [RegistrationFormController::class, 'show']);
            Route::put('events/{event}/registration-form', [RegistrationFormController::class, 'save']);
            Route::get('events/{event}/registrations', [RegistrantController::class, 'index']);

            Route::get('events/{event}/engagement/chat', [HostChatController::class, 'index']);
            Route::post('events/{event}/engagement/chat', [HostChatController::class, 'store']);

            Route::get('events/{event}/engagement/questions', [HostQuestionController::class, 'index']);
            Route::post('questions/{question}/answer', [HostQuestionController::class, 'answer']);

            Route::get('events/{event}/engagement/polls', [HostPollController::class, 'index']);
            Route::post('events/{event}/engagement/polls', [HostPollController::class, 'store']);
            Route::post('polls/{poll}/open', [HostPollController::class, 'open']);
            Route::post('polls/{poll}/close', [HostPollController::class, 'close']);

            Route::get('events/{event}/engagement/resources', [HostResourceController::class, 'index']);
            Route::post('events/{event}/engagement/resources', [HostResourceController::class, 'store']);

            // ---- Analytics (Fase 6) ----
            Route::get('events/{event}/analytics/summary', [AnalyticsController::class, 'summary']);
            Route::get('events/{event}/analytics/attendance', [AnalyticsController::class, 'attendance']);
            Route::get('events/{event}/analytics/engagement', [AnalyticsController::class, 'engagement']);
            Route::get('events/{event}/analytics/attribution', [AnalyticsController::class, 'attribution']);

            // ---- Commerce host side (Fase 7) ----
            Route::get('payment-accounts', [PaymentAccountController::class, 'index']);
            Route::put('payment-accounts', [PaymentAccountController::class, 'save']);

            Route::get('events/{event}/commerce/tickets', [HostTicketController::class, 'index']);
            Route::post('events/{event}/commerce/tickets', [HostTicketController::class, 'store']);
            Route::get('events/{event}/commerce/orders', [HostOrderController::class, 'index']);
            Route::get('events/{event}/commerce/ctas', [HostCtaController::class, 'index']);
            Route::post('events/{event}/commerce/ctas', [HostCtaController::class, 'store']);
            Route::get('events/{event}/commerce/revenue', [CommerceReportController::class, 'revenue']);

            // ---- Automation host side (Fase 8) ----
            Route::get('automations', [AutomationController::class, 'index']);
            Route::post('automations', [AutomationController::class, 'store']);
            Route::post('automations/{automation}/active', [AutomationController::class, 'setActive']);
            Route::get('automations/{automation}/runs', [AutomationRunController::class, 'index']);

            // ---- Content: recordings, transcripts, clips (Fase 9) ----
            Route::get('events/{event}/recordings', [RecordingController::class, 'index']);
            Route::post('events/{event}/recordings', [RecordingController::class, 'store']);
            Route::get('recordings/{recording}', [RecordingController::class, 'show']);
            Route::post('recordings/{recording}/complete', [RecordingController::class, 'complete']);

            Route::post('recordings/{recording}/transcribe', [TranscriptController::class, 'store']);
            Route::get('transcripts/{transcript}', [TranscriptController::class, 'show']);

            Route::get('recordings/{recording}/clips', [ClipController::class, 'index']);
            Route::post('recordings/{recording}/clips', [ClipController::class, 'store']);

            // ---- AI: semantic search, RAG Q&A, summaries, architect (Fase 10) ----
            Route::get('events/{event}/content/search', [SemanticSearchController::class, 'search']);
            Route::post('events/{event}/content/ask', [SmartQaController::class, 'ask']);
            Route::post('events/{event}/ai/architect', [EventArchitectController::class, 'design']);
            Route::post('recordings/{recording}/summaries', [ContentSummaryController::class, 'store']);
            Route::get('summaries/{summary}', [ContentSummaryController::class, 'show']);

            // ---- Education host side (Fase 11) ----
            Route::get('events/{event}/education/assessment', [HostAssessmentController::class, 'show']);
            Route::put('events/{event}/education/assessment', [HostAssessmentController::class, 'save']);
            Route::get('events/{event}/education/completion-rule', [CompletionRuleController::class, 'show']);
            Route::put('events/{event}/education/completion-rule', [CompletionRuleController::class, 'save']);
            Route::get('events/{event}/education/submissions', [SubmissionController::class, 'index']);
            Route::get('events/{event}/education/certificates', [HostCertificateController::class, 'index']);
            Route::post('events/{event}/education/certificates/issue', [HostCertificateController::class, 'issue']);

            // ---- Enterprise Events host side (Fase 12) ----
            Route::get('events/{event}/tracks', [HostTrackController::class, 'index']);
            Route::post('events/{event}/tracks', [HostTrackController::class, 'store']);
            Route::get('events/{event}/agenda/sessions', [SessionAgendaController::class, 'index']);
            Route::patch('events/{event}/sessions/{session}/agenda', [SessionAgendaController::class, 'update']);

            Route::get('events/{event}/sponsors', [HostSponsorController::class, 'index']);
            Route::post('events/{event}/sponsors', [HostSponsorController::class, 'store']);
            Route::get('events/{event}/booths', [HostBoothController::class, 'index']);
            Route::post('events/{event}/booths', [HostBoothController::class, 'store']);
            Route::get('events/{event}/leads', [LeadController::class, 'index']);
            Route::get('events/{event}/leaderboard', [LeaderboardController::class, 'index']);

            // ---- Enterprise admin: domains, API keys, SSO, settings, audit (Fase 13) ----
            Route::get('enterprise/domains', [CustomDomainController::class, 'index']);
            Route::post('enterprise/domains', [CustomDomainController::class, 'store']);
            Route::post('enterprise/domains/{domain}/verify', [CustomDomainController::class, 'verify']);
            Route::delete('enterprise/domains/{domain}', [CustomDomainController::class, 'destroy']);

            Route::get('enterprise/api-keys', [ApiKeyController::class, 'index']);
            Route::post('enterprise/api-keys', [ApiKeyController::class, 'store']);
            Route::delete('enterprise/api-keys/{apiKey}', [ApiKeyController::class, 'destroy']);

            Route::get('enterprise/sso-connections', [SsoConnectionController::class, 'index']);
            Route::post('enterprise/sso-connections', [SsoConnectionController::class, 'store']);
            Route::match(['put', 'patch'], 'enterprise/sso-connections/{connection}', [SsoConnectionController::class, 'update']);
            Route::delete('enterprise/sso-connections/{connection}', [SsoConnectionController::class, 'destroy']);

            Route::get('enterprise/settings', [TenantSettingsController::class, 'show']);
            Route::match(['put', 'patch'], 'enterprise/settings', [TenantSettingsController::class, 'update']);

            Route::get('audit-logs', [AuditLogController::class, 'index']);
        });
    });
});
