import { beforeEach, describe, expect, it, vi } from 'vitest'

import { esUrlDeMedios, useLiveKit, type Dispositivos } from '@/composables/useLiveKit'

import { FakeRoom, RoomEvent, Track } from './fakes/livekit'
import { withSetup } from './withSetup'

vi.mock('livekit-client', () => import('./fakes/livekit'))

const MEDIA_URL = 'wss://media.escenia.io'
const AMBOS: Dispositivos = { camara: true, micro: true }

beforeEach(() => {
  FakeRoom.reset()
})

describe('esUrlDeMedios', () => {
  const cases: Array<[string | undefined, boolean]> = [
    ['wss://media.escenia.io', true],
    ['ws://localhost:7880', true],
    ['wss://fake.media.local', false], // FakeMediaProvider (dev) URL
    ['wss://livekit.local', false],
    ['http://media.escenia.io', false],
    [undefined, false],
  ]

  it.each(cases)('%s → %s', (url, expected) => {
    expect(esUrlDeMedios(url)).toBe(expected)
  })
})

describe('useLiveKit', () => {
  it('connects, then publishes camera + mic and previews the camera track', async () => {
    const { result } = withSetup(() => useLiveKit())
    const video = document.createElement('video')

    await result.conectar(MEDIA_URL, 'jwt', video, AMBOS)

    const room = FakeRoom.last()
    const participant = room.localParticipant
    expect(room.options).toEqual({ adaptiveStream: true, dynacast: true })
    expect(room.connect).toHaveBeenCalledWith(MEDIA_URL, 'jwt')
    const [connectedAt] = room.connect.mock.invocationCallOrder
    const [publishedAt] = participant.enableCameraAndMicrophone.mock.invocationCallOrder
    expect(connectedAt).toBeLessThan(publishedAt ?? 0)
    expect([...participant.onAir]).toEqual([Track.Source.Camera, Track.Source.Microphone])
    expect(participant.cameraTrack.attach).toHaveBeenCalledWith(video)
    expect(result.estado.value).toBe('conectado')
    expect([result.camaraOn.value, result.microOn.value]).toEqual([true, true])

    room.emit(RoomEvent.Disconnected)
    expect(result.estado.value).toBe('idle')
  })

  it('goes live without a preview element', async () => {
    const { result } = withSetup(() => useLiveKit())

    await result.conectar(MEDIA_URL, 'jwt', null, AMBOS)

    expect(FakeRoom.last().localParticipant.cameraTrack.attach).not.toHaveBeenCalled()
    expect(result.estado.value).toBe('conectado')
  })

  it.each([
    [{ camara: true, micro: false }, [Track.Source.Camera]],
    [{ camara: false, micro: true }, [Track.Source.Microphone]],
    [{ camara: false, micro: false }, []],
  ])('publishes only the devices left on in the green room: %o', async (dispositivos, onAir) => {
    const { result } = withSetup(() => useLiveKit())

    await result.conectar(MEDIA_URL, 'jwt', null, dispositivos)

    const participant = FakeRoom.last().localParticipant
    expect([...participant.onAir]).toEqual(onAir)
    expect([...participant.published]).toEqual(onAir)
    expect(participant.enableCameraAndMicrophone).not.toHaveBeenCalled()
    expect(result.camaraOn.value).toBe(dispositivos.camara)
    expect(result.microOn.value).toBe(dispositivos.micro)
    expect(result.estado.value).toBe('conectado')
  })

  it('reports a failed connection and releases the room', async () => {
    FakeRoom.connectWith = () => Promise.reject(new Error('could not establish signal connection'))
    const { result } = withSetup(() => useLiveKit())

    await result.conectar(MEDIA_URL, 'jwt', null, AMBOS)

    expect(result.estado.value).toBe('error')
    expect(result.error.value).toBe('No se pudo conectar al vídeo en directo del evento.')
    expect(FakeRoom.last().localParticipant.onAir.size).toBe(0)
    expect(FakeRoom.last().disconnect).toHaveBeenCalledTimes(1)
  })

  it('toggles the published camera and microphone', async () => {
    const { result } = withSetup(() => useLiveKit())
    await result.conectar(MEDIA_URL, 'jwt', null, AMBOS)
    const participant = FakeRoom.last().localParticipant

    await result.toggleCamara()
    expect(result.camaraOn.value).toBe(false)
    expect(participant.setCameraEnabled).toHaveBeenLastCalledWith(false)
    expect(participant.onAir.has(Track.Source.Camera)).toBe(false)

    await result.toggleCamara()
    expect(participant.setCameraEnabled).toHaveBeenLastCalledWith(true)
    expect(participant.onAir.has(Track.Source.Camera)).toBe(true)

    await result.toggleMicro()
    expect(result.microOn.value).toBe(false)
    expect(participant.setMicrophoneEnabled).toHaveBeenLastCalledWith(false)
    expect(participant.onAir.has(Track.Source.Microphone)).toBe(false)
  })

  it('previews a camera first turned on after going live', async () => {
    const { result } = withSetup(() => useLiveKit())
    const video = document.createElement('video')
    await result.conectar(MEDIA_URL, 'jwt', video, { camara: false, micro: true })
    const participant = FakeRoom.last().localParticipant
    expect(participant.cameraTrack.attach).not.toHaveBeenCalled()

    await result.toggleCamara()

    expect(participant.onAir.has(Track.Source.Camera)).toBe(true)
    expect(participant.cameraTrack.attach).toHaveBeenCalledWith(video)
  })

  it('disconnects when the room view unmounts', async () => {
    const { result, unmount } = withSetup(() => useLiveKit())
    await result.conectar(MEDIA_URL, 'jwt', null, AMBOS)

    unmount()

    expect(FakeRoom.last().disconnect).toHaveBeenCalledTimes(1)
  })
})
