import { onBeforeUnmount, ref, type Ref } from 'vue'

import { getAttendeeToken } from '@/lib/attendeeApi'

/**
 * Minimal shape of the pieces of Laravel Echo we use, so we can keep the
 * dependency dynamically imported and avoid leaking its generics here.
 */
interface RealtimeChannel {
  listen(event: string, callback: (payload: unknown) => void): RealtimeChannel
}
// Laravel Echo is inconsistent: `here` yields flattened user_info ({id,role,…}),
// while `joining`/`leaving` yield {id, info:{role,…}}. Accept both shapes.
interface PresenceMember {
  id: string | number
  role?: string
  info?: { role?: string }
}
interface PresenceChannel {
  here(callback: (members: PresenceMember[]) => void): PresenceChannel
  joining(callback: (member: PresenceMember) => void): PresenceChannel
  leaving(callback: (member: PresenceMember) => void): PresenceChannel
}
interface RealtimeEcho {
  channel(name: string): RealtimeChannel
  private(name: string): RealtimeChannel
  join(name: string): PresenceChannel
  leaveChannel(name: string): void
  leave(name: string): void
}

type Handlers = Record<string, (payload: unknown) => void>

let echoPromise: Promise<RealtimeEcho | null> | null = null

/** Real-time is opt-in: without a configured Reverb key the app relies on polling. */
function configurado(): boolean {
  return Boolean(import.meta.env.VITE_REVERB_APP_KEY)
}

function obtenerEcho(): Promise<RealtimeEcho | null> {
  if (echoPromise === null) {
    echoPromise = (async (): Promise<RealtimeEcho | null> => {
      const [echoMod, pusherMod] = await Promise.all([import('laravel-echo'), import('pusher-js')])
      ;(window as unknown as { Pusher: unknown }).Pusher = pusherMod.default
      const EchoCtor = echoMod.default as unknown as new (options: Record<string, unknown>) => unknown
      const echo = new EchoCtor({
        broadcaster: 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY ?? '',
        wsHost: import.meta.env.VITE_REVERB_HOST,
        wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? '443'),
        wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? '443'),
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
        // Presence channels authorize via the attendee token (no Sanctum session).
        authEndpoint: '/api/v1/attend/broadcasting/auth',
        auth: { headers: { 'X-Attendee-Token': getAttendeeToken() ?? '' } },
      })
      return echo as RealtimeEcho
    })()
  }
  return echoPromise
}

/**
 * Subscribe to an event's public channel and bind broadcast handlers by their
 * `broadcastAs` name (e.g. `chat.posted`). A no-op when realtime isn't
 * configured, so callers keep their polling as the baseline.
 */
export function useEventChannel(eventId: string, handlers: Handlers): void {
  if (!configurado()) return

  let cancelado = false
  let limpiar: (() => void) | null = null

  void obtenerEcho().then((echo) => {
    if (echo === null || cancelado) return
    const channel = echo.channel(`event.${eventId}`)
    for (const [nombre, cb] of Object.entries(handlers)) {
      channel.listen(`.${nombre}`, cb)
    }
    limpiar = () => echo.leaveChannel(`event.${eventId}`)
  })

  onBeforeUnmount(() => {
    cancelado = true
    limpiar?.()
  })
}

/**
 * Subscribe to a PRIVATE channel (authorized via the attendee token) and bind
 * broadcast handlers by their `broadcastAs` name. Used for per-attendee
 * notifications (`attendee.{ulid}`). A no-op when realtime isn't configured.
 */
export function usePrivateChannel(name: string, handlers: Handlers): void {
  if (!configurado()) return

  let cancelado = false
  let limpiar: (() => void) | null = null

  void obtenerEcho().then((echo) => {
    if (echo === null || cancelado) return
    const channel = echo.private(name)
    for (const [nombre, cb] of Object.entries(handlers)) {
      channel.listen(`.${nombre}`, cb)
    }
    limpiar = () => echo.leave(name)
  })

  onBeforeUnmount(() => {
    cancelado = true
    limpiar?.()
  })
}

/**
 * Join the event's presence channel and expose the live count of attendees
 * online (deduped by attendee id; the producer joins tagged `host` and is not
 * counted). Members drop automatically when their WebSocket disconnects, so the
 * count self-corrects. Returns 0 (and never connects) when realtime is off.
 */
export function useViewerCount(eventId: string): { enLinea: Ref<number> } {
  const enLinea = ref(0)
  if (!configurado()) return { enLinea }

  const ids = new Set<string>()
  const esAsistente = (m: PresenceMember): boolean => (m.info?.role ?? m.role) === 'attendee'
  let cancelado = false
  let limpiar: (() => void) | null = null

  void obtenerEcho().then((echo) => {
    if (echo === null || cancelado) return
    const canal = echo.join(`viewers.${eventId}`)
    canal
      .here((members) => {
        ids.clear()
        for (const m of members) if (esAsistente(m)) ids.add(String(m.id))
        enLinea.value = ids.size
      })
      .joining((m) => {
        if (esAsistente(m)) {
          ids.add(String(m.id))
          enLinea.value = ids.size
        }
      })
      .leaving((m) => {
        if (esAsistente(m)) {
          ids.delete(String(m.id))
          enLinea.value = ids.size
        }
      })
    limpiar = () => echo.leave(`viewers.${eventId}`)
  })

  onBeforeUnmount(() => {
    cancelado = true
    limpiar?.()
  })

  return { enLinea }
}
