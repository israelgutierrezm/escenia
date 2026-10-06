<?php

declare(strict_types=1);

use App\Domain\Enterprise\DTOs\SsoAuthorizationRequest;
use App\Domain\Enterprise\DTOs\SsoCallback;
use App\Domain\Enterprise\Enums\SsoProvider;
use App\Domain\Enterprise\Exceptions\SsoAuthenticationException;
use App\Domain\Enterprise\Models\SsoConnection;
use App\Domain\Tenancy\Enums\TenantRole;
use App\Infrastructure\Enterprise\Sso\OidcIdentityProvider;
use Firebase\JWT\JWT;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * A minimal OpenSSL config so key generation also works where PHP ships
 * without one (Windows builds). Keys are generated per run: no key material
 * lives in the repo.
 */
function oidcOpensslConfig(): string
{
    $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'escenia-test-openssl.cnf';
    // PHP checks the key length even for EC keys, reading it from the config.
    $contents = "[ req ]\ndefault_bits = 2048\ndistinguished_name = dn\n[ dn ]\n";

    if (! is_file($path) || file_get_contents($path) !== $contents) {
        file_put_contents($path, $contents);
    }

    return $path;
}

function oidcBase64Url(string $bytes): string
{
    return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
}

/**
 * An RSA signing key for the fake IdP. The JWK carries no `alg`, like Entra ID:
 * the adapter must infer it.
 *
 * @return array{private: string, public: string, jwk: array<string, string>}
 */
function oidcRsaKey(string $kid = 'key-1'): array
{
    $config = oidcOpensslConfig();
    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA, 'config' => $config]);
    openssl_pkey_export($key, $private, null, ['config' => $config]);
    $details = openssl_pkey_get_details($key);

    return [
        'private' => $private,
        'public' => $details['key'],
        'jwk' => [
            'kty' => 'RSA', 'use' => 'sig', 'kid' => $kid,
            'n' => oidcBase64Url($details['rsa']['n']), 'e' => oidcBase64Url($details['rsa']['e']),
        ],
    ];
}

/**
 * @return array{private: string, jwk: array<string, string>}
 */
function oidcEcKey(string $kid = 'ec-1'): array
{
    $config = oidcOpensslConfig();
    $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1', 'config' => $config]);
    openssl_pkey_export($key, $private, null, ['config' => $config]);
    $ec = openssl_pkey_get_details($key)['ec'];

    return [
        'private' => $private,
        'jwk' => ['kty' => 'EC', 'crv' => 'P-256', 'kid' => $kid, 'x' => oidcBase64Url($ec['x']), 'y' => oidcBase64Url($ec['y'])],
    ];
}

/**
 * @param  array<string, mixed>  $config
 */
