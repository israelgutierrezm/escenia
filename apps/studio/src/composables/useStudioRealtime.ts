import { onBeforeUnmount, ref, type Ref } from 'vue'

/**
 * Minimal shape of the pieces of Laravel Echo we use, kept local so the
 * dependency stays dynamically imported and its generics never leak here.
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
  private(name: string): RealtimeChannel
  channel(name: string): RealtimeChannel
  join(name: string): PresenceChannel
  leave(name: string): void
  leaveChannel(name: string): void
}

type Handlers = Record<string, (payload: unknown) => void>

let echoPromise: Promise<RealtimeEcho | null> | null = null

/** Real-time is opt-in: without a configured Reverb key the console relies on manual refresh. */
function configurado(): boolean {
  return Boolean(import.meta.env.VITE_REVERB_APP_KEY)
}

function leerCookie(nombre: string): string | null {
  const match = document.cookie.match(new RegExp('(^|; )' + nombre + '=([^;]*)'))
  return match?.[2] !== undefined ? decodeURIComponent(match[2]) : null
}

function obtenerEcho(): Promise<RealtimeEcho | null> {
  if (echoPromise === null) {
    echoPromise = (async (): Promise<RealtimeEcho | null> => {
      const [echoMod, pusherMod] = await Promise.all([import('laravel-echo'), import('pusher-js')])
      ;(window as unknown as { Pusher: unknown }).Pusher = pusherMod.default
      const EchoCtor = echoMod.default as unknown as new (options: Record<string, unknown>) => unknown
      const xsrf = leerCookie('XSRF-TOKEN')
      const echo = new EchoCtor({
        broadcaster: 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY ?? '',
        wsHost: import.meta.env.VITE_REVERB_HOST,
        wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? '443'),
        wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? '443'),
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
        // Private channels authorize over the SPA origin (proxied to Laravel),
        // carrying the Sanctum session cookie + CSRF token.
        authEndpoint: '/broadcasting/auth',
        auth: { headers: xsrf !== null ? { 'X-XSRF-TOKEN': xsrf } : {} },
      })
      return echo as RealtimeEcho
    })()
  }
  return echoPromise
}

/**
 * Subscribe to the event's PRIVATE studio channel (`studio.{ulid}`) and bind
 * broadcast handlers by their `broadcastAs` name (e.g. `participant.activity`).
 * A no-op when realtime isn't configured, so the console keeps working with its
 * on-demand loads as the baseline.
 */
export function useStudioChannel(eventId: string, handlers: Handlers): void {
  if (!configurado()) return

  let cancelado = false
  let limpiar: (() => void) | null = null

  void obtenerEcho().then((echo) => {
    if (echo === null || cancelado) return
    const channel = echo.private(`studio.${eventId}`)
    for (const [nombre, cb] of Object.entries(handlers)) {
      channel.listen(`.${nombre}`, cb)
    }
    limpiar = () => echo.leave(`studio.${eventId}`)
  })

  onBeforeUnmount(() => {
    cancelado = true
    limpiar?.()
  })
}

/**
 * Subscribe to the event's PUBLIC channel (`event.{ulid}`) — the same feed the
 * attendee uses for chat/Q&A/polls — so the producer sees the live chat in the
 * console. A no-op when realtime isn't configured.
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
 * Join the event's presence channel and expose the live count of attendees
 * online (deduped by attendee id; the producer joins tagged `host` and is not
 * counted). Members drop automatically on WebSocket disconnect, so the count
 * self-corrects. Returns 0 (and never connects) when realtime is off.
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
