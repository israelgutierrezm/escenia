<?php

declare(strict_types=1);

namespace App\Infrastructure\Enterprise\Sso;

use App\Domain\Enterprise\Exceptions\SsoAuthenticationException;
use App\Infrastructure\Http\OutboundUrlGuard;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Fetches and caches an IdP's published JSON Web Key Set, through the egress
 * guard (the URL is tenant-configured). Keys rotate, so a token signed with an
 * unknown `kid` may force one refetch — at most once per cooldown, so forged
 * `kid`s cannot make us hammer the IdP.
 */
final class OidcJwksProvider
{
    private const CACHE_SECONDS = 3600;

    private const REFRESH_COOLDOWN_SECONDS = 60;

    public function __construct(
        private readonly Cache $cache,
        private readonly OutboundUrlGuard $guard,
    ) {}

    /**
     * @return list<array<mixed>>
     */
    public function keys(string $jwksUri): array
    {
        $cached = self::jwkList($this->cache->get($this->key($jwksUri)));

        return $cached !== [] ? $cached : $this->fetch($jwksUri);
    }

    /**
     * A fresh key set, or null while the refresh cooldown is running.
     *
     * @return list<array<mixed>>|null
     */
    public function refresh(string $jwksUri): ?array
    {
        if (! $this->cache->add($this->key($jwksUri).':refreshed', true, self::REFRESH_COOLDOWN_SECONDS)) {
            return null;
        }

        return $this->fetch($jwksUri);
    }

    /**
     * @return list<array<mixed>>
     */
    private function fetch(string $jwksUri): array
    {
        try {
            $keys = Http::withOptions($this->guard->pinnedOptions($jwksUri))
                ->acceptJson()->timeout(10)->get($jwksUri)->throw()->json('keys');
        } catch (Throwable $e) {
            Log::notice('OIDC key set fetch failed.', ['jwks_uri' => $jwksUri, 'error' => $e->getMessage()]);

            throw new SsoAuthenticationException;
        }

        $jwks = self::jwkList($keys);

        if ($jwks === []) {
            throw new SsoAuthenticationException;
        }

        $this->cache->put($this->key($jwksUri), $jwks, self::CACHE_SECONDS);

        return $jwks;
    }

    /**
     * The JWK objects in a `keys` member; anything else is dropped.
     *
     * @return list<array<mixed>>
     */
    private static function jwkList(mixed $keys): array
    {
        $jwks = [];

        foreach (is_array($keys) ? $keys : [] as $jwk) {
            if (is_array($jwk)) {
                $jwks[] = $jwk;
            }
        }

        return $jwks;
    }

    private function key(string $jwksUri): string
    {
        return 'sso:jwks:'.hash('sha256', $jwksUri);
    }
}
