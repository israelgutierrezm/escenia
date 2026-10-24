import { beforeEach, describe, expect, it, vi } from 'vitest'
import { toRaw } from 'vue'

import { useDeviceCheck } from '@/composables/useDeviceCheck'

import { withSetup } from './withSetup'

function fakeStream() {
  const video = { kind: 'video', enabled: true, stop: vi.fn<() => void>() }
  const audio = { kind: 'audio', enabled: true, stop: vi.fn<() => void>() }
  const stream = {
    getVideoTracks: () => [video],
    getAudioTracks: () => [audio],
    getTracks: () => [video, audio],
  } as unknown as MediaStream
  return { stream, video, audio }
}

const getUserMedia = vi.fn<(constraints: MediaStreamConstraints) => Promise<MediaStream>>()

// jsdom has no media devices: define the one call the green room makes.
beforeEach(() => {
  getUserMedia.mockReset()
  Object.defineProperty(navigator, 'mediaDevices', {
    configurable: true,
    value: { getUserMedia },
  })
})

describe('useDeviceCheck', () => {
  it('asks once for camera + microphone and exposes the preview stream', async () => {
    const { stream } = fakeStream()
    getUserMedia.mockResolvedValue(stream)
    const { result } = withSetup(() => useDeviceCheck())

    const pending = result.iniciar()
    expect(result.activando.value).toBe(true)
    await pending

    expect(getUserMedia).toHaveBeenCalledExactlyOnceWith({ video: true, audio: true })
    expect(toRaw(result.stream.value)).toBe(stream)
    expect(result.activando.value).toBe(false)
    expect(result.error.value).toBeNull()

    await result.iniciar() // already previewing
    expect(getUserMedia).toHaveBeenCalledTimes(1)
  })

  it('explains a denied permission without throwing', async () => {
    getUserMedia.mockRejectedValue(new DOMException('Permission denied', 'NotAllowedError'))
    const { result } = withSetup(() => useDeviceCheck())

    await result.iniciar()

    expect(result.stream.value).toBeNull()
    expect(result.activando.value).toBe(false)
    expect(result.error.value).toBe(
      'No se pudo acceder a la cámara o el micrófono. Revisa los permisos del navegador.',
    )
  })

  it('mutes camera and microphone independently on the live tracks', async () => {
    const { stream, video, audio } = fakeStream()
    getUserMedia.mockResolvedValue(stream)
    const { result } = withSetup(() => useDeviceCheck())
    await result.iniciar()

    result.toggleCamara()
    expect(result.camaraOn.value).toBe(false)
    expect(video.enabled).toBe(false)
    expect(audio.enabled).toBe(true)

    result.toggleMicro()
    expect(result.microOn.value).toBe(false)
    expect(audio.enabled).toBe(false)

    result.toggleCamara()
    expect(video.enabled).toBe(true)
  })

  it('releases the devices on detener and when the room unmounts', async () => {
    const first = fakeStream()
    const second = fakeStream()
    getUserMedia.mockResolvedValueOnce(first.stream).mockResolvedValueOnce(second.stream)
    const { result, unmount } = withSetup(() => useDeviceCheck())

    await result.iniciar()
    result.detener()
    expect(first.video.stop).toHaveBeenCalledTimes(1)
    expect(first.audio.stop).toHaveBeenCalledTimes(1)
    expect(result.stream.value).toBeNull()

    await result.iniciar() // can preview again after stopping
    unmount()
    expect(second.video.stop).toHaveBeenCalledTimes(1)
    expect(second.audio.stop).toHaveBeenCalledTimes(1)
  })
})
