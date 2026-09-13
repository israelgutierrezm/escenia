<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { AppButton } from '@escenia/ui'
import type { ParticipantStage } from '@escenia/types'

import { useSpeakerStore } from '@/stores/speaker'
import { useDeviceCheck } from '@/composables/useDeviceCheck'

const router = useRouter()
const store = useSpeakerStore()
const video = ref<HTMLVideoElement | null>(null)

const { stream, camaraOn, microOn, error: deviceError, activando, iniciar, toggleCamara, toggleMicro } =
  useDeviceCheck()

const estadoEscenario: Record<ParticipantStage, { titulo: string; detalle: string; clase: string }> = {
  invited: { titulo: 'Invitación aceptada', detalle: 'El productor te dará paso a la sala.', clase: '' },
  green_room: { titulo: 'En la sala de espera', detalle: 'Revisa tu cámara y micrófono. El productor te dará paso.', clase: '' },
  backstage: { titulo: 'Entre bastidores', detalle: 'Prepárate: entrarás al escenario en breve.', clase: 'chip--primary' },
  stage: { titulo: '¡Estás en el escenario!', detalle: 'Tu cámara y micrófono están al aire.', clase: 'chip--live' },
  left: { titulo: 'Has salido de la sala', detalle: 'Vuelve a unirte cuando quieras.', clase: 'chip--danger' },
}

const estado = computed(() => estadoEscenario[store.session?.participant.stage ?? 'green_room'])

watch(stream, (s) => {
  if (video.value !== null) video.value.srcObject = s
})

function salir(): void {
  store.logout()
  router.push({ name: 'home' })
}

onMounted(() => {
  void iniciar()
})
</script>

<template>
  <div class="sala">
    <header class="bar">
      <div class="marca"><span class="marca__punto"></span><strong>escenia</strong> <span class="muted rol">Ponente</span></div>
      <AppButton variant="ghost" @click="salir">Salir</AppButton>
    </header>

    <main class="contenido">
      <section class="preview panel">
        <div class="video-wrap">
          <video ref="video" autoplay playsinline muted class="video" :class="{ oculto: !camaraOn }"></video>
          <div v-if="!camaraOn || stream === null" class="video-off">
            <p v-if="activando" class="muted">Activando cámara…</p>
            <p v-else-if="deviceError" class="muted">{{ deviceError }}</p>
            <p v-else-if="!camaraOn" class="muted">Cámara apagada</p>
            <p v-else class="muted">Sin vista previa</p>
          </div>
          <span class="etiqueta">{{ store.session?.participant.name }}</span>
        </div>

        <div class="controles">
          <button type="button" class="ctrl" :class="{ off: !camaraOn }" :aria-pressed="camaraOn" @click="toggleCamara">
            {{ camaraOn ? 'Cámara ✓' : 'Cámara ✕' }}
          </button>
          <button type="button" class="ctrl" :class="{ off: !microOn }" :aria-pressed="microOn" @click="toggleMicro">
            {{ microOn ? 'Micrófono ✓' : 'Micrófono ✕' }}
          </button>
        </div>
      </section>

      <section class="estado panel">
        <span class="chip" :class="estado.clase">{{ estado.titulo }}</span>
        <p class="detalle">{{ estado.detalle }}</p>
        <dl>
          <div><dt>Sala</dt><dd class="mono">{{ store.session?.room }}</dd></div>
        </dl>
        <p class="muted small">
          El video en directo se conecta con el proveedor de medios del evento cuando el productor te pone al aire.
        </p>
      </section>
    </main>
  </div>
</template>

<style scoped>
.sala {
  min-height: 100dvh;
  display: flex;
  flex-direction: column;
}

.bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: var(--escenia-space-3) var(--escenia-space-4);
  border-bottom: 1px solid var(--escenia-color-border);
  background: rgba(6, 18, 31, 0.72);
  backdrop-filter: blur(14px);
}

.marca {
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.marca__punto {
  width: 11px;
  height: 11px;
  border-radius: 50%;
  background: var(--escenia-gradient-brand);
}

.rol {
  font-size: 0.7rem;
  letter-spacing: 0.14em;
  text-transform: uppercase;
}

.contenido {
  flex: 1;
  width: 100%;
  max-width: 780px;
  margin: 0 auto;
  padding: var(--escenia-space-5) var(--escenia-space-4);
  display: flex;
  flex-direction: column;
  gap: var(--escenia-space-5);
}

.video-wrap {
  position: relative;
  aspect-ratio: 16 / 9;
  border-radius: var(--escenia-radius-md);
  overflow: hidden;
  background: #04101d;
  border: 1px solid var(--escenia-color-border-strong);
}

.video {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transform: scaleX(-1);
}

.video.oculto {
  display: none;
}

.video-off {
  position: absolute;
  inset: 0;
  display: grid;
  place-items: center;
  text-align: center;
  padding: var(--escenia-space-4);
}

.etiqueta {
  position: absolute;
  left: var(--escenia-space-3);
  bottom: var(--escenia-space-3);
  padding: 4px 10px;
  font-size: 0.8rem;
  font-weight: 600;
  border-radius: var(--escenia-radius-pill);
  background: rgba(4, 16, 29, 0.7);
  backdrop-filter: blur(6px);
}

.controles {
  display: flex;
  gap: var(--escenia-space-2);
  justify-content: center;
  margin-top: var(--escenia-space-3);
}

.ctrl {
  padding: 10px var(--escenia-space-4);
  font: inherit;
  font-weight: 600;
  font-size: 0.85rem;
  color: var(--escenia-color-text);
  background: rgba(4, 16, 29, 0.5);
  border: 1px solid var(--escenia-color-border);
  border-radius: var(--escenia-radius-sm);
  cursor: pointer;
  transition: all 0.14s ease;
}

.ctrl.off {
  color: var(--escenia-color-danger);
  border-color: color-mix(in srgb, var(--escenia-color-danger) 45%, transparent);
  background: color-mix(in srgb, var(--escenia-color-danger) 10%, transparent);
}

.estado {
  display: flex;
  flex-direction: column;
  gap: var(--escenia-space-2);
}

.detalle {
  margin: 0;
}

.small {
  font-size: 0.8rem;
  margin: var(--escenia-space-2) 0 0;
}

dl {
  margin: 0;
}

dl div {
  display: flex;
  justify-content: space-between;
  gap: var(--escenia-space-3);
  font-size: 0.9rem;
}

dt {
  color: var(--escenia-color-text-muted);
}

dd {
  margin: 0;
  font-weight: 600;
}

.mono {
  font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
  font-size: 0.82rem;
}
</style>
