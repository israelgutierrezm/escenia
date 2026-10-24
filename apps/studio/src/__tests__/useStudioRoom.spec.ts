import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises } from '@vue/test-utils'

import { esUrlDeMedios, useStudioRoom } from '@/composables/useStudioRoom'

import { FakeRoom, RoomEvent } from './fakes/livekit'
import { withSetup } from './withSetup'

// livekit-client is imported dynamically inside conectar(); the mock routes that
// import to the scriptable FakeRoom.
vi.mock('livekit-client', () => import('./fakes/livekit'))

const MEDIA_URL = 'wss://media.escenia.io'

function track(kind: 'video' | 'audio') {
  return { kind, attach: vi.fn<(el: HTMLMediaElement) => void>(), detach: vi.fn<() => void>() }
}

function participant(identity: string): { identity: string } {
  return { identity }
}

beforeEach(() => {
  FakeRoom.reset()
})

describe('esUrlDeMedios', () => {
  const cases: Array<[string | undefined, boolean]> = [
    ['wss://media.escenia.io', true],
    ['WSS://MEDIA.ESCENIA.IO', true],
    ['ws://localhost:7880', true], // a local LiveKit server is a real media server
    ['wss://fake.media.local', false], // FakeMediaProvider (dev) URL
    ['wss://livekit.local:7880', false],
    ['https://media.escenia.io', false],
    ['', false],
    [undefined, false],
  ]

  it.each(cases)('%s → %s', (url, expected) => {
    expect(esUrlDeMedios(url)).toBe(expected)
  })
})

describe('useStudioRoom', () => {
  it('connects as a subscriber-only host and tracks which identities have video', async () => {
    const { result } = withSetup(() => useStudioRoom())

    await result.conectar(MEDIA_URL, 'jwt')

    const room = FakeRoom.last()
    expect(room.options).toEqual({ adaptiveStream: true })
    expect(room.connect).toHaveBeenCalledWith(MEDIA_URL, 'jwt')
    expect(result.estado.value).toBe('conectado')

    room.emit(RoomEvent.TrackSubscribed, track('video'), {}, participant('spk_1'))
    room.emit(RoomEvent.TrackSubscribed, track('audio'), {}, participant('spk_2'))
    expect(result.conVideo.value).toEqual(['spk_1'])

    room.emit(RoomEvent.Disconnected)
    expect(result.estado.value).toBe('idle')
  })

  it('attaches each video track to its tile, whichever arrives first', async () => {
    const { result } = withSetup(() => useStudioRoom())
    await result.conectar(MEDIA_URL, 'jwt')
    const room = FakeRoom.last()

    const tileFirst = document.createElement('video')
    result.registrarVideo('spk_1', tileFirst)
    const cam1 = track('video')
    room.emit(RoomEvent.TrackSubscribed, cam1, {}, participant('spk_1'))
    expect(cam1.attach).toHaveBeenCalledWith(tileFirst)

    const cam2 = track('video')
    room.emit(RoomEvent.TrackSubscribed, cam2, {}, participant('spk_2'))
    expect(cam2.attach).not.toHaveBeenCalled()
    const tileLater = document.createElement('video')
    result.registrarVideo('spk_2', tileLater)
    expect(cam2.attach).toHaveBeenCalledWith(tileLater)
  })

  it('drops a tile on unsubscribe or when the participant leaves', async () => {
    const { result } = withSetup(() => useStudioRoom())
    await result.conectar(MEDIA_URL, 'jwt')
    const room = FakeRoom.last()
    const cam1 = track('video')
    room.emit(RoomEvent.TrackSubscribed, cam1, {}, participant('spk_1'))
    room.emit(RoomEvent.TrackSubscribed, track('video'), {}, participant('spk_2'))
    expect(result.conVideo.value).toEqual(['spk_1', 'spk_2'])

    room.emit(RoomEvent.TrackUnsubscribed, cam1, {}, participant('spk_1'))
    expect(cam1.detach).toHaveBeenCalledTimes(1)
    expect(result.conVideo.value).toEqual(['spk_2'])

    room.emit(RoomEvent.ParticipantDisconnected, participant('spk_2'))
    expect(result.conVideo.value).toEqual([])
  })

  it('reports a failed connection, releases that room and retries with a fresh one', async () => {
    FakeRoom.connectWith = () => Promise.reject(new Error('could not establish signal connection'))
    const { result } = withSetup(() => useStudioRoom())

    await result.conectar(MEDIA_URL, 'jwt')
    expect(result.estado.value).toBe('error')
    expect(result.error.value).toBe('No se pudo conectar al vídeo del estudio.')
    const failed = FakeRoom.last()
    expect(failed.disconnect).toHaveBeenCalledTimes(1)

    FakeRoom.connectWith = () => Promise.resolve()
    await result.conectar(MEDIA_URL, 'jwt')
    expect(FakeRoom.instances).toHaveLength(2)
    expect(FakeRoom.last()).not.toBe(failed)
    expect(result.estado.value).toBe('conectado')
    expect(result.error.value).toBeNull()
  })

  it('ignores conectar while a connection is in flight', async () => {
    let connected!: () => void
    FakeRoom.connectWith = () =>
      new Promise<void>((resolve) => {
        connected = resolve
      })
    const { result } = withSetup(() => useStudioRoom())

    const first = result.conectar(MEDIA_URL, 'jwt')
    const second = result.conectar(MEDIA_URL, 'jwt')
    await vi.waitFor(() => expect(FakeRoom.instances).toHaveLength(1))
    expect(result.estado.value).toBe('conectando')

    connected()
    await Promise.all([first, second])
    expect(FakeRoom.instances).toHaveLength(1)
    expect(result.estado.value).toBe('conectado')
  })

  it('ignores conectar while connected and disconnects when the console unmounts', async () => {
    const { result, unmount } = withSetup(() => useStudioRoom())
    await result.conectar(MEDIA_URL, 'jwt')
    await result.conectar(MEDIA_URL, 'jwt')
    expect(FakeRoom.instances).toHaveLength(1)
    FakeRoom.last().emit(RoomEvent.TrackSubscribed, track('video'), {}, participant('spk_1'))

    unmount()
    await flushPromises()

    expect(FakeRoom.last().disconnect).toHaveBeenCalledTimes(1)
    expect(result.conVideo.value).toEqual([])
  })
})
