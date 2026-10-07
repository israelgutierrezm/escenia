<?php

declare(strict_types=1);

use App\Domain\Enterprise\Contracts\IdentityProvider;
use App\Domain\Enterprise\Models\SsoConnection;
use App\Domain\Identity\Models\User;
use App\Domain\Tenancy\Context\TenantContext;
use App\Domain\Tenancy\Models\Tenant;
use App\Infrastructure\Enterprise\Sso\SamlEndpoints;
use App\Infrastructure\Enterprise\Sso\SamlRedirectBinding;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use OneLogin\Saml2\Utils;
use RobRichards\XMLSecLibs\XMLSecEnc;
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
 * @param  array<string, mixed>  $config  extra connection options (sign_requests, encrypt_assertions, idp_slo_url)
 * @return array{0: string, 1: SsoConnection}
 */
function makeSamlConnection(array $config = []): array
{
    config(['sanctum.stateful' => ['localhost:5180'], 'enterprise.identity_provider' => 'real']);
    app()->forgetInstance(IdentityProvider::class);

    [, , $headers] = ssoTenantOwner();
    $ulid = makeVerifiedSsoConnection($headers, [
        'provider' => 'saml',
        'display_name' => 'Acme SAML',
        'config' => array_merge([
            'idp_entity_id' => 'https://idp.test/saml',
            'idp_sso_url' => 'https://idp.test/sso',
            'idp_x509_cert' => samlIdpCredentials()['cert'],
        ], $config),
    ]);
    app(TenantContext::class)->forget();

    return [$ulid, SsoConnection::query()->withoutGlobalScopes()->where('ulid', $ulid)->firstOrFail()];
}

/**
 * Start a login from the admin SPA: the AuthnRequest ID, the RelayState and the
 * binding cookie the browser keeps.
 *
 * @return array{request_id: string, state: string, binding: string, authn_request: string, url: string}
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
        'url' => (string) $start->json('data.authorization_url'),
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
        'encrypt_to' => null,
        'key_transport' => XMLSecurityKey::RSA_OAEP_MGF1P,
    ], $overrides);

    $now = gmdate('Y-m-d\TH:i:s\Z');
    $before = gmdate('Y-m-d\TH:i:s\Z', time() - 60);
    $until = gmdate('Y-m-d\TH:i:s\Z', time() + 300);
    $id = bin2hex(random_bytes(8));

    $xml = <<<XML
<samlp:Response xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol" xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion" ID="_r{$id}" Version="2.0" IssueInstant="{$now}" Destination="{$o['destination']}" InResponseTo="{$inResponseTo}"><saml:Issuer>{$o['issuer']}</saml:Issuer><samlp:Status><samlp:StatusCode Value="urn:oasis:names:tc:SAML:2.0:status:Success"/></samlp:Status><saml:Assertion ID="_a{$id}" Version="2.0" IssueInstant="{$now}"><saml:Issuer>{$o['issuer']}</saml:Issuer><saml:Subject><saml:NameID Format="urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress">{$o['name_id']}</saml:NameID><saml:SubjectConfirmation Method="urn:oasis:names:tc:SAML:2.0:cm:bearer"><saml:SubjectConfirmationData NotOnOrAfter="{$until}" Recipient="{$o['destination']}" InResponseTo="{$inResponseTo}"/></saml:SubjectConfirmation></saml:Subject><saml:Conditions NotBefore="{$before}" NotOnOrAfter="{$until}"><saml:AudienceRestriction><saml:Audience>{$o['audience']}</saml:Audience></saml:AudienceRestriction></saml:Conditions><saml:AuthnStatement AuthnInstant="{$now}" SessionIndex="_s{$id}"><saml:AuthnContext><saml:AuthnContextClassRef>urn:oasis:names:tc:SAML:2.0:ac:classes:PasswordProtectedTransport</saml:AuthnContextClassRef></saml:AuthnContext></saml:AuthnStatement><saml:AttributeStatement><saml:Attribute Name="email"><saml:AttributeValue>{$o['email']}</saml:AttributeValue></saml:Attribute><saml:Attribute Name="name"><saml:AttributeValue>Dev Example</saml:AttributeValue></saml:Attribute></saml:AttributeStatement></saml:Assertion></samlp:Response>
XML;

    if (is_string($o['encrypt_to'])) {
        $xml = encryptSamlAssertion($xml, $o['encrypt_to'], (string) $o['key_transport']);
    }

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

/**
 * Encrypts the assertion to the SP certificate, as the IdP does when the SP
 * asks for encrypted assertions (AES-256-CBC content, RSA key transport).
 */
