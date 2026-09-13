import { onBeforeUnmount, ref } from 'vue'

/**
 * Local camera/mic preview for the green room, using getUserMedia. Independent
 * of the media server: it lets a speaker check their devices before going live.
 */
export function useDeviceCheck() {
  const stream = ref<MediaStream | null>(null)
  const camaraOn = ref(true)
  const microOn = ref(true)
  const error = ref<string | null>(null)
  const activando = ref(false)

  async function iniciar(): Promise<void> {
    if (stream.value !== null) return
    activando.value = true
    error.value = null
    try {
      stream.value = await navigator.mediaDevices.getUserMedia({ video: true, audio: true })
    } catch {
      error.value = 'No se pudo acceder a la cámara o el micrófono. Revisa los permisos del navegador.'
    } finally {
      activando.value = false
    }
  }

  function toggleCamara(): void {
    camaraOn.value = !camaraOn.value
    stream.value?.getVideoTracks().forEach((t) => {
      t.enabled = camaraOn.value
    })
  }

  function toggleMicro(): void {
    microOn.value = !microOn.value
    stream.value?.getAudioTracks().forEach((t) => {
      t.enabled = microOn.value
    })
  }

  function detener(): void {
    stream.value?.getTracks().forEach((t) => t.stop())
    stream.value = null
  }

  onBeforeUnmount(detener)

  return { stream, camaraOn, microOn, error, activando, iniciar, toggleCamara, toggleMicro, detener }
}
