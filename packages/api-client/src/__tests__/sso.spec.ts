import { describe, expect, it, vi } from 'vitest'
import type { SsoDiscovery, User } from '@escenia/types'

import { finishSsoLogin, redirectToSso, ssoCallbackUrl, SsoLoginError } from '../sso'
import type { SsoApi } from '../sso'

function fakeSsoApi() {
  return {
    ssoDiscover: vi.fn<SsoApi['ssoDiscover']>(),
    ssoStart: vi.fn<SsoApi['ssoStart']>(),
    ssoComplete: vi.fn<SsoApi['ssoComplete']>(),
  } satisfies SsoApi
}

const acme: SsoDiscovery = { id: 'con_1', provider: 'oidc', display_name: 'Acme' }
const ada: User = {
  id: 'usr_1',
  name: 'Ada',
  email: 'ada@acme.com',
  timezone: null,
  locale: null,
  created_at: null,
}

describe('ssoCallbackUrl', () => {
  it('points the IdP back at the SPA callback route of the connection', () => {
    expect(ssoCallbackUrl('https://studio.escenia.test', 'con_1')).toBe(
      'https://studio.escenia.test/sso/con_1/callback',
    )
  })
})

describe('redirectToSso', () => {
  it('starts the connection with the callback URL and sends the browser to the IdP', async () => {
    const api = fakeSsoApi()
    api.ssoStart.mockResolvedValue({
      data: {
        connection: 'con_1',
        provider: 'oidc',
        authorization_url: 'https://idp.acme.test/authorize?state=s1',
        state: 's1',
      },
    })
    const navigate = vi.fn<(url: string) => void>()

    await redirectToSso(api, acme, 'https://studio.escenia.test', navigate)

    expect(api.ssoStart).toHaveBeenCalledWith(
      'con_1',
      'https://studio.escenia.test/sso/con_1/callback',
    )
    expect(navigate).toHaveBeenCalledExactlyOnceWith('https://idp.acme.test/authorize?state=s1')
  })

  it('does not navigate when the start request fails', async () => {
    const api = fakeSsoApi()
    api.ssoStart.mockRejectedValue(new Error('network down'))
    const navigate = vi.fn<(url: string) => void>()

    await expect(redirectToSso(api, acme, 'https://studio.escenia.test', navigate)).rejects.toThrow(
      'network down',
    )
    expect(navigate).not.toHaveBeenCalled()
  })
})

describe('finishSsoLogin', () => {
  it('redeems an OIDC code + state', async () => {
    const api = fakeSsoApi()
    api.ssoComplete.mockResolvedValue({ data: ada })

    await finishSsoLogin(api, 'con_1', { code: 'c0de', state: 's1' })

    expect(api.ssoComplete).toHaveBeenCalledExactlyOnceWith('con_1', {
      code: 'c0de',
      state: 's1',
    })
  })

  it('accepts a SAML return (session opened at the ACS) without calling the API', async () => {
    const api = fakeSsoApi()

    await expect(finishSsoLogin(api, 'con_1', { status: 'ok' })).resolves.toBeUndefined()
    expect(api.ssoComplete).not.toHaveBeenCalled()
  })

  it('reports the IdP error even when a code is present', async () => {
    const api = fakeSsoApi()

    const error = await finishSsoLogin(api, 'con_1', {
      error: 'access_denied',
      code: 'c0de',
      state: 's1',
    }).catch((e: unknown) => e)

    expect(error).toBeInstanceOf(SsoLoginError)
    expect((error as SsoLoginError).message).toBe('access_denied')
    expect(api.ssoComplete).not.toHaveBeenCalled()
  })

  const incomplete: Array<{ label: string; query: Record<string, unknown> }> = [
    { label: 'an empty query', query: {} },
    { label: 'a code without state', query: { code: 'c0de' } },
    { label: 'repeated router query params', query: { code: ['a', 'b'], state: 's1' } },
    { label: 'a non-ok SAML status', query: { status: 'error' } },
  ]

  it.each(incomplete)('rejects $label as incomplete', async ({ query }) => {
    const api = fakeSsoApi()

    const error = await finishSsoLogin(api, 'con_1', query).catch((e: unknown) => e)

    expect(error).toBeInstanceOf(SsoLoginError)
    expect((error as SsoLoginError).message).toBe('incomplete')
    expect(api.ssoComplete).not.toHaveBeenCalled()
  })
})