function oidcConnection(array $config = []): SsoConnection
{
    return (new SsoConnection)->forceFill([
        'ulid' => '01JTESTSSOCONNECTION0000000',
        'provider' => SsoProvider::Oidc,
        'display_name' => 'IdP',
        'domain' => 'acme.com',
        'config' => array_merge([
            'issuer' => 'https://idp.test',
            'client_id' => 'escenia-client',
            'client_secret' => 's3cret',
            'authorization_endpoint' => 'https://idp.test/authorize',
            'token_endpoint' => 'https://idp.test/token',
            'jwks_uri' => 'https://idp.test/jwks',
        ], $config),
        'default_role' => TenantRole::Member,
        'is_active' => true,
    ]);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function oidcClaims(array $overrides = []): array
{
    return array_merge([
        'iss' => 'https://idp.test',
        'aud' => 'escenia-client',
        'sub' => 'idp-user-42',
        'email' => 'Dev@Acme.com',
        'email_verified' => true,
        'name' => 'Dev Example',
        'nonce' => 'expected-nonce',
        'iat' => time(),
        'exp' => time() + 300,
    ], $overrides);
}

function oidcCallback(): SsoCallback
{
    return new SsoCallback(
        code: 'auth-code',
        redirectUri: 'https://app.test/sso/callback',
        nonce: 'expected-nonce',
        codeVerifier: 'the-pkce-verifier',
    );
}

/**
 * Fake the IdP: the token endpoint answers with $idToken, the JWKS publishes $jwks.
 *
 * @param  list<array<string, string>>  $jwks
 */
function fakeOidcIdp(string $idToken, array $jwks): void
{
    Http::fake([
        'idp.test/token' => Http::response(['id_token' => $idToken, 'access_token' => 'at', 'token_type' => 'Bearer']),
        'idp.test/jwks' => Http::response(['keys' => $jwks]),
    ]);
}

it('builds the authorization URL with state, nonce and an S256 PKCE challenge', function () {
    $url = app(OidcIdentityProvider::class)->authorizationUrl(oidcConnection(), new SsoAuthorizationRequest(
        redirectUri: 'https://app.test/sso/callback',
        state: 'st4te',
        nonce: 'n0nce',
        codeChallenge: 'ch4llenge',
    ));

    expect($url)->toStartWith('https://idp.test/authorize?');
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

    expect($query)->toMatchArray([
        'response_type' => 'code',
        'client_id' => 'escenia-client',
        'redirect_uri' => 'https://app.test/sso/callback',
        'scope' => 'openid email profile',
        'state' => 'st4te',
        'nonce' => 'n0nce',
        'code_challenge' => 'ch4llenge',
        'code_challenge_method' => 'S256',
    ]);
});

it('accepts a correctly signed id_token and redeems the code with the PKCE verifier', function () {
    $key = oidcRsaKey();
    fakeOidcIdp(JWT::encode(oidcClaims(), $key['private'], 'RS256', 'key-1'), [$key['jwk']]);

    $identity = app(OidcIdentityProvider::class)->verifyCallback(oidcConnection(), oidcCallback());

    expect($identity->subject)->toBe('idp-user-42')
        ->and($identity->email)->toBe('dev@acme.com')
        ->and($identity->name)->toBe('Dev Example');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://idp.test/token'
        && $request['grant_type'] === 'authorization_code'
        && $request['code'] === 'auth-code'
        && $request['code_verifier'] === 'the-pkce-verifier'
        && $request['redirect_uri'] === 'https://app.test/sso/callback');
});

it('infers the algorithm of an alg-less RSA key from the token, within the RSA family', function () {
    $key = oidcRsaKey();
    fakeOidcIdp(JWT::encode(oidcClaims(), $key['private'], 'RS384', 'key-1'), [$key['jwk']]);

    expect(app(OidcIdentityProvider::class)->verifyCallback(oidcConnection(), oidcCallback())->subject)
        ->toBe('idp-user-42');
});

it('accepts an ES256 id_token signed with an EC key', function () {
    $key = oidcEcKey();
    fakeOidcIdp(JWT::encode(oidcClaims(), $key['private'], 'ES256', 'ec-1'), [$key['jwk']]);

    expect(app(OidcIdentityProvider::class)->verifyCallback(oidcConnection(), oidcCallback())->subject)
        ->toBe('idp-user-42');
});

it('rejects an id_token whose claims do not match the login', function (array $overrides) {
    $key = oidcRsaKey();
    fakeOidcIdp(JWT::encode(oidcClaims($overrides), $key['private'], 'RS256', 'key-1'), [$key['jwk']]);

    app(OidcIdentityProvider::class)->verifyCallback(oidcConnection(), oidcCallback());
})->throws(SsoAuthenticationException::class)->with([
    'foreign issuer' => [['iss' => 'https://evil.test']],
    'other audience' => [['aud' => 'someone-else']],
    'nonce of another login' => [['nonce' => 'stale-nonce']],
    'expired' => [['exp' => time() - 600, 'iat' => time() - 900]],
    'unverified email' => [['email_verified' => false]],
    'several audiences without azp' => [['aud' => ['escenia-client', 'other-app']]],
    'azp naming another client' => [['azp' => 'other-app']],
    'empty subject' => [['sub' => '']],
    'no email' => [['email' => '']],
]);

it('requires the exp and iat claims', function (string $claim) {
    $key = oidcRsaKey();
    $claims = oidcClaims();
    unset($claims[$claim]);
    fakeOidcIdp(JWT::encode($claims, $key['private'], 'RS256', 'key-1'), [$key['jwk']]);

    app(OidcIdentityProvider::class)->verifyCallback(oidcConnection(), oidcCallback());
})->throws(SsoAuthenticationException::class)->with(['exp', 'iat']);

