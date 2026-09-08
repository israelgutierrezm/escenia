<?php

declare(strict_types=1);

namespace App\Http;

use App\Domain\Events\Exceptions\CapabilityNotEntitledException;
use App\Domain\Events\Exceptions\EventTransitionConflictException;
use App\Domain\Events\Exceptions\InvalidEventTransitionException;
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
            $e instanceof CapabilityNotEntitledException => [403, 'capability_not_entitled', $e->getMessage(), null],
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
