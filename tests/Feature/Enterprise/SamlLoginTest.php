<?php

declare(strict_types=1);

use App\Domain\Enterprise\Contracts\IdentityProvider;
use App\Domain\Enterprise\Models\SsoConnection;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Context\TenantContext;
use App\Infrastructure\Enterprise\Sso\SamlEndpoints;
use Illuminate\Testing\TestResponse;
use OneLogin\Saml2\Utils;
use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;

/**
 * A self-signed IdP signing certificate + key, generated once per run.
 *
 * @return array{key: string, cert: string}
 */
function samlIdpCredentials(): array
{
    static $credentials = null;

    if ($credentials === null) {
        $config = opensslTestConfig();
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA, 'config' => $config]);
        $csr = openssl_csr_new(['commonName' => 'idp.test'], $key, ['config' => $config, 'digest_alg' => 'sha256']);
        $x509 = openssl_csr_sign($csr, null, $key, 30, ['config' => $config, 'digest_alg' => 'sha256']);
        openssl_pkey_export($key, $privateKey, null, ['config' => $config]);
        openssl_x509_export($x509, $certificate);
        $credentials = ['key' => $privateKey, 'cert' => $certificate];
    }

    return $credentials;
}

/**
 * A verified SAML connection for acme.com, with the real (routing) provider.
 *
 * @return array{0: string, 1: SsoConnection}
 */
function makeSamlConnection(): array
{
    config(['sanctum.stateful' => ['localhost:5180'], 'enterprise.identity_provider' => 'real']);
    app()->forgetInstance(IdentityProvider::class);

    [, , $headers] = ssoTenantOwner();
    $ulid = makeVerifiedSsoConnection($headers, [
        'provider' => 'saml',
        'display_name' => 'Acme SAML',
        'config' => [
            'idp_entity_id' => 'https://idp.test/saml',
            'idp_sso_url' => 'https://idp.test/sso',
            'idp_x509_cert' => samlIdpCredentials()['cert'],
        ],
    ]);
    app(TenantContext::class)->forget();

    return [$ulid, SsoConnection::query()->withoutGlobalScopes()->where('ulid', $ulid)->firstOrFail()];
}

/**
 * Start a login from the admin SPA: the AuthnRequest ID, the RelayState and the
 * binding cookie the browser keeps.
 *
 * @return array{request_id: string, state: string, binding: string, authn_request: string}
 */
function startSamlLogin(string $ulid): array
{
    $start = test()->getJson("/api/v1/sso/{$ulid}?redirect_uri=".urlencode("http://localhost:5180/sso/{$ulid}/callback"))
        ->assertOk();

    parse_str((string) parse_url((string) $start->json('data.authorization_url'), PHP_URL_QUERY), $query);
    $authnRequest = (string) gzinflate((string) base64_decode((string) $query['SAMLRequest'], true));
    preg_match('/ ID="([^"]+)"/', $authnRequest, $id);

    return [
        'request_id' => $id[1] ?? '',
        'state' => (string) $query['RelayState'],
        'binding' => (string) $start->getCookie('escenia_sso', false)?->getValue(),
        'authn_request' => $authnRequest,
    ];
}

/**
 * A SAMLResponse as the IdP would post it, signed with its key unless told otherwise.
 *
 * @param  array<string, mixed>  $overrides
 */
function samlResponseFor(SsoConnection $connection, string $inResponseTo, array $overrides = []): string
{
    $o = array_merge([
        'issuer' => 'https://idp.test/saml',
        'audience' => SamlEndpoints::entityId($connection),
        'destination' => SamlEndpoints::acsUrl($connection),
        'name_id' => 'dev@acme.com',
        'email' => 'dev@acme.com',
        'sign_with' => samlIdpCredentials(),
        'algorithm' => XMLSecurityKey::RSA_SHA256,
        'digest' => XMLSecurityDSig::SHA256,
    ], $overrides);

    $now = gmdate('Y-m-d\TH:i:s\Z');
    $before = gmdate('Y-m-d\TH:i:s\Z', time() - 60);
    $until = gmdate('Y-m-d\TH:i:s\Z', time() + 300);
    $id = bin2hex(random_bytes(8));

    $xml = <<<XML
<samlp:Response xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol" xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion" ID="_r{$id}" Version="2.0" IssueInstant="{$now}" Destination="{$o['destination']}" InResponseTo="{$inResponseTo}"><saml:Issuer>{$o['issuer']}</saml:Issuer><samlp:Status><samlp:StatusCode Value="urn:oasis:names:tc:SAML:2.0:status:Success"/></samlp:Status><saml:Assertion ID="_a{$id}" Version="2.0" IssueInstant="{$now}"><saml:Issuer>{$o['issuer']}</saml:Issuer><saml:Subject><saml:NameID Format="urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress">{$o['name_id']}</saml:NameID><saml:SubjectConfirmation Method="urn:oasis:names:tc:SAML:2.0:cm:bearer"><saml:SubjectConfirmationData NotOnOrAfter="{$until}" Recipient="{$o['destination']}" InResponseTo="{$inResponseTo}"/></saml:SubjectConfirmation></saml:Subject><saml:Conditions NotBefore="{$before}" NotOnOrAfter="{$until}"><saml:AudienceRestriction><saml:Audience>{$o['audience']}</saml:Audience></saml:AudienceRestriction></saml:Conditions><saml:AuthnStatement AuthnInstant="{$now}" SessionIndex="_s{$id}"><saml:AuthnContext><saml:AuthnContextClassRef>urn:oasis:names:tc:SAML:2.0:ac:classes:PasswordProtectedTransport</saml:AuthnContextClassRef></saml:AuthnContext></saml:AuthnStatement><saml:AttributeStatement><saml:Attribute Name="email"><saml:AttributeValue>{$o['email']}</saml:AttributeValue></saml:Attribute><saml:Attribute Name="name"><saml:AttributeValue>Dev Example</saml:AttributeValue></saml:Attribute></saml:AttributeStatement></saml:Assertion></samlp:Response>
XML;

    if (is_array($o['sign_with'])) {
        $xml = Utils::addSign($xml, $o['sign_with']['key'], $o['sign_with']['cert'], $o['algorithm'], $o['digest']);
    }

    return base64_encode($xml);
}

