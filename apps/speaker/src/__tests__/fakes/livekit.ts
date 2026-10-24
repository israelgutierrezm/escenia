import { vi } from 'vitest'

// Stand-in for `livekit-client` (no WebRTC, no media server). Specs mock the
// package with this module and import it themselves, so both share one instance:
//   vi.mock('livekit-client', () => import('./fakes/livekit'))

export const RoomEvent = { Disconnected: 'disconnected' } as const

export const Track = { Source: { Camera: 'camera', Microphone: 'microphone' } } as const

type Source = (typeof Track.Source)[keyof typeof Track.Source]

/**
 * The speaker's side of the room. Like LiveKit, enabling a device publishes its
 * track the first time and unmutes it afterwards; disabling it only mutes it.
 */
export class FakeLocalParticipant {
  /** Devices whose track is published. */
  readonly published = new Set<Source>()
  /** Devices published and not muted: what the audience receives. */
  readonly onAir = new Set<Source>()
  readonly cameraTrack = { attach: vi.fn<(element: HTMLMediaElement) => void>() }

  readonly enableCameraAndMicrophone = vi.fn<() => Promise<void>>(async () => {
    this.enable(Track.Source.Camera, true)
    this.enable(Track.Source.Microphone, true)
  })
  readonly setCameraEnabled = vi.fn<(enabled: boolean) => Promise<void>>(async (enabled) => {
    this.enable(Track.Source.Camera, enabled)
  })
  readonly setMicrophoneEnabled = vi.fn<(enabled: boolean) => Promise<void>>(async (enabled) => {
    this.enable(Track.Source.Microphone, enabled)
  })
  readonly getTrackPublication = vi.fn<
    (source: string) => { videoTrack: FakeLocalParticipant['cameraTrack'] } | undefined
  >((source) =>
    source === Track.Source.Camera && this.published.has(source)
      ? { videoTrack: this.cameraTrack }
      : undefined,
  )

  private enable(source: Source, enabled: boolean): void {
    if (enabled) {
      this.published.add(source)
      this.onAir.add(source)
    } else {
      this.onAir.delete(source)
    }
  }
}

export class FakeRoom {
  static instances: FakeRoom[] = []
  /** How the next rooms answer `connect` (a resolved promise unless a spec scripts it). */
  static connectWith: () => Promise<void> = () => Promise.resolve()

  static reset(): void {
    FakeRoom.instances.length = 0
    FakeRoom.connectWith = () => Promise.resolve()
  }

  static last(): FakeRoom {
    const room = FakeRoom.instances.at(-1)
    if (room === undefined) throw new Error('no Room was created')
    return room
  }

  readonly options: unknown
  readonly handlers = new Map<string, () => void>()
  readonly localParticipant = new FakeLocalParticipant()
  readonly connect = vi.fn<(url: string, token: string) => Promise<void>>(() =>
    FakeRoom.connectWith(),
  )
  // Like LiveKit, disconnecting fires `Disconnected` on the room's own handlers.
  readonly disconnect = vi.fn<() => Promise<void>>(() => {
    this.emit(RoomEvent.Disconnected)
    return Promise.resolve()
  })

  constructor(options: unknown) {
    this.options = options
    FakeRoom.instances.push(this)
  }

  on(event: string, handler: () => void): this {
    this.handlers.set(event, handler)
    return this
  }

  /** Fires a room event as LiveKit would. */
  emit(event: string): void {
    this.handlers.get(event)?.()
  }
}

export { FakeRoom as Room }
