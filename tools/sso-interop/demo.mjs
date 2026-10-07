// A browsable SSO demo: an isolated Escenia API (SQLite in this folder) on :8010,
// an OIDC provider (oidc-provider, auto-login) on :8032 and a SAML IdP (samlify,
// auto-login) on :8033 — so the admin SPA (vite on :5180) and the studio SPA
// (vite on :5184), both proxying /api to :8010, can run the real "Continuar con
// SSO" flows in a browser. Test-only credentials: owner@acme.com /
// interop-password-123; SSO user dev@acme.com.
// Usage: node demo.mjs [escenia repo, default ../..]   (Ctrl+C to stop)
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
const SPA = 'http://localhost:5180'
const STUDIO = 'http://localhost:5184'
const OP = 'http://127.0.0.1:8032'
const SAML_IDP = 'http://127.0.0.1:8033'

// The SPAs reach the API through vite's proxy, so the API's public URL (and with
// it the SAML ACS, where the binding cookie must arrive) is the admin origin.
// Cookies are per host, not per port: the session the ACS opens on localhost
// reaches studio too.
const env = {
  ...process.env,
  APP_ENV: 'local',
  APP_DEBUG: 'true',
  APP_URL: SPA,
  DB_CONNECTION: 'sqlite',
  DB_DATABASE: path.join(here, 'demo.sqlite'),
  DB_URL: '',
  CACHE_STORE: 'file',
  SESSION_DRIVER: 'file',
  SESSION_DOMAIN: '',
  QUEUE_CONNECTION: 'sync',
  BROADCAST_CONNECTION: 'log',
  CERTIFICATE_PREGENERATE: 'false',
  ENTERPRISE_IDENTITY_PROVIDER: 'real',
  ENTERPRISE_DOMAIN_VERIFIER: 'fake',
  ENTERPRISE_EGRESS_ALLOW_PRIVATE: 'true',
  SANCTUM_STATEFUL_DOMAINS: 'localhost:5180,127.0.0.1:5180,localhost:5184,127.0.0.1:5184',
}

fs.writeFileSync(env.DB_DATABASE, '')
const php = (args) => {
  const run = spawnSync('php', args, { cwd: repo, env, encoding: 'utf8' })
  if (run.status !== 0) throw new Error(`${args.join(' ')} failed: ${run.stdout}${run.stderr}`)
  return run.stdout
}
php(['artisan', 'migrate', '--force', '--no-interaction'])
for (const seeder of ['PlanSeeder', 'RolePermissionSeeder', 'EventTemplateSeeder']) {
  php(['artisan', 'db:seed', `--class=${seeder}`, '--force', '--no-interaction'])
}
const seed = JSON.parse(php([path.join(here, 'setup.php'), repo, here]).trim().split('\n').pop())

const api = spawn('php', ['-S', '127.0.0.1:8010', path.join(repo, 'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')], {
  cwd: path.join(repo, 'public'), env, stdio: 'inherit',
})

// ---- OIDC provider, auto-login as dev@acme.com -------------------------------
const op = new Provider(OP, {
  clients: [{
    client_id: 'escenia',
    client_secret: 'interop-secret',
    redirect_uris: [`${SPA}/sso/${seed.oidc}/callback`, `${STUDIO}/sso/${seed.oidc}/callback`],
    grant_types: ['authorization_code'],
    response_types: ['code'],
    token_endpoint_auth_method: 'client_secret_basic',
  }],
  claims: { openid: ['sub'], email: ['email', 'email_verified'], profile: ['name'] },
  async findAccount(ctx, sub) {
    return { accountId: sub, async claims() { return { sub, email: 'dev@acme.com', email_verified: true, name: 'Dev Demo' } } }
  },
  features: { devInteractions: { enabled: false } },
  interactions: { url: (ctx, interaction) => `/interaction/${interaction.uid}` },
  cookies: { keys: ['demo-cookie-key'] },
})
const opHandler = op.callback()
http.createServer(async (req, res) => {
  if (req.url.startsWith('/interaction/')) {
    const details = await op.interactionDetails(req, res)
    const grant = new op.Grant({ accountId: 'dev-sub-123', clientId: String(details.params.client_id) })
    grant.addOIDCScope(String(details.params.scope))
    await op.interactionFinished(req, res, { login: { accountId: 'dev-sub-123' }, consent: { grantId: await grant.save() } }, { mergeWithLastSubmission: false })
    return
  }
  opHandler(req, res)
}).listen(8032, '127.0.0.1')

