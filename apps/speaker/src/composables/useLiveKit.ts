import { onBeforeUnmount, ref } from 'vue'
import { Room, RoomEvent, Track } from 'livekit-client'

type Estado = 'idle' | 'conectando' | 'conectado' | 'error'

/**
 * Publishes the speaker's camera/mic into the event's media room (LiveKit).
 * Activates against a real media server; in dev the provider is fake, so the
 * caller only connects when the access URL is real (see `esUrlDeMedios`).
 */
export function useLiveKit() {
  const estado = ref<Estado>('idle')
  const error = ref<string | null>(null)
  const camaraOn = ref(true)
  const microOn = ref(true)
  let room: Room | null = null

  async function conectar(url: string, token: string, videoEl: HTMLVideoElement | null): Promise<void> {
    estado.value = 'conectando'
    error.value = null
    try {
      room = new Room({ adaptiveStream: true, dynacast: true })
      room.on(RoomEvent.Disconnected, () => {
        estado.value = 'idle'
      })
      await room.connect(url, token)
      await room.localParticipant.enableCameraAndMicrophone()
      const pub = room.localParticipant.getTrackPublication(Track.Source.Camera)
      if (videoEl !== null && pub?.videoTrack !== undefined) {
        pub.videoTrack.attach(videoEl)
      }
      estado.value = 'conectado'
    } catch {
      estado.value = 'error'
      error.value = 'No se pudo conectar al vídeo en directo del evento.'
      await desconectar()
    }
  }

  async function toggleCamara(): Promise<void> {
    camaraOn.value = !camaraOn.value
    await room?.localParticipant.setCameraEnabled(camaraOn.value)
  }

  async function toggleMicro(): Promise<void> {
    microOn.value = !microOn.value
    await room?.localParticipant.setMicrophoneEnabled(microOn.value)
  }

  async function desconectar(): Promise<void> {
    if (room !== null) {
      await room.disconnect()
      room = null
    }
  }

  onBeforeUnmount(() => {
    void desconectar()
  })

  return { estado, error, camaraOn, microOn, conectar, toggleCamara, toggleMicro, desconectar }
}

/** A real media-server URL (not the network-free dev fake). */
export function esUrlDeMedios(url: string | undefined): boolean {
  return url !== undefined && /^wss?:\/\//i.test(url) && !/fake|\.local\b/i.test(url)
}
