<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;

/**
 * A URL on one of Escenia's own browser apps: its host[:port] must be a Sanctum
 * stateful domain (the SPAs the session already trusts). SSO only ever sends a
 * browser back to a first-party app — never an open redirect.
 */
final class FirstPartyUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! self::matches($value)) {
            $fail('The :attribute must point to an Escenia application.');
        }
    }

    public static function matches(string $url): bool
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = parse_url($url, PHP_URL_HOST);

        if (! in_array($scheme, ['http', 'https'], true) || ! is_string($host) || $host === '') {
            return false;
        }

        $port = parse_url($url, PHP_URL_PORT);
        $origin = strtolower($host).($port !== null && $port !== false ? ':'.$port : '');

        foreach ((array) config('sanctum.stateful', []) as $domain) {
            if (is_string($domain) && trim($domain) !== '' && Str::is(strtolower(trim($domain)), $origin)) {
                return true;
            }
        }

        return false;
    }
}
