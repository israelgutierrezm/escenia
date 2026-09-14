import { onBeforeUnmount, ref } from 'vue'
// Types only at build time; the ~570 kB runtime is dynamically imported inside
// `conectar` so it never enters the main bundle (dev/fake never connects).
import type { RemoteParticipant, RemoteTrack, RemoteTrackPublication, Room } from 'livekit-client'

type Estado = 'idle' | 'conectando' | 'conectado' | 'error'

/** URL real de medios (no el proveedor `fake` de desarrollo). */
export function esUrlDeMedios(url: string | undefined): boolean {
  return url !== undefined && /^wss?:\/\//i.test(url) && !/fake|\.local\b/i.test(url)
}

/**
 * Conecta la consola del productor a la sala de medios como «host»: no publica
 * cámara, solo se suscribe a las pistas de vídeo de los participantes y las
 * adjunta a los tiles según su `identity`. Activa contra un servidor real; con
 * el proveedor `fake` el llamador no conecta (ver `esUrlDeMedios`).
 */
export function useStudioRoom() {
  const estado = ref<Estado>('idle')
  const error = ref<string | null>(null)
  // Identidades con una pista de vídeo suscrita en este momento.
  const conVideo = ref<string[]>([])

  let room: Room | null = null
  const pistas = new Map<string, RemoteTrack>()
  const elementos = new Map<string, HTMLVideoElement>()

  function marcar(identity: string, tiene: boolean): void {
    const i = conVideo.value.indexOf(identity)
    if (tiene && i === -1) conVideo.value.push(identity)
    else if (!tiene && i !== -1) conVideo.value.splice(i, 1)
  }

  function adjuntar(identity: string): void {
    const track = pistas.get(identity)
    const el = elementos.get(identity)
    if (track !== undefined && el !== undefined) track.attach(el)
  }

  /** El tile registra (o limpia con `null`) su elemento de vídeo por identidad. */
  function registrarVideo(identity: string, el: HTMLVideoElement | null): void {
    if (el === null) {
      elementos.delete(identity)
      return
    }
    elementos.set(identity, el)
    adjuntar(identity)
  }

  async function conectar(url: string, token: string): Promise<void> {
    if (room !== null) return
    estado.value = 'conectando'
    error.value = null
    try {
      const { Room, RoomEvent, Track } = await import('livekit-client')
      const sala = new Room({ adaptiveStream: true })
      sala.on(RoomEvent.TrackSubscribed, (track: RemoteTrack, _pub: RemoteTrackPublication, participant: RemoteParticipant) => {
        if (track.kind !== Track.Kind.Video) return
        pistas.set(participant.identity, track)
        marcar(participant.identity, true)
        adjuntar(participant.identity)
      })
      sala.on(RoomEvent.TrackUnsubscribed, (track: RemoteTrack, _pub: RemoteTrackPublication, participant: RemoteParticipant) => {
        if (track.kind !== Track.Kind.Video) return
        track.detach()
        pistas.delete(participant.identity)
        marcar(participant.identity, false)
      })
      sala.on(RoomEvent.ParticipantDisconnected, (participant: RemoteParticipant) => {
        pistas.delete(participant.identity)
        marcar(participant.identity, false)
      })
      sala.on(RoomEvent.Disconnected, () => {
        estado.value = 'idle'
      })
      await sala.connect(url, token)
      room = sala
      estado.value = 'conectado'
    } catch {
      estado.value = 'error'
      error.value = 'No se pudo conectar al vídeo del estudio.'
      await desconectar()
    }
  }

  async function desconectar(): Promise<void> {
    if (room !== null) {
      await room.disconnect()
      room = null
    }
    pistas.clear()
    elementos.clear()
    conVideo.value = []
  }

  onBeforeUnmount(() => {
    void desconectar()
  })

  return { estado, error, conVideo, registrarVideo, conectar, desconectar }
}
