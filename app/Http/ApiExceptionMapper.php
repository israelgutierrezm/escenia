<?php

declare(strict_types=1);

namespace App\Http;

use App\Domain\AccessControl\Exceptions\PrivilegeEscalationException;
use App\Domain\AccessControl\Exceptions\SystemRoleException;
use App\Domain\Agenda\Exceptions\SessionFullException;
use App\Domain\Broadcasting\Exceptions\BroadcastTransitionConflictException;
use App\Domain\Broadcasting\Exceptions\InvalidBroadcastTransitionException;
use App\Domain\Commerce\Exceptions\CheckoutUnavailableException;
use App\Domain\Commerce\Exceptions\InvalidOrderTransitionException;
use App\Domain\Commerce\Exceptions\OrderTransitionConflictException;
use App\Domain\Commerce\Exceptions\WebhookVerificationException;
use App\Domain\Content\Exceptions\InvalidClipRangeException;
use App\Domain\Education\Exceptions\AlreadySubmittedException;
use App\Domain\Education\Exceptions\AssessmentNotAvailableException;
use App\Domain\Engagement\Exceptions\AlreadyVotedException;
use App\Domain\Engagement\Exceptions\InvalidPollTransitionException;
use App\Domain\Engagement\Exceptions\PollNotOpenException;
use App\Domain\Engagement\Exceptions\PollTransitionConflictException;
use App\Domain\Enterprise\Exceptions\DomainVerificationFailedException;
use App\Domain\Enterprise\Exceptions\SsoAuthenticationException;
use App\Domain\Events\Exceptions\CapabilityNotEntitledException;
use App\Domain\Events\Exceptions\EventTransitionConflictException;
use App\Domain\Events\Exceptions\InvalidEventTransitionException;
use App\Domain\Registration\Exceptions\RegistrationClosedException;
use App\Domain\Studio\Exceptions\GuestLinkInvalidException;
use App\Domain\Studio\Exceptions\InvalidParticipantStageTransitionException;
use App\Domain\Studio\Exceptions\ParticipantStageConflictException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Maps exceptions to a stable API error envelope. Internal messages and stack
 * traces are never exposed for unexpected (500) errors outside debug mode.
 */
final class ApiExceptionMapper
{
    /**
     * @return array{0: int, 1: string, 2: string, 3: array<string, array<int, string>>|null}
     */
    public static function map(Throwable $e, bool $debug): array
    {
        return match (true) {
            $e instanceof ValidationException => [422, 'validation_failed', 'The given data was invalid.', $e->errors()],
            $e instanceof InvalidEventTransitionException => [422, 'invalid_transition', $e->getMessage(), null],
            $e instanceof EventTransitionConflictException => [409, 'transition_conflict', $e->getMessage(), null],
            $e instanceof InvalidParticipantStageTransitionException => [422, 'invalid_stage_transition', $e->getMessage(), null],
            $e instanceof ParticipantStageConflictException => [409, 'stage_conflict', $e->getMessage(), null],
            $e instanceof GuestLinkInvalidException => [403, 'guest_link_invalid', $e->getMessage(), null],
            $e instanceof InvalidBroadcastTransitionException => [422, 'invalid_broadcast_transition', $e->getMessage(), null],
            $e instanceof BroadcastTransitionConflictException => [409, 'broadcast_conflict', $e->getMessage(), null],
            $e instanceof CapabilityNotEntitledException => [403, 'capability_not_entitled', $e->getMessage(), null],
            $e instanceof RegistrationClosedException => [422, 'registration_closed', $e->getMessage(), null],
            $e instanceof InvalidPollTransitionException => [422, 'invalid_poll_transition', $e->getMessage(), null],
            $e instanceof PollTransitionConflictException => [409, 'poll_conflict', $e->getMessage(), null],
            $e instanceof PollNotOpenException => [422, 'poll_not_open', $e->getMessage(), null],
            $e instanceof AlreadyVotedException => [409, 'already_voted', $e->getMessage(), null],
            $e instanceof CheckoutUnavailableException => [422, 'checkout_unavailable', $e->getMessage(), null],
            $e instanceof InvalidOrderTransitionException => [422, 'invalid_order_transition', $e->getMessage(), null],
            $e instanceof OrderTransitionConflictException => [409, 'order_conflict', $e->getMessage(), null],
            $e instanceof WebhookVerificationException => [403, 'webhook_verification_failed', $e->getMessage(), null],
            $e instanceof InvalidClipRangeException => [422, 'invalid_clip_range', $e->getMessage(), null],
            $e instanceof AssessmentNotAvailableException => [422, 'assessment_not_available', $e->getMessage(), null],
            $e instanceof AlreadySubmittedException => [409, 'already_submitted', $e->getMessage(), null],
            $e instanceof SessionFullException => [422, 'session_full', $e->getMessage(), null],
            $e instanceof DomainVerificationFailedException => [422, 'domain_verification_failed', $e->getMessage(), null],
            $e instanceof SsoAuthenticationException => [401, 'sso_authentication_failed', $e->getMessage(), null],
            $e instanceof SystemRoleException => [422, 'system_role', $e->getMessage(), null],
            $e instanceof PrivilegeEscalationException => [403, 'privilege_escalation', $e->getMessage(), null],
            $e instanceof AuthenticationException => [401, 'unauthenticated', 'Unauthenticated.', null],
            $e instanceof AuthorizationException => [403, 'forbidden', $e->getMessage() ?: 'This action is unauthorized.', null],
            $e instanceof AccessDeniedHttpException => [403, 'forbidden', $e->getMessage() ?: 'This action is unauthorized.', null],
            $e instanceof ModelNotFoundException,
            $e instanceof NotFoundHttpException => [404, 'not_found', 'Resource not found.', null],
            $e instanceof ThrottleRequestsException => [429, 'too_many_requests', 'Too many requests.', null],
            $e instanceof HttpExceptionInterface => [$e->getStatusCode(), 'http_error', $e->getMessage() ?: 'HTTP error.', null],
            default => [500, 'server_error', $debug ? $e->getMessage() : 'Server error.', null],
        };
    }
}
