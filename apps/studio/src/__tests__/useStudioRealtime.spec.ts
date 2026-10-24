import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises } from '@vue/test-utils'

import { useEventChannel, useStudioChannel, useViewerCount } from '@/composables/useStudioRealtime'

import { FakeEcho, FakePusher } from './fakes/echo'
import { withSetup } from './withSetup'

vi.mock('laravel-echo', async () => ({ default: (await import('./fakes/echo')).FakeEcho }))
vi.mock('pusher-js', async () => ({ default: (await import('./fakes/echo')).FakePusher }))

const XSRF_COOKIE = 'XSRF-TOKEN=eyJpdiI6InRvayJ9%3D%3D'
const XSRF_HEADER = 'eyJpdiI6InRvayJ9=='

/**
 * The Echo singleton the composables share (one per page, so one per spec
 * file), once created and its subscriptions bound.
 */
async function echo(): Promise<FakeEcho> {
  const instance = await vi.waitFor(() => {
    const created = FakeEcho.instances[0]
    if (created === undefined) throw new Error('Echo not created yet')
    return created
  })
  await flushPromises()
  return instance
}

function required<T>(value: T | undefined, what: string): T {
  if (value === undefined) throw new Error(`${what} was not subscribed`)
  return value
}

// The Echo options are read once per page, so every test stubs the same
// environment and cookie: whichever test builds Echo, it is built the same way.
// `.env.local` defines VITE_REVERB_* locally, hence the explicit stubs.
beforeEach(() => {
  vi.stubEnv('VITE_REVERB_APP_KEY', 'test-key')
  vi.stubEnv('VITE_REVERB_HOST', 'reverb.escenia.test')
  vi.stubEnv('VITE_REVERB_PORT', '8080')
  vi.stubEnv('VITE_REVERB_SCHEME', 'http')
  document.cookie = 'theme=dark'
  document.cookie = XSRF_COOKIE
})

afterEach(() => {
  vi.unstubAllEnvs()
})

describe('studio realtime', () => {
  it('builds Echo for Reverb and authorizes private channels with the Sanctum CSRF token', async () => {
    const { unmount } = withSetup(() => useStudioChannel('evt_opts', {}))

    const instance = await echo()

    expect(instance.options).toMatchObject({
      broadcaster: 'reverb',
      key: 'test-key',
      wsHost: 'reverb.escenia.test',
      wsPort: 8080,
      wssPort: 8080,
      forceTLS: false,
      enabledTransports: ['ws', 'wss'],
    })
    expect(instance.authorizationRequest()).toEqual({
      endpoint: '/broadcasting/auth',
      headers: { 'X-XSRF-TOKEN': XSRF_HEADER },
    })
    expect((window as unknown as { Pusher?: unknown }).Pusher).toBe(FakePusher)
    unmount()
  })

  it('reads the CSRF token on every authorization, so a rotated cookie needs no reload', async () => {
    const { unmount } = withSetup(() => useStudioChannel('evt_rotate', {}))
    const instance = await echo()

    // Logging out and back in rotates Sanctum's XSRF-TOKEN cookie.
    document.cookie = 'XSRF-TOKEN=rotated%3D'
    expect(instance.authorizationRequest().headers).toEqual({ 'X-XSRF-TOKEN': 'rotated=' })

    document.cookie = 'XSRF-TOKEN=; expires=Thu, 01 Jan 1970 00:00:00 GMT'
    expect(instance.authorizationRequest().headers).toEqual({})
    unmount()
  })

  it('listens on the private studio channel by broadcastAs name and leaves it on unmount', async () => {
    const onActivity = vi.fn<(payload: unknown) => void>()
    const { unmount } = withSetup(() =>
      useStudioChannel('evt_1', { 'participant.activity': onActivity }),
    )
    const instance = await echo()

    required(instance.privateChannels.get('studio.evt_1'), 'studio.evt_1').fire(
      '.participant.activity',
      {
        id: 'par_1',
      },
    )
    expect(onActivity).toHaveBeenCalledWith({ id: 'par_1' })

    unmount()
    expect(instance.leave).toHaveBeenCalledWith('studio.evt_1')
  })

  it('subscribes the producer to the public event feed and leaves it on unmount', async () => {
    const onChat = vi.fn<(payload: unknown) => void>()
    const { unmount } = withSetup(() => useEventChannel('evt_2', { 'chat.posted': onChat }))
    const instance = await echo()

    required(instance.publicChannels.get('event.evt_2'), 'event.evt_2').fire('.chat.posted', {
      id: 'msg_1',
    })
    expect(onChat).toHaveBeenCalledWith({ id: 'msg_1' })

    unmount()
    expect(instance.leaveChannel).toHaveBeenCalledWith('event.evt_2')
  })

  it('counts unique attendees online, excluding the host', async () => {
    const { result, unmount } = withSetup(() => useViewerCount('evt_3'))
    const instance = await echo()
    const presence = required(instance.presences.get('viewers.evt_3'), 'viewers.evt_3')

    presence.emitHere([
      { id: 1, role: 'attendee' },
      { id: '1', role: 'attendee' },
      { id: 'usr_host', role: 'host' },
      { id: 'usr_untagged' }, // only members tagged `attendee` count
      { id: 2, role: 'attendee' },
    ])
    expect(result.enLinea.value).toBe(2)

    presence.emitJoining({ id: 3, info: { role: 'attendee' } })
    presence.emitJoining({ id: 3, info: { role: 'attendee' } })
    presence.emitJoining({ id: 'usr_cohost', info: { role: 'host' } })
    expect(result.enLinea.value).toBe(3)

    presence.emitLeaving({ id: '1', info: { role: 'attendee' } })
    expect(result.enLinea.value).toBe(2)

    unmount()
    expect(instance.leave).toHaveBeenCalledWith('viewers.evt_3')
  })

  it('does not subscribe when the console is gone before Echo is ready', async () => {
    const { unmount } = withSetup(() =>
      useStudioChannel('evt_early', { 'participant.activity': vi.fn() }),
    )
    unmount()

    const instance = await echo()

    expect(instance.privateChannels.has('studio.evt_early')).toBe(false)
  })

  it('stays on manual refresh when Reverb is not configured', async () => {
    vi.stubEnv('VITE_REVERB_APP_KEY', '')

    const { result } = withSetup(() => {
      useStudioChannel('evt_off', { 'participant.activity': vi.fn() })
      return useViewerCount('evt_off')
    })
    await flushPromises()

    expect(result.enLinea.value).toBe(0)
    const subscribed = FakeEcho.instances.flatMap((e) => [
      ...e.privateChannels.keys(),
      ...e.presences.keys(),
    ])
    expect(subscribed).not.toContain('studio.evt_off')
    expect(subscribed).not.toContain('viewers.evt_off')
  })
})
