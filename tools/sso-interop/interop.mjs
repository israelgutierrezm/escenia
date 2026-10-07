// Escenia SSO interoperability run against independent IdP implementations:
// - OIDC: oidc-provider (an OpenID Certified OP) — discovery, JWKS, PKCE, nonce,
//   client_secret_basic, standard claims.
// - SAML: samlify as the IdP — validates our (signed) AuthnRequests and logout
//   messages against the SAML XSDs, signs responses, encrypts assertions, single logout.
// Escenia runs as a real HTTP server (php -S) on an isolated SQLite database.
// Usage: node interop.mjs [escenia repo, default ../..] [claims in the ID token: yes|no]
import { spawn, spawnSync } from 'node:child_process'
import fs from 'node:fs'
import http from 'node:http'
import path from 'node:path'
import { fileURLToPath } from 'node:url'
import Provider from 'oidc-provider'
import * as samlify from 'samlify'
import * as xmllint from '@authenio/samlify-node-xmllint'

const here = path.dirname(fileURLToPath(import.meta.url))
const repo = path.resolve(process.argv[2] ?? path.join(here, '..', '..'))
const claimsInIdToken = (process.argv[3] ?? 'no') === 'yes'
const API = 'http://127.0.0.1:8031'
const SPA = 'http://127.0.0.1:5199'
const OP = 'http://127.0.0.1:8032'

const env = {
  ...process.env,
  APP_ENV: 'local',
  APP_DEBUG: 'true',
  APP_URL: API,
  DB_CONNECTION: 'sqlite',
  DB_DATABASE: path.join(here, 'escenia.sqlite'),
  DB_URL: '',
  CACHE_STORE: 'file',
  SESSION_DRIVER: 'file',
  SESSION_DOMAIN: '',
  SESSION_SECURE_COOKIE: 'false',
  QUEUE_CONNECTION: 'sync',
  BROADCAST_CONNECTION: 'log',
  CERTIFICATE_PREGENERATE: 'false',
  ENTERPRISE_IDENTITY_PROVIDER: 'real',
  ENTERPRISE_DOMAIN_VERIFIER: 'fake',
  ENTERPRISE_EGRESS_ALLOW_PRIVATE: 'true', // the IdPs run on 127.0.0.1
  SANCTUM_STATEFUL_DOMAINS: '127.0.0.1:5199',
}

