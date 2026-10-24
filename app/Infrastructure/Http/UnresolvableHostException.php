<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

/**
 * The host of an outbound URL does not resolve right now. Unlike a non-public
 * address (a policy refusal) this can be transient, so a caller may try again
 * later; callers that only care about "not allowed" catch the parent class.
 */
final class UnresolvableHostException extends UnsafeOutboundUrlException {}