it('accepts several audiences when azp names this client', function () {
    $key = oidcRsaKey();
    $claims = oidcClaims(['aud' => ['escenia-client', 'other-app'], 'azp' => 'escenia-client']);
    fakeOidcIdp(JWT::encode($claims, $key['private'], 'RS256', 'key-1'), [$key['jwk']]);

    expect(app(OidcIdentityProvider::class)->verifyCallback(oidcConnection(), oidcCallback())->subject)
        ->toBe('idp-user-42');
});

it('rejects an id_token signed by a key the IdP does not publish', function () {
    $published = oidcRsaKey();
    $forger = oidcRsaKey();
    fakeOidcIdp(JWT::encode(oidcClaims(), $forger['private'], 'RS256', 'key-1'), [$published['jwk']]);

    app(OidcIdentityProvider::class)->verifyCallback(oidcConnection(), oidcCallback());
})->throws(SsoAuthenticationException::class);

it('rejects unsigned and symmetric tokens (algorithm confusion)', function (string $alg) {
    $key = oidcRsaKey();
    $token = match ($alg) {
        // `none`: header + payload with an empty signature.
        'none' => oidcBase64Url((string) json_encode(['alg' => 'none', 'typ' => 'JWT', 'kid' => 'key-1'])).'.'
            .oidcBase64Url((string) json_encode(oidcClaims())).'.',
        // HMAC keyed with the IdP's *public* key: passes if the verifier trusts the header.
        default => JWT::encode(oidcClaims(), $key['public'], $alg, 'key-1'),
    };
    fakeOidcIdp($token, [$key['jwk']]);

    app(OidcIdentityProvider::class)->verifyCallback(oidcConnection(), oidcCallback());
})->throws(SsoAuthenticationException::class)->with(['none', 'HS256']);

it('rejects a code the token endpoint refuses', function () {
    Http::fake(['idp.test/token' => Http::response(['error' => 'invalid_grant'], 400)]);

    app(OidcIdentityProvider::class)->verifyCallback(oidcConnection(), oidcCallback());
})->throws(SsoAuthenticationException::class);

it('refetches the key set once when the signing key rotated', function () {
    $old = oidcRsaKey('key-old');
    $new = oidcRsaKey('key-new');
    Http::fake([
        'idp.test/token' => Http::response(['id_token' => JWT::encode(oidcClaims(), $new['private'], 'RS256', 'key-new')]),
        'idp.test/jwks' => Http::sequence()
            ->push(['keys' => [$old['jwk']]])
            ->push(['keys' => [$old['jwk'], $new['jwk']]]),
    ]);

    expect(app(OidcIdentityProvider::class)->verifyCallback(oidcConnection(), oidcCallback())->subject)
        ->toBe('idp-user-42');

    Http::assertSentCount(3); // token + stale JWKS + refreshed JWKS
});

it('caches the key set between logins', function () {
    $key = oidcRsaKey();
    fakeOidcIdp(JWT::encode(oidcClaims(), $key['private'], 'RS256', 'key-1'), [$key['jwk']]);
    $provider = app(OidcIdentityProvider::class);

    $provider->verifyCallback(oidcConnection(), oidcCallback());
    $provider->verifyCallback(oidcConnection(), oidcCallback());

    Http::assertSentCount(3); // two token exchanges, one JWKS fetch
});

it('refuses a connection that is not OIDC', function () {
    Http::fake();
    $connection = oidcConnection();
    $connection->provider = SsoProvider::Saml;

    try {
        app(OidcIdentityProvider::class)->verifyCallback($connection, oidcCallback());
    } finally {
        Http::assertNothingSent();
    }
})->throws(SsoAuthenticationException::class);

it('refuses an incomplete configuration without calling the IdP', function () {
    Http::fake();

    try {
        app(OidcIdentityProvider::class)->verifyCallback(oidcConnection(['jwks_uri' => '']), oidcCallback());
    } finally {
        Http::assertNothingSent();
    }
})->throws(SsoAuthenticationException::class);
