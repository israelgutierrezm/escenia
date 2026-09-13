import { onBeforeUnmount } from 'vue'

/**
 * Minimal shape of the pieces of Laravel Echo we use, so we can keep the
 * dependency dynamically imported and avoid leaking its generics here.
 */
interface RealtimeChannel {
  listen(event: string, callback: (payload: unknown) => void): RealtimeChannel
}
interface RealtimeEcho {
  channel(name: string): RealtimeChannel
  leaveChannel(name: string): void
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
