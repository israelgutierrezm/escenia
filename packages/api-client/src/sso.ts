import type { ApiCollection, ApiResource, SsoDiscovery, SsoStart, User } from '@escenia/types'

/** The slice of the API client the SSO login helpers need. */
export interface SsoApi {
  ssoDiscover(email: string): Promise<ApiCollection<SsoDiscovery>>
  ssoStart(connectionId: string, redirectUri: string): Promise<ApiResource<SsoStart>>
  ssoComplete(connectionId: string, data: { code: string; state: string }): Promise<ApiResource<User>>
}

/** The SPA route the IdP (OIDC) or the ACS (SAML) sends the browser back to. */
export function ssoCallbackUrl(origin: string, connectionId: string): string {
  return `${origin}/sso/${connectionId}/callback`
}

/** Sends the browser to the IdP of a connection ("Continuar con SSO"). */
export async function redirectToSso(
  api: SsoApi,
  connection: SsoDiscovery,
  origin: string,
  navigate: (url: string) => void,
): Promise<void> {
  const start = (await api.ssoStart(connection.id, ssoCallbackUrl(origin, connection.id))).data
  navigate(start.authorization_url)
}

/** The SSO return reported a failure (IdP or ACS error) or came back incomplete. */
export class SsoLoginError extends Error {}

/**
 * Completes the login the browser came back with. OIDC brings `code` + `state`,
 * redeemed here (the binding cookie travels by itself); SAML already opened the
 * session at the ACS and brings `status=ok`. Throws on any failure.
 */
export async function finishSsoLogin(api: SsoApi, connectionId: string, query: Record<string, unknown>): Promise<void> {
  const text = (value: unknown): string => (typeof value === 'string' ? value : '')

  if (text(query.error) !== '') {
    throw new SsoLoginError(text(query.error))
  }

  const code = text(query.code)
  const state = text(query.state)

  if (code !== '' && state !== '') {
    await api.ssoComplete(connectionId, { code, state })
    return
  }

  if (text(query.status) !== 'ok') {
    throw new SsoLoginError('incomplete')
  }
}