function encryptSamlAssertion(string $responseXml, string $spCertificate, string $keyTransport): string
{
    $document = new DOMDocument;
    $document->loadXML($responseXml);
    $assertion = $document->getElementsByTagNameNS('urn:oasis:names:tc:SAML:2.0:assertion', 'Assertion')->item(0);

    $contentKey = new XMLSecurityKey(XMLSecurityKey::AES256_CBC);
    $contentKey->generateSessionKey();
    $transportKey = new XMLSecurityKey($keyTransport, ['type' => 'public']);
    $transportKey->loadKey($spCertificate, false, true);

    $encryption = new XMLSecEnc;
    $encryption->setNode($assertion);
    $encryption->type = XMLSecEnc::Element;
    $encryption->encryptKey($transportKey, $contentKey);
    $encryptedData = $encryption->encryptNode($contentKey);

    $wrapper = $document->createElementNS('urn:oasis:names:tc:SAML:2.0:assertion', 'saml:EncryptedAssertion');
    $encryptedData->parentNode->replaceChild($wrapper, $encryptedData);
    $wrapper->appendChild($encryptedData);

    return (string) $document->saveXML($document->documentElement);
}

/**
 * Logs dev@acme.com in through the ACS and returns the session cookie (as the
 * browser holds it, encrypted).
 */
function loginWithSaml(string $ulid, SsoConnection $connection): string
{
    $login = startSamlLogin($ulid);
    $response = postToAcs($connection, samlResponseFor($connection, $login['request_id']), $login['state'], $login['binding'])
        ->assertRedirect("http://localhost:5180/sso/{$ulid}/callback?status=ok");

    return sessionCookieOf($response);
}

function sessionCookieOf(TestResponse $response): string
{
    return (string) $response->getCookie((string) config('session.cookie'), false)?->getValue();
}

/**
 * The browser following an IdP redirect to our SLO endpoint. onelogin checks
 * the redirect signature against $_GET / QUERY_STRING and the path against
 * REQUEST_URI, which Laravel's test client does not populate.
 */
function followIdpRedirect(string $url, string $sessionCookie): TestResponse
{
    $query = (string) parse_url($url, PHP_URL_QUERY);
    $path = (string) parse_url($url, PHP_URL_PATH);
    parse_str($query, $_GET);
    $_SERVER['QUERY_STRING'] = $query;
    $_SERVER['REQUEST_URI'] = $path.'?'.$query;

    return test()->withUnencryptedCookie((string) config('session.cookie'), $sessionCookie)->get($path.'?'.$query);
}

/**
 * A LogoutRequest (for a NameID) or LogoutResponse (to a request ID) from the
 * IdP to our SLO endpoint, deflated + base64.
 */
function idpLogoutMessage(SsoConnection $connection, string $type, string $nameIdOrRequestId): string
{
    $id = '_l'.bin2hex(random_bytes(8));
    $now = gmdate('Y-m-d\TH:i:s\Z');
    $slo = SamlEndpoints::sloUrl($connection);
    $ns = 'xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol" xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion"';

    $xml = $type === 'SAMLRequest'
        ? "<samlp:LogoutRequest {$ns} ID=\"{$id}\" Version=\"2.0\" IssueInstant=\"{$now}\" Destination=\"{$slo}\"><saml:Issuer>https://idp.test/saml</saml:Issuer><saml:NameID Format=\"urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress\">{$nameIdOrRequestId}</saml:NameID></samlp:LogoutRequest>"
        : "<samlp:LogoutResponse {$ns} ID=\"{$id}\" Version=\"2.0\" IssueInstant=\"{$now}\" Destination=\"{$slo}\" InResponseTo=\"{$nameIdOrRequestId}\"><saml:Issuer>https://idp.test/saml</saml:Issuer><samlp:Status><samlp:StatusCode Value=\"urn:oasis:names:tc:SAML:2.0:status:Success\"/></samlp:Status></samlp:LogoutResponse>";

    return base64_encode((string) gzdeflate($xml));
}

