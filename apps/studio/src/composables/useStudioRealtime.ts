import { onBeforeUnmount } from 'vue'

/**
 * Minimal shape of the pieces of Laravel Echo we use, kept local so the
 * dependency stays dynamically imported and its generics never leak here.
 */
interface RealtimeChannel {
  listen(event: string, callback: (payload: unknown) => void): RealtimeChannel
}
interface RealtimeEcho {
  private(name: string): RealtimeChannel
  channel(name: string): RealtimeChannel
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
