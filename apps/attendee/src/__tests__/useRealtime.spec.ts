import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises } from '@vue/test-utils'

import { setAttendeeToken } from '@/lib/attendeeApi'
import { useEventChannel, usePrivateChannel, useViewerCount } from '@/composables/useRealtime'

import { FakeEcho, FakePusher } from './fakes/echo'
import { withSetup } from './withSetup'

vi.mock('laravel-echo', async () => ({ default: (await import('./fakes/echo')).FakeEcho }))
vi.mock('pusher-js', async () => ({ default: (await import('./fakes/echo')).FakePusher }))

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
// environment and join token: whichever test builds Echo, it is built the same
// way. `.env.local` defines VITE_REVERB_* locally, hence the explicit stubs.
beforeEach(() => {
  vi.stubEnv('VITE_REVERB_APP_KEY', 'test-key')
  vi.stubEnv('VITE_REVERB_HOST', 'reverb.escenia.test')
  vi.stubEnv('VITE_REVERB_PORT', '8080')
  vi.stubEnv('VITE_REVERB_SCHEME', 'http')
  setAttendeeToken('tok_attendee')
})

afterEach(() => {
  vi.unstubAllEnvs()
})

describe('attendee realtime', () => {
  it('builds Echo for Reverb and authorizes channels with the attendee token', async () => {
    const { unmount } = withSetup(() => useEventChannel('evt_opts', {}))

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
      endpoint: '/api/v1/attend/broadcasting/auth',
      headers: { 'X-Attendee-Token': 'tok_attendee' },
    })
    expect((window as unknown as { Pusher?: unknown }).Pusher).toBe(FakePusher)
    unmount()
  })

  it('reads the join token on every authorization, so another event needs no reload', async () => {
    const { unmount } = withSetup(() => useEventChannel('evt_switch', {}))
    const instance = await echo()

    // Opening another event's live room in the same tab activates its token.
    setAttendeeToken('tok_other_event')
    expect(instance.authorizationRequest().headers).toEqual({
      'X-Attendee-Token': 'tok_other_event',
    })

    setAttendeeToken(null)
    expect(instance.authorizationRequest().headers).toEqual({})
    unmount()
  })

  it('binds public event handlers by broadcastAs name and leaves the channel on unmount', async () => {
    const onPolls = vi.fn<(payload: unknown) => void>()
    const { unmount } = withSetup(() => useEventChannel('evt_1', { 'polls.changed': onPolls }))
    const instance = await echo()

    required(instance.publicChannels.get('event.evt_1'), 'event.evt_1').fire('.polls.changed', {})
    expect(onPolls).toHaveBeenCalledTimes(1)

    unmount()
    expect(instance.leaveChannel).toHaveBeenCalledWith('event.evt_1')
  })

  it('subscribes to the attendee private channel for networking notifications', async () => {
    const onNotice = vi.fn<(payload: unknown) => void>()
    const { unmount } = withSetup(() =>
      usePrivateChannel('attendee.att_1', { 'networking.notification': onNotice }),
    )
    const instance = await echo()

    required(instance.privateChannels.get('attendee.att_1'), 'attendee.att_1').fire(
      '.networking.notification',
      { message: 'Grace quiere conectar' },
    )
    expect(onNotice).toHaveBeenCalledWith({ message: 'Grace quiere conectar' })

    unmount()
    expect(instance.leave).toHaveBeenCalledWith('attendee.att_1')
  })

  it('counts unique attendees online across both Echo member shapes, excluding the host', async () => {
    const { result, unmount } = withSetup(() => useViewerCount('evt_2'))
    const instance = await echo()
    const presence = required(instance.presences.get('viewers.evt_2'), 'viewers.evt_2')

    presence.emitHere([
      { id: 1, role: 'attendee' },
      { id: '1', role: 'attendee' },
      { id: 'usr_host', role: 'host' },
      { id: 'usr_untagged' }, // only members tagged `attendee` count
      { id: 2, role: 'attendee' },
    ])
    expect(result.enLinea.value).toBe(2)

    presence.emitJoining({ id: 3, info: { role: 'attendee' } })
    presence.emitJoining({ id: 'usr_host', info: { role: 'host' } })
    expect(result.enLinea.value).toBe(3)

    presence.emitLeaving({ id: 2, info: { role: 'attendee' } })
    expect(result.enLinea.value).toBe(2)

    presence.emitHere([{ id: 9, role: 'attendee' }]) // reconnect: `here` resets the set
    expect(result.enLinea.value).toBe(1)

    unmount()
    expect(instance.leave).toHaveBeenCalledWith('viewers.evt_2')
  })

  it('does not subscribe when the view is gone before Echo is ready', async () => {
    const { unmount } = withSetup(() => useEventChannel('evt_early', { 'chat.posted': vi.fn() }))
    unmount()

    const instance = await echo()

    expect(instance.publicChannels.has('event.evt_early')).toBe(false)
  })

  it('stays on polling when Reverb is not configured', async () => {
    vi.stubEnv('VITE_REVERB_APP_KEY', '')

    const { result } = withSetup(() => {
      useEventChannel('evt_off', { 'chat.posted': vi.fn() })
      return useViewerCount('evt_off')
    })
    await flushPromises()

    expect(result.enLinea.value).toBe(0)
    const subscribed = FakeEcho.instances.flatMap((e) => [
      ...e.publicChannels.keys(),
      ...e.presences.keys(),
    ])
    expect(subscribed).not.toContain('event.evt_off')
    expect(subscribed).not.toContain('viewers.evt_off')
  })
})