/**
 * The ID inside the deflated + base64 message of a redirect URL.
 */
function samlMessageId(string $url, string $type): string
{
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
    preg_match('/ ID="([^"]+)"/', (string) gzinflate((string) base64_decode((string) $query[$type], true)), $id);

    return $id[1] ?? '';
}

afterEach(function () {
    unset($_SERVER['REQUEST_URI'], $_SERVER['QUERY_STRING']);
    $_GET = [];
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

it('gives each SAML connection its own SP credentials, published and rotatable', function () {
    [$ulid, $connection] = makeSamlConnection();

    expect($connection->sp_certificate)->toStartWith('-----BEGIN CERTIFICATE-----')
        ->and($connection->serviceProviderCredentials()?->privateKey)->toContain('PRIVATE KEY');
    // The private key never leaves encrypted storage.
    expect((string) DB::table('sso_connections')->where('id', $connection->id)->value('sp_private_key'))->not->toContain('PRIVATE KEY');

    $this->get("/api/v1/sso/{$ulid}/saml/metadata")
        ->assertOk()
        ->assertSee('<ds:X509Certificate>', false)
        ->assertSee('use="encryption"', false);

    $owner = User::query()->whereKey(DB::table('tenant_memberships')->where('tenant_id', $connection->tenant_id)->value('user_id'))->firstOrFail();
    Sanctum::actingAs($owner);

    $rotated = $this->withHeaders(['X-Tenant-Id' => (string) Tenant::query()->whereKey($connection->tenant_id)->value('ulid')])
        ->postJson("/api/v1/enterprise/sso-connections/{$ulid}/saml/credentials")
        ->assertOk()
        ->json('data.saml.sp_certificate');

    expect($rotated)->toStartWith('-----BEGIN CERTIFICATE-----')->not->toBe($connection->sp_certificate);
    $this->assertDatabaseHas('audit_logs', ['action' => 'enterprise.sso.sp_credentials_rotated']);
});

it('signs the AuthnRequest with the SP key when the connection asks for it', function () {
    [$ulid, $connection] = makeSamlConnection(['sign_requests' => true]);

    $query = (string) parse_url(startSamlLogin($ulid)['url'], PHP_URL_QUERY);
    [$signed, $signature] = explode('&Signature=', $query);

    expect($signed)->toContain('&SigAlg='.urlencode(SamlRedirectBinding::SIGNATURE_ALGORITHM))
        ->and(openssl_verify($signed, base64_decode(urldecode($signature)), (string) $connection->sp_certificate, OPENSSL_ALGO_SHA256))->toBe(1);
});

it('accepts an assertion encrypted to the SP certificate', function () {
    [$ulid, $connection] = makeSamlConnection(['encrypt_assertions' => true]);
    $login = startSamlLogin($ulid);

    $response = samlResponseFor($connection, $login['request_id'], ['encrypt_to' => $connection->sp_certificate]);

    postToAcs($connection, $response, $login['state'], $login['binding'])
        ->assertRedirect("http://localhost:5180/sso/{$ulid}/callback?status=ok");
    $this->assertAuthenticatedAs(User::query()->where('email', 'dev@acme.com')->firstOrFail(), 'web');
});

it('refuses plain assertions when encryption is required, and RSA-1.5 key transport', function (bool $encrypt, string $transport) {
    [$ulid, $connection] = makeSamlConnection(['encrypt_assertions' => true]);
    $login = startSamlLogin($ulid);

    $response = samlResponseFor($connection, $login['request_id'], $encrypt
        ? ['encrypt_to' => $connection->sp_certificate, 'key_transport' => $transport]
        : []);

    postToAcs($connection, $response, $login['state'], $login['binding'])
        ->assertRedirect("http://localhost:5180/sso/{$ulid}/callback?error=sso_failed");
    $this->assertGuest('web');
})->with([
    'plain assertion' => [false, XMLSecurityKey::RSA_OAEP_MGF1P],
    'RSA-1.5 key transport' => [true, XMLSecurityKey::RSA_1_5],
]);

it('ends the IdP session too when the user signs out (SP-initiated single logout)', function () {
    [$ulid, $connection] = makeSamlConnection(['idp_slo_url' => 'https://idp.test/slo', 'sign_requests' => true]);
    $session = loginWithSaml($ulid, $connection);

    // The SPA signs out: a stateful request carrying the session cookie.
    $logout = $this->withCredentials()
        ->withHeader('Origin', 'http://localhost:5180')
        ->withUnencryptedCookie((string) config('session.cookie'), $session)
        ->postJson('/api/v1/auth/logout', ['return_to' => 'http://localhost:5180/login'])
        ->assertOk();

    $idpLogout = (string) $logout->json('data.sso_logout_url');
    expect($idpLogout)->toStartWith('https://idp.test/slo?SAMLRequest=')->toContain('&Signature=');
    parse_str((string) parse_url($idpLogout, PHP_URL_QUERY), $query);
    expect((string) gzinflate((string) base64_decode((string) $query['SAMLRequest'], true)))
        ->toContain('>dev@acme.com</saml:NameID>')
        ->toContain('SessionIndex>');

    // The IdP ends its session and sends the browser back with a signed LogoutResponse.
    $answer = SamlRedirectBinding::url(
        SamlEndpoints::sloUrl($connection),
        'SAMLResponse',
        idpLogoutMessage($connection, 'SAMLResponse', samlMessageId($idpLogout, 'SAMLRequest')),
        null,
        samlIdpCredentials()['key'],
    );

    followIdpRedirect($answer, sessionCookieOf($logout))
        ->assertRedirect('http://localhost:5180/login?logout=ok');
});

it('signs the user out on a signed LogoutRequest from the IdP (IdP-initiated single logout)', function () {
    [$ulid, $connection] = makeSamlConnection(['idp_slo_url' => 'https://idp.test/slo']);
    $session = loginWithSaml($ulid, $connection);

    $url = SamlRedirectBinding::url(
        SamlEndpoints::sloUrl($connection),
        'SAMLRequest',
        idpLogoutMessage($connection, 'SAMLRequest', 'dev@acme.com'),
        'idp-relay',
        samlIdpCredentials()['key'],
    );

    $location = (string) followIdpRedirect($url, $session)->headers->get('Location');

    expect($location)->toStartWith('https://idp.test/slo?SAMLResponse=')->toContain('RelayState=idp-relay');
    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
    expect((string) gzinflate((string) base64_decode((string) $query['SAMLResponse'], true)))
        ->toContain('InResponseTo="'.samlMessageId($url, 'SAMLRequest').'"');
    $this->assertGuest('web');
});

it('ignores a LogoutRequest that is unsigned or signed with SHA-1', function (bool $sign) {
    [$ulid, $connection] = makeSamlConnection(['idp_slo_url' => 'https://idp.test/slo']);
    $session = loginWithSaml($ulid, $connection);

    $url = SamlEndpoints::sloUrl($connection).'?SAMLRequest='.urlencode(idpLogoutMessage($connection, 'SAMLRequest', 'dev@acme.com'));

    if ($sign) {
        $url .= '&SigAlg='.urlencode(XMLSecurityKey::RSA_SHA1);
        $key = new XMLSecurityKey(XMLSecurityKey::RSA_SHA1, ['type' => 'private']);
        $key->loadKey(samlIdpCredentials()['key']);
        $url .= '&Signature='.urlencode(base64_encode((string) $key->signData((string) parse_url($url, PHP_URL_QUERY))));
    }

    followIdpRedirect($url, $session)->assertStatus(400)->assertSee('Solicitud no válida');
    $this->assertAuthenticatedAs(User::query()->where('email', 'dev@acme.com')->firstOrFail(), 'web');
})->with(['unsigned' => [false], 'SHA-1' => [true]]);