// ---- SAML IdP, auto-login as dev@acme.com (auto-submitting form to the ACS) ---
samlify.setSchemaValidator(xmllint)
const samlLib = samlify.SamlLib ?? samlify.default.SamlLib
const binding = samlify.Constants.namespace.binding
const idp = samlify.IdentityProvider({
  entityID: `${SAML_IDP}/metadata`,
  signingCert: fs.readFileSync(path.join(here, 'idp-cert.pem'), 'utf8'),
  privateKey: fs.readFileSync(path.join(here, 'idp-key.pem'), 'utf8'),
  isAssertionEncrypted: true,
  dataEncryptionAlgorithm: 'http://www.w3.org/2001/04/xmlenc#aes256-cbc',
  keyEncryptionAlgorithm: 'http://www.w3.org/2001/04/xmlenc#rsa-oaep-mgf1p',
  wantAuthnRequestsSigned: true,
  requestSignatureAlgorithm: 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256',
  singleSignOnService: [{ Binding: binding.redirect, Location: `${SAML_IDP}/sso` }],
  singleLogoutService: [{ Binding: binding.redirect, Location: `${SAML_IDP}/slo` }],
  nameIDFormat: ['urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress'],
})
let sp = null
const serviceProvider = async () => {
  sp ??= samlify.ServiceProvider({
    metadata: await (await fetch(`http://127.0.0.1:8010/api/v1/sso/${seed.saml}/saml/metadata`)).text(),
    wantLogoutRequestSigned: true,
    wantLogoutResponseSigned: true,
  })
  return sp
}
const redirectRequest = (url) => {
  const raw = new URL(url, SAML_IDP).search.slice(1)
  return { query: Object.fromEntries(new URLSearchParams(raw)), octetString: raw.split('&Signature=')[0] }
}

http.createServer(async (req, res) => {
  try {
    const target = await serviceProvider()

    if (req.url.startsWith('/sso')) {
      const authn = await idp.parseLoginRequest(target, 'redirect', redirectRequest(req.url))
      const relayState = new URL(req.url, SAML_IDP).searchParams.get('RelayState') ?? ''
      const now = new Date().toISOString()
      const later = new Date(Date.now() + 5 * 60_000).toISOString()
      const assertionId = `_a${crypto.randomUUID()}`
      const responseId = `_r${crypto.randomUUID()}`
      const acsUrl = `${SPA}/api/v1/sso/${seed.saml}/acs`
      const authnStatement = `<saml:AuthnStatement AuthnInstant="${now}" SessionIndex="_s${assertionId}"><saml:AuthnContext><saml:AuthnContextClassRef>urn:oasis:names:tc:SAML:2.0:ac:classes:PasswordProtectedTransport</saml:AuthnContextClassRef></saml:AuthnContext></saml:AuthnStatement>`
      const response = await idp.createLoginResponse(target, authn, 'post', { email: 'dev@acme.com' }, {
        relayState,
        encryptThenSign: true,
        customTagReplacement: (template) => ({
          id: responseId,
          context: samlLib.replaceTagsByValue(template.replace('{AuthnStatement}', authnStatement).replace('{AttributeStatement}', ''), {
            ID: responseId, AssertionID: assertionId, IssueInstant: now, Destination: acsUrl,
            InResponseTo: authn.extract.request.id, Issuer: `${SAML_IDP}/metadata`,
            StatusCode: 'urn:oasis:names:tc:SAML:2.0:status:Success',
            NameIDFormat: 'urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress', NameID: 'dev@acme.com',
            SubjectConfirmationDataNotOnOrAfter: later, SubjectRecipient: acsUrl,
            ConditionsNotBefore: now, ConditionsNotOnOrAfter: later, Audience: `${SPA}/api/v1/sso/${seed.saml}/saml/metadata`,
          }),
        }),
      })
      res.writeHead(200, { 'content-type': 'text/html; charset=utf-8' })
      res.end(`<!doctype html><body onload="document.forms[0].submit()"><p>IdP SAML de prueba: entrando como dev@acme.com…</p>
<form method="post" action="${response.entityEndpoint}"><input type="hidden" name="SAMLResponse" value="${response.context}"><input type="hidden" name="RelayState" value="${relayState.replace(/"/g, '&quot;')}"></form></body>`)
      return
    }

    if (req.url.startsWith('/slo')) {
      // Escenia's logout: end our (non-existent) session and answer it.
      const logoutRequest = await idp.parseLogoutRequest(target, 'redirect', redirectRequest(req.url))
      res.writeHead(302, { location: idp.createLogoutResponse(target, logoutRequest, 'redirect').context })
      res.end()
      return
    }

    res.writeHead(404).end()
  } catch (error) {
    res.writeHead(500, { 'content-type': 'text/plain' }).end(String(error?.stack ?? error))
  }
}).listen(8033, '127.0.0.1')

console.log(`\nSSO demo ready — open ${SPA}/login (vite admin) or ${STUDIO}/login (vite studio) and use dev@acme.com → "Continuar con SSO".`)
console.log(`Owner (password login, Enterprise > SSO): owner@acme.com / interop-password-123`)
process.on('SIGINT', () => { api.kill(); process.exit(0) })
