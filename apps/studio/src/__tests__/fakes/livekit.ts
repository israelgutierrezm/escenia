import { vi } from 'vitest'

// Stand-in for `livekit-client` (no WebRTC, no media server). Specs mock the
// package with this module and import it themselves, so both share one instance:
//   vi.mock('livekit-client', () => import('./fakes/livekit'))

export const RoomEvent = {
  TrackSubscribed: 'trackSubscribed',
  TrackUnsubscribed: 'trackUnsubscribed',
  ParticipantDisconnected: 'participantDisconnected',
  Disconnected: 'disconnected',
} as const

export const Track = { Kind: { Video: 'video', Audio: 'audio' } } as const

type Handler = (...args: unknown[]) => void

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
  readonly handlers = new Map<string, Handler>()
  readonly connect = vi.fn<(url: string, token: string) => Promise<void>>(() =>
    FakeRoom.connectWith(),
  )
  readonly disconnect = vi.fn<() => Promise<void>>(() => Promise.resolve())

  constructor(options: unknown) {
    this.options = options
    FakeRoom.instances.push(this)
  }

  on(event: string, handler: Handler): this {
    this.handlers.set(event, handler)
    return this
  }

  /** Fires a room event as LiveKit would. */
  emit(event: string, ...args: unknown[]): void {
    this.handlers.get(event)?.(...args)
  }
}

export { FakeRoom as Room }