/**
 * The IdP's browser POST to the ACS. onelogin reads the request path from
 * $_SERVER['REQUEST_URI'], which Laravel's test client does not populate.
 */
function postToAcs(SsoConnection $connection, string $samlResponse, string $relayState, string $binding): TestResponse
{
    $_SERVER['REQUEST_URI'] = (string) parse_url(SamlEndpoints::acsUrl($connection), PHP_URL_PATH);

    return test()->withUnencryptedCookie('escenia_sso', $binding)->post(
        (string) parse_url(SamlEndpoints::acsUrl($connection), PHP_URL_PATH),
        ['SAMLResponse' => $samlResponse, 'RelayState' => $relayState],
    );
}

afterEach(function () {
    unset($_SERVER['REQUEST_URI']);
});

it('sends an AuthnRequest bound to this login, for this service provider', function () {
    [$ulid, $connection] = makeSamlConnection();

    $login = startSamlLogin($ulid);

    expect($login['request_id'])->toStartWith('_')
        ->and($login['authn_request'])->toContain('Destination="https://idp.test/sso"')
        ->and($login['authn_request'])->toContain('AssertionConsumerServiceURL="'.SamlEndpoints::acsUrl($connection).'"')
        ->and($login['authn_request'])->toContain('<saml:Issuer>'.SamlEndpoints::entityId($connection).'</saml:Issuer>');
});

it('logs in from a signed response and returns to the SPA it started from', function () {
    [$ulid, $connection] = makeSamlConnection();
    $login = startSamlLogin($ulid);

    postToAcs($connection, samlResponseFor($connection, $login['request_id']), $login['state'], $login['binding'])
        ->assertRedirect("http://localhost:5180/sso/{$ulid}/callback?status=ok");

    $this->assertAuthenticatedAs(User::query()->where('email', 'dev@acme.com')->firstOrFail(), 'web');
});

it('rejects responses that are not for this login', function (array $overrides, bool $otherRequest) {
    [$ulid, $connection] = makeSamlConnection();
    $login = startSamlLogin($ulid);
    $inResponseTo = $otherRequest ? '_someone-elses-request' : $login['request_id'];

    postToAcs($connection, samlResponseFor($connection, $inResponseTo, $overrides), $login['state'], $login['binding'])
        ->assertRedirect("http://localhost:5180/sso/{$ulid}/callback?error=sso_failed");

    $this->assertGuest('web');
})->with([
    'signed by another key' => [fn () => ['sign_with' => (function (): array {
        $config = opensslTestConfig();
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA, 'config' => $config]);
        openssl_pkey_export($key, $pem, null, ['config' => $config]);

        return ['key' => $pem, 'cert' => samlIdpCredentials()['cert']];
    })()], false],
    'unsigned' => [fn () => ['sign_with' => null], false],
    'SHA-1 signature' => [fn () => ['algorithm' => XMLSecurityKey::RSA_SHA1, 'digest' => XMLSecurityDSig::SHA1], false],
    'another login' => [fn () => [], true],
    'another service provider' => [fn () => ['audience' => 'https://other-sp.test/metadata'], false],
    'another issuer' => [fn () => ['issuer' => 'https://evil.test/saml'], false],
    'sent elsewhere' => [fn () => ['destination' => 'https://other-sp.test/acs'], false],
    'email outside the domain' => [fn () => ['name_id' => 'victim@victim.org', 'email' => 'victim@victim.org'], false],
]);

it('requires the browser that started the login', function () {
    [$ulid, $connection] = makeSamlConnection();
    $login = startSamlLogin($ulid);

    postToAcs($connection, samlResponseFor($connection, $login['request_id']), $login['state'], 'stolen-elsewhere')
        ->assertRedirect("http://localhost:5180/sso/{$ulid}/callback?error=sso_failed");

    $this->assertGuest('web');
});

it('shows a plain page when the login is unknown', function () {
    [, $connection] = makeSamlConnection();

    postToAcs($connection, samlResponseFor($connection, '_unknown'), 'no-such-state', 'nope')
        ->assertStatus(401)
        ->assertSee('No se pudo iniciar sesión con SSO');
});

it('publishes the service provider metadata', function () {
    [$ulid, $connection] = makeSamlConnection();

    $this->get("/api/v1/sso/{$ulid}/saml/metadata")
        ->assertOk()
        ->assertHeader('Content-Type', 'application/samlmetadata+xml')
        ->assertSee('entityID="'.SamlEndpoints::entityId($connection).'"', false)
        ->assertSee('Location="'.SamlEndpoints::acsUrl($connection).'"', false);
});
