import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { ApiError } from '@escenia/api-client'
import type {
  AccessTokenPayload,
  ApiResource,
  GuestJoinResult,
  StudioParticipant,
} from '@escenia/types'

vi.mock('@/lib/speakerApi', () => ({ api: { joinStudioAsGuest: vi.fn() } }))

import { api } from '@/lib/speakerApi'
import { useSpeakerStore } from '@/stores/speaker'

const participant: StudioParticipant = {
  id: 'par_1',
  identity: 'guest-par_1',
  name: 'Grace',
  role: 'speaker',
  stage: 'green_room',
  device_checked: false,
  joined_at: null,
  left_at: null,
}

function access(room: string): AccessTokenPayload {
  return {
    token: 'lk.jwt',
    url: 'wss://media.escenia.io',
    identity: 'guest-par_1',
    room,
    expires_at: '2026-10-24T18:00:00Z',
  }
}

function joinResult(sessionRoom: string, accessRoom: string): ApiResource<GuestJoinResult> {
  return {
    data: {
      participant,
      session: {
        id: 'ses_1',
        status: 'live',
        provider: 'livekit',
        room: sessionRoom,
        started_at: null,
        ended_at: null,
      },
      access: access(accessRoom),
    },
  }
}

const key = (linkToken: string): string => `escenia.speaker.${linkToken}`

describe('speaker store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    localStorage.clear()
    vi.clearAllMocks()
  })

  it('joins with the link token and persists the session for that link', async () => {
    vi.mocked(api.joinStudioAsGuest).mockResolvedValue(joinResult('evt-room', 'access-room'))
    const store = useSpeakerStore()

    await store.join('lnk_1', 'Grace')

    expect(api.joinStudioAsGuest).toHaveBeenCalledWith('lnk_1', 'Grace')
    expect(store.isJoined).toBe(true)
    expect(store.session).toEqual({
      linkToken: 'lnk_1',
      participant,
      room: 'evt-room',
      access: access('access-room'),
    })
    expect(JSON.parse(localStorage.getItem(key('lnk_1')) ?? 'null')).toEqual(store.session)
  })

  it('falls back to the access room when the studio session has none', async () => {
    vi.mocked(api.joinStudioAsGuest).mockResolvedValue(joinResult('', 'access-room'))
    const store = useSpeakerStore()

    await store.join('lnk_1', 'Grace')

    expect(store.session?.room).toBe('access-room')
  })

  it('keeps no session when the link is rejected', async () => {
    vi.mocked(api.joinStudioAsGuest).mockRejectedValue(
      new ApiError(410, 'This link is no longer valid.', 'gone'),
    )
    const store = useSpeakerStore()

    await expect(store.join('lnk_x', 'Grace')).rejects.toBeInstanceOf(ApiError)
    expect(store.isJoined).toBe(false)
    expect(localStorage.getItem(key('lnk_x'))).toBeNull()
  })

  it('restores a saved session per link and ignores corrupt data', () => {
    localStorage.setItem(
      key('lnk_1'),
      JSON.stringify({ linkToken: 'lnk_1', participant, room: 'r1', access: access('r1') }),
    )
    localStorage.setItem(key('lnk_bad'), '{not json')
    const store = useSpeakerStore()

    store.activate('lnk_1')
    expect(store.session?.room).toBe('r1')

    store.activate('lnk_2')
    expect(store.isJoined).toBe(false)

    store.activate('lnk_bad')
    expect(store.isJoined).toBe(false)
  })

  it('logout forgets only the current link', () => {
    for (const token of ['lnk_1', 'lnk_2']) {
      localStorage.setItem(
        key(token),
        JSON.stringify({ linkToken: token, participant, room: 'r', access: access('r') }),
      )
    }
    const store = useSpeakerStore()
    store.activate('lnk_1')

    store.logout()

    expect(store.isJoined).toBe(false)
    expect(localStorage.getItem(key('lnk_1'))).toBeNull()
    expect(localStorage.getItem(key('lnk_2'))).not.toBeNull()
  })
})