const results = []
function check(name, ok, detail = '') {
  results.push({ name, ok })
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${name}${detail ? `  — ${detail}` : ''}`)
}

/** A minimal per-site cookie jar (raw values, as a browser would send them). */
class Jar {
  constructor() { this.cookies = new Map() }
  store(response) {
    for (const line of response.headers.getSetCookie()) {
      const pair = line.split(';')[0]
      const i = pair.indexOf('=')
      const name = pair.slice(0, i).trim()
      const value = pair.slice(i + 1).trim()
      if (value === '' || /expires=Thu, 01[- ]Jan[- ]1970|max-age=0/i.test(line)) this.cookies.delete(name)
      else this.cookies.set(name, value)
    }
  }
  header() { return [...this.cookies].map(([n, v]) => `${n}=${v}`).join('; ') }
  get(name) { return this.cookies.get(name) }
}

async function send(jar, url, { method = 'GET', headers = {}, body } = {}) {
  const cookie = jar.header()
  const response = await fetch(url, { method, body, redirect: 'manual', headers: { ...headers, ...(cookie ? { cookie } : {}) } })
  jar.store(response)
  return response
}

/** Headers of a first-party SPA request (Sanctum stateful + XSRF). */
async function spaHeaders(jar) {
  if (!jar.get('XSRF-TOKEN')) await send(jar, `${API}/sanctum/csrf-cookie`, { headers: { origin: SPA, referer: `${SPA}/` } })
  return {
    accept: 'application/json',
    'content-type': 'application/json',
    origin: SPA,
    referer: `${SPA}/`,
    'x-xsrf-token': decodeURIComponent(jar.get('XSRF-TOKEN') ?? ''),
  }
}

async function me(jar) {
  const response = await send(jar, `${API}/api/v1/auth/me`, { headers: { accept: 'application/json', origin: SPA, referer: `${SPA}/` } })
  return response.status === 200 ? (await response.json()).data : null
}

/** Splits a redirect URL into what samlify needs to verify a signed redirect. */
function redirectRequest(url) {
  const raw = new URL(url).search.slice(1)
  const query = Object.fromEntries(new URLSearchParams(raw))
  return { query, octetString: raw.split('&Signature=')[0] }
}

async function waitFor(url, attempts = 60) {
  for (let i = 0; i < attempts; i++) {
    try { await fetch(url); return } catch { await new Promise((r) => setTimeout(r, 500)) }
  }
  throw new Error(`${url} never came up`)
}

// ---- Escenia on an isolated SQLite database ----------------------------------
fs.writeFileSync(env.DB_DATABASE, '')
const php = (args) => spawnSync('php', args, { cwd: repo, env, encoding: 'utf8' })
let run = php(['artisan', 'migrate', '--force', '--no-interaction'])
if (run.status !== 0) throw new Error(`migrate failed: ${run.stdout}${run.stderr}`)
for (const seeder of ['PlanSeeder', 'RolePermissionSeeder', 'EventTemplateSeeder']) {
  run = php(['artisan', 'db:seed', `--class=${seeder}`, '--force', '--no-interaction'])
  if (run.status !== 0) throw new Error(`seed ${seeder} failed: ${run.stdout}${run.stderr}`)
}
run = php([path.join(here, 'setup.php'), repo, here])
if (run.status !== 0) throw new Error(`setup failed: ${run.stdout}${run.stderr}`)
const seed = JSON.parse(run.stdout.trim().split('\n').pop())

const escenia = spawn('php', ['-S', '127.0.0.1:8031', path.join(repo, 'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')], {
  cwd: path.join(repo, 'public'), env, stdio: 'ignore',
})

// ---- OIDC provider (oidc-provider) -------------------------------------------
const op = new Provider(OP, {
  clients: [{
    client_id: 'escenia',
    client_secret: 'interop-secret',
    redirect_uris: [`${SPA}/sso/${seed.oidc}/callback`],
    grant_types: ['authorization_code'],
    response_types: ['code'],
    token_endpoint_auth_method: 'client_secret_basic',
  }],
  claims: { openid: ['sub'], email: ['email', 'email_verified'], profile: ['name'] },
  conformIdTokenClaims: !claimsInIdToken, // spec-conformant: scope claims only via UserInfo
  async findAccount(ctx, sub) {
    return { accountId: sub, async claims() { return { sub, email: 'dev@acme.com', email_verified: true, name: 'Dev Interop' } } }
  },
  features: { devInteractions: { enabled: false } },
  interactions: { url: (ctx, interaction) => `/interaction/${interaction.uid}` },
  cookies: { keys: ['interop-cookie-key'] },
})
const opHandler = op.callback()
const opServer = http.createServer(async (req, res) => {
  if (req.url.startsWith('/interaction/')) {
    // Auto-login + consent for the test account.
    const details = await op.interactionDetails(req, res)
    const grant = new op.Grant({ accountId: 'dev-sub-123', clientId: String(details.params.client_id) })
    grant.addOIDCScope(String(details.params.scope))
    const grantId = await grant.save()
    await op.interactionFinished(req, res, { login: { accountId: 'dev-sub-123' }, consent: { grantId } }, { mergeWithLastSubmission: false })
    return
  }
  opHandler(req, res)
}).listen(8032, '127.0.0.1')

// ---- SAML IdP (samlify) ------------------------------------------------------
samlify.setSchemaValidator(xmllint)
const idpKey = fs.readFileSync(path.join(here, 'idp-key.pem'), 'utf8')
const idpCert = fs.readFileSync(path.join(here, 'idp-cert.pem'), 'utf8')
const binding = samlify.Constants.namespace.binding

try {
  await waitFor(`${API}/up`)
  const spMetadata = await (await fetch(`${API}/api/v1/sso/${seed.saml}/saml/metadata`)).text()
  // Escenia requires signed logout messages from the IdP (SAML metadata cannot say so).
  const sp = samlify.ServiceProvider({ metadata: spMetadata, wantLogoutRequestSigned: true, wantLogoutResponseSigned: true })
  const idp = samlify.IdentityProvider({
    entityID: 'http://127.0.0.1:8033/metadata',
    signingCert: idpCert,
    privateKey: idpKey,
    isAssertionEncrypted: true,
    dataEncryptionAlgorithm: 'http://www.w3.org/2001/04/xmlenc#aes256-cbc',
    keyEncryptionAlgorithm: 'http://www.w3.org/2001/04/xmlenc#rsa-oaep-mgf1p',
    wantAuthnRequestsSigned: true,
    wantLogoutRequestSigned: true,
    wantLogoutResponseSigned: true,
    requestSignatureAlgorithm: 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256',
    singleSignOnService: [{ Binding: binding.redirect, Location: 'http://127.0.0.1:8033/sso' }],
    singleLogoutService: [{ Binding: binding.redirect, Location: 'http://127.0.0.1:8033/slo' }],
    nameIDFormat: ['urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress'],
  })

  // ---------------------------- OIDC ----------------------------------------
  console.log(`\n== OIDC against oidc-provider (claims ${claimsInIdToken ? 'in the ID token' : 'only via UserInfo'}) ==`)
  {
    const browser = new Jar()
    const idpJar = new Jar()
    const start = await send(browser, `${API}/api/v1/sso/${seed.oidc}?redirect_uri=${encodeURIComponent(`${SPA}/sso/${seed.oidc}/callback`)}`, { headers: { accept: 'application/json' } })
    const startBody = await start.json()
    check('start: discovery + authorization URL', start.status === 200 && String(startBody.data?.authorization_url).startsWith(`${OP}/auth?`), `${start.status}`)

    let url = startBody.data.authorization_url
    for (let i = 0; i < 10 && !url.startsWith(SPA); i++) {
      const hop = await send(idpJar, url)
      const location = hop.headers.get('location')
      if (!location) { console.log(await hop.text()); break }
      url = new URL(location, url).toString()
    }
    const back = new URL(url)
    check('IdP redirects back with code + state', back.searchParams.has('code') && back.searchParams.has('state'), back.searchParams.get('error') ?? '')

    const done = await send(browser, `${API}/api/v1/sso/${seed.oidc}/callback`, {
      method: 'POST',
      headers: await spaHeaders(browser),
      body: JSON.stringify({ code: back.searchParams.get('code'), state: back.searchParams.get('state') }),
    })
    const doneBody = await done.json().catch(() => ({}))
    check('callback: token (Basic auth + PKCE), JWKS signature, claims', done.status === 200 && doneBody.data?.email === 'dev@acme.com', `${done.status} ${JSON.stringify(doneBody).slice(0, 160)}`)
    check('session is established', (await me(browser))?.email === 'dev@acme.com')
  }

  // ---------------------------- SAML ----------------------------------------
  console.log('\n== SAML against samlify (signed requests, encrypted assertions, SLO) ==')
  async function samlLogin() {
    const browser = new Jar()
    const start = await send(browser, `${API}/api/v1/sso/${seed.saml}?redirect_uri=${encodeURIComponent(`${SPA}/sso/${seed.saml}/callback`)}`, { headers: { accept: 'application/json' } })
    const authorization = (await start.json()).data.authorization_url
    const authn = await idp.parseLoginRequest(sp, 'redirect', redirectRequest(authorization))
    const relayState = new URL(authorization).searchParams.get('RelayState')
    // samlify leaves AuthnStatement empty by default; the Web Browser SSO profile
    // requires it (saml-profiles §4.1.4.2), and real IdPs always send it.
    const now = new Date().toISOString()
    const later = new Date(Date.now() + 5 * 60_000).toISOString()
    const responseId = `_r${crypto.randomUUID()}`
    const assertionId = `_a${crypto.randomUUID()}`
    const acsUrl = `${API}/api/v1/sso/${seed.saml}/acs`
    const authnStatement = `<saml:AuthnStatement AuthnInstant="${now}" SessionIndex="_s${assertionId}"><saml:AuthnContext><saml:AuthnContextClassRef>urn:oasis:names:tc:SAML:2.0:ac:classes:PasswordProtectedTransport</saml:AuthnContextClassRef></saml:AuthnContext></saml:AuthnStatement>`
    const attributeStatement = '<saml:AttributeStatement><saml:Attribute Name="email"><saml:AttributeValue xsi:type="xs:string">dev@acme.com</saml:AttributeValue></saml:Attribute><saml:Attribute Name="name"><saml:AttributeValue xsi:type="xs:string">Dev Interop</saml:AttributeValue></saml:Attribute></saml:AttributeStatement>'
    const customTagReplacement = (template) => ({
      id: responseId,
      // XML fragments go in verbatim: replaceTagsByValue would escape them.
      context: (samlify.SamlLib ?? samlify.default.SamlLib).replaceTagsByValue(template.replace('{AuthnStatement}', authnStatement).replace('{AttributeStatement}', attributeStatement), {
        ID: responseId, AssertionID: assertionId, IssueInstant: now, Destination: acsUrl,
        InResponseTo: authn.extract.request.id, Issuer: 'http://127.0.0.1:8033/metadata',
        StatusCode: 'urn:oasis:names:tc:SAML:2.0:status:Success',
        NameIDFormat: 'urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress', NameID: 'dev@acme.com',
        SubjectConfirmationDataNotOnOrAfter: later, SubjectRecipient: acsUrl,
        ConditionsNotBefore: now, ConditionsNotOnOrAfter: later, Audience: `${API}/api/v1/sso/${seed.saml}/saml/metadata`,
      }),
    })
    const response = await idp.createLoginResponse(sp, authn, 'post', { email: 'dev@acme.com' }, { relayState, customTagReplacement, encryptThenSign: true })
    const acs = await send(browser, response.entityEndpoint, {
      method: 'POST',
      headers: { 'content-type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ SAMLResponse: response.context, RelayState: relayState }).toString(),
    })
    return { browser, authn, acs }
  }

  {
    const { browser, authn, acs } = await samlLogin()
    check('IdP verifies our signed, schema-valid AuthnRequest', Boolean(authn.extract?.request?.id))
    check('ACS accepts the signed response with an encrypted assertion', acs.status === 302 && acs.headers.get('location') === `${SPA}/sso/${seed.saml}/callback?status=ok`, `${acs.status} ${acs.headers.get('location')}`)
    check('session is established', (await me(browser))?.email === 'dev@acme.com')

    // SP-initiated single logout.
    const logout = await send(browser, `${API}/api/v1/auth/logout`, { method: 'POST', headers: await spaHeaders(browser), body: JSON.stringify({ return_to: `${SPA}/login` }) })
    const idpLogoutUrl = (await logout.json().catch(() => ({}))).data?.sso_logout_url ?? ''
    check('logout hands over the IdP logout URL', logout.status === 200 && idpLogoutUrl.startsWith('http://127.0.0.1:8033/slo?'), `${logout.status}`)
    const logoutRequest = await idp.parseLogoutRequest(sp, 'redirect', redirectRequest(idpLogoutUrl))
    check('IdP verifies our signed LogoutRequest (NameID + SessionIndex)', logoutRequest.extract?.nameID === 'dev@acme.com' && Boolean(logoutRequest.extract?.sessionIndex), JSON.stringify(logoutRequest.extract?.sessionIndex ?? null))
    const answer = idp.createLogoutResponse(sp, logoutRequest, 'redirect')
    const landed = await send(browser, answer.context)
    check('our SLO accepts the IdP LogoutResponse and returns to the SPA', landed.status === 302 && landed.headers.get('location') === `${SPA}/login?logout=ok`, `${landed.status} ${landed.headers.get('location')}`)
    check('session is gone', (await me(browser)) === null)
  }

  {
    // IdP-initiated single logout.
    const { browser } = await samlLogin()
    const request = idp.createLogoutRequest(sp, 'redirect', { logoutNameID: 'dev@acme.com' })
    const answered = await send(browser, request.context)
    const location = answered.headers.get('location') ?? ''
    check('our SLO answers an IdP LogoutRequest', answered.status === 302 && location.startsWith('http://127.0.0.1:8033/slo?SAMLResponse='), `${answered.status}`)
    const verified = location ? await idp.parseLogoutResponse(sp, 'redirect', redirectRequest(location)) : null
    check('IdP verifies our signed LogoutResponse', verified?.extract?.response?.inResponseTo === request.id, JSON.stringify(verified?.extract?.response ?? null))
    check('session is gone', (await me(browser)) === null)
  }
} catch (error) {
  check('run completed', false, error?.stack ?? String(error))
} finally {
  escenia.kill()
  opServer.close()
  const failed = results.filter((r) => !r.ok).length
  console.log(`\n${results.length - failed}/${results.length} checks passed`)
  process.exitCode = failed === 0 ? 0 : 1
}
