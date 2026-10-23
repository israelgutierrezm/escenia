<?php

declare(strict_types=1);

namespace App\Rules;

use App\Infrastructure\Http\OutboundUrlGuard;
use App\Infrastructure\Http\UnsafeOutboundUrlException;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A URL the server will call later (an automation webhook): checked when saved
 * with the same egress guard the call goes through, so a destination on the
 * internal network, or a host that does not resolve, is refused up front. The
 * guard checks again at call time, since DNS can change in between (ADR-037).
 */
final class PublicOutboundUrl implements ValidationRule
{
    public function __construct(
        private readonly OutboundUrlGuard $guard,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('The :attribute must be a URL.');

            return;
        }

        try {
            $this->guard->pinnedOptions($value);
        } catch (UnsafeOutboundUrlException) {
            $fail('The :attribute must point to a public internet address.');
        }
    }
}
