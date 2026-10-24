import { vi } from 'vitest'

// Stand-ins for `laravel-echo` and `pusher-js` (no WebSocket). Specs mock the
// packages with these classes and import this module themselves, so both share
// one instance:
//   vi.mock('laravel-echo', async () => ({ default: (await import('./fakes/echo')).FakeEcho }))
//   vi.mock('pusher-js', async () => ({ default: (await import('./fakes/echo')).FakePusher }))

type Listener = (payload: unknown) => void

export interface Member {
  id: string | number
  role?: string
  info?: { role?: string }
}

/** The channel-authorization options Echo hands to pusher-js. */
interface AuthOptions {
  authEndpoint?: string
  auth?: { headers?: Record<string, string> }
  channelAuthorization?: {
    endpoint?: string
    headers?: Record<string, string>
    headersProvider?: () => Record<string, string>
  }
}

export class FakeChannel {
  readonly listeners = new Map<string, Listener>()

  listen(event: string, callback: Listener): this {
    this.listeners.set(event, callback)
    return this
  }

  fire(event: string, payload: unknown): void {
    const callback = this.listeners.get(event)
    if (callback === undefined) throw new Error(`nothing listens to ${event}`)
    callback(payload)
  }
}

export class FakePresence {
  private onHere: (members: Member[]) => void = () => undefined
  private onJoining: (member: Member) => void = () => undefined
  private onLeaving: (member: Member) => void = () => undefined

  here(callback: (members: Member[]) => void): this {
    this.onHere = callback
    return this
  }

  joining(callback: (member: Member) => void): this {
    this.onJoining = callback
    return this
  }

  leaving(callback: (member: Member) => void): this {
    this.onLeaving = callback
    return this
  }

  emitHere(members: Member[]): void {
    this.onHere(members)
  }

  emitJoining(member: Member): void {
    this.onJoining(member)
  }

  emitLeaving(member: Member): void {
    this.onLeaving(member)
  }
}

export class FakeEcho {
  static instances: FakeEcho[] = []

  readonly options: Record<string, unknown>
  readonly publicChannels = new Map<string, FakeChannel>()
  readonly privateChannels = new Map<string, FakeChannel>()
  readonly presences = new Map<string, FakePresence>()
  readonly leave = vi.fn<(name: string) => void>()
  readonly leaveChannel = vi.fn<(name: string) => void>()

  constructor(options: Record<string, unknown>) {
    this.options = options
    FakeEcho.instances.push(this)
  }

  channel(name: string): FakeChannel {
    const channel = new FakeChannel()
    this.publicChannels.set(name, channel)
    return channel
  }

  private(name: string): FakeChannel {
    const channel = new FakeChannel()
    this.privateChannels.set(name, channel)
    return channel
  }

  join(name: string): FakePresence {
    const presence = new FakePresence()
    this.presences.set(name, presence)
    return presence
  }

  /**
   * The endpoint and headers pusher-js would use for the next private/presence
   * channel authorization, resolved from the options the way pusher-js 8 does
   * (`channelAuthorization`, with `headersProvider` called per request, wins
   * over the legacy `authEndpoint` + `auth.headers`, fixed when Echo is built).
   */
  authorizationRequest(): { endpoint: string; headers: Record<string, string> } {
    const options = this.options as AuthOptions
    const modern = options.channelAuthorization
    if (modern !== undefined) {
      return {
        endpoint: modern.endpoint ?? '/pusher/auth',
        headers: { ...modern.headers, ...modern.headersProvider?.() },
      }
    }
    return {
      endpoint: options.authEndpoint ?? '/broadcasting/auth',
      headers: { ...options.auth?.headers },
    }
  }
}

export class FakePusher {}
