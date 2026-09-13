<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { AppButton } from '@escenia/ui'
import { ApiError } from '@escenia/api-client'
import type {
  ClipStatus,
  Recording,
  RecordingSource,
  RecordingStatus,
  TrackKind,
  Transcript,
  TranscriptSegment,
  TranscriptStatus,
} from '@escenia/types'

import { fecha } from '@/lib/eventLabels'
import { api } from '@/lib/api'

const route = useRoute()
const id = route.params.id as string

const recordings = ref<Recording[]>([])
const selectedId = ref<string | null>(null)
const detalle = ref<Recording | null>(null)
const segmentos = ref<Record<string, TranscriptSegment[]>>({})
const abierto = ref<string | null>(null)

const loading = ref(true)
const cargandoDetalle = ref(false)
const error = ref<string | null>(null)
const busy = ref(false)

const idioma = ref('es')
const nuevoClip = ref({ title: '', start: '', end: '' })

const fuente: Record<RecordingSource, string> = { broadcast: 'Emisión', upload: 'Subida' }
const estadoGrab: Record<RecordingStatus, { label: string; clase: string }> = {
  pending: { label: 'Pendiente', clase: '' },
  processing: { label: 'Procesando', clase: 'chip--primary' },
  ready: { label: 'Lista', clase: 'chip--live' },
  failed: { label: 'Falló', clase: 'chip--danger' },
}
const estadoTranscripcion: Record<TranscriptStatus, { label: string; clase: string }> = {
  pending: { label: 'Pendiente', clase: '' },
  processing: { label: 'Procesando', clase: 'chip--primary' },
  ready: { label: 'Lista', clase: 'chip--live' },
  failed: { label: 'Falló', clase: 'chip--danger' },
}
const pista: Record<TrackKind, string> = {
  composite: 'Compuesta',
  screen: 'Pantalla',
  camera: 'Cámara',
  audio: 'Audio',
}
const estadoClip: Record<ClipStatus, { label: string; clase: string }> = {
  draft: { label: 'Borrador', clase: '' },
  ready: { label: 'Listo', clase: 'chip--live' },
}

const esVideo = computed(() => {
  const f = detalle.value?.format ?? ''
  return f.includes('mp4') || f.includes('webm') || f.startsWith('video')
})

function message(e: unknown): string {
  return e instanceof ApiError ? e.message : 'Algo salió mal.'
}

function tiempo(ms: number | null): string {
  if (ms === null) return '—'
  const total = Math.round(ms / 1000)
  const m = Math.floor(total / 60)
  const s = total % 60
  return `${m}:${String(s).padStart(2, '0')}`
}

function pesoMb(bytes: number | null): string {
  if (bytes === null) return '—'
  return `${(bytes / 1_048_576).toFixed(1)} MB`
}

function titulo(r: Recording): string {
  return r.title ?? `Grabación · ${fuente[r.source]}`
}

async function load(): Promise<void> {
  loading.value = true
  error.value = null
  try {
    recordings.value = (await api.recordings(id)).data
    const first = recordings.value[0]
    if (first !== undefined) await seleccionar(first.id)
  } catch (e) {
    error.value = message(e)
  } finally {
    loading.value = false
  }
}

async function seleccionar(rid: string): Promise<void> {
  selectedId.value = rid
  cargandoDetalle.value = true
  abierto.value = null
  try {
    detalle.value = (await api.recording(rid)).data
  } catch (e) {
    error.value = message(e)
  } finally {
    cargandoDetalle.value = false
  }
}

async function run(fn: () => Promise<void>): Promise<void> {
  busy.value = true
  error.value = null
  try {
    await fn()
  } catch (e) {
    error.value = message(e)
  } finally {
    busy.value = false
  }
}

function transcribir(): void {
  const rid = selectedId.value
  if (rid === null) return
  run(async () => {
    await api.transcribeRecording(rid, idioma.value)
    detalle.value = (await api.recording(rid)).data
  })
}

function verSegmentos(t: Transcript): void {
  if (abierto.value === t.id) {
    abierto.value = null
    return
  }
  abierto.value = t.id
  if (segmentos.value[t.id] !== undefined) return
  run(async () => {
    segmentos.value[t.id] = (await api.transcript(t.id)).data.segments ?? []
  })
}

function crearClip(): void {
  const rid = selectedId.value
  const c = nuevoClip.value
  if (rid === null || c.title.trim() === '') return
  const startMs = Math.round(Number(c.start || 0) * 1000)
  const endMs = Math.round(Number(c.end || 0) * 1000)
  if (endMs <= startMs) {
    error.value = 'El fin del clip debe ser mayor que el inicio.'
    return
  }
  run(async () => {
    await api.createClip(rid, { title: c.title, start_ms: startMs, end_ms: endMs })
    nuevoClip.value = { title: '', start: '', end: '' }
    detalle.value = (await api.recording(rid)).data
  })
}

onMounted(load)
</script>

<template>
  <section class="stack">
    <RouterLink :to="{ name: 'event-detail', params: { id } }" class="back muted">‹ Volver al evento</RouterLink>

    <div class="page-head">
      <h1>Contenido</h1>
      <p>Grabaciones, transcripciones y clips del evento.</p>
    </div>

    <p v-if="loading" class="muted">Cargando…</p>
    <p v-else-if="error && recordings.length === 0" role="alert" class="error-text">{{ error }}</p>

    <div v-else-if="recordings.length === 0" class="panel empty-state">
      <h2>Aún no hay grabaciones</h2>
      <p class="muted">
        Las grabaciones se generan al emitir un evento con la opción «Grabar» activada.
      </p>
      <RouterLink :to="{ name: 'event-studio', params: { id } }" class="head-link">Ir a Studio › Emisión</RouterLink>
    </div>

    <div v-else class="split">
      <!-- Lista de grabaciones -->
      <aside class="panel stack list-pane">
        <h2>Grabaciones <span class="muted">({{ recordings.length }})</span></h2>
        <ul class="rec-list">
          <li v-for="r in recordings" :key="r.id">
            <button type="button" class="rec" :class="{ 'is-active': r.id === selectedId }" @click="seleccionar(r.id)">
              <div class="rec__top">
                <strong>{{ titulo(r) }}</strong>
                <span class="chip" :class="estadoGrab[r.status].clase">{{ estadoGrab[r.status].label }}</span>
              </div>
              <div class="muted small">
                {{ fuente[r.source] }} · {{ tiempo(r.duration_ms) }} · {{ fecha(r.created_at) }}
              </div>
            </button>
          </li>
        </ul>
      </aside>

      <!-- Detalle -->
      <div class="detail-pane stack">
        <p v-if="cargandoDetalle" class="muted">Cargando grabación…</p>

        <template v-else-if="detalle">
          <div class="panel stack">
            <div class="actions" style="justify-content: space-between; align-items: flex-start">
              <div>
                <h2 style="margin: 0">{{ titulo(detalle) }}</h2>
                <p class="muted small">
                  {{ fuente[detalle.source] }} · {{ tiempo(detalle.duration_ms) }} · {{ pesoMb(detalle.size_bytes) }}
                  <template v-if="detalle.format">· {{ detalle.format }}</template>
                </p>
              </div>
              <span class="chip" :class="estadoGrab[detalle.status].clase">{{ estadoGrab[detalle.status].label }}</span>
            </div>

            <video v-if="detalle.playback_url && esVideo" :src="detalle.playback_url" controls class="player"></video>
            <audio v-else-if="detalle.playback_url" :src="detalle.playback_url" controls class="player-audio"></audio>
            <p v-else class="muted small">La reproducción estará disponible cuando la grabación esté lista.</p>

            <div v-if="detalle.tracks && detalle.tracks.length" class="tracks">
              <span v-for="t in detalle.tracks" :key="t.id" class="chip">
                {{ pista[t.kind] }}<template v-if="t.label"> · {{ t.label }}</template>
              </span>
            </div>
          </div>

          <!-- Transcripciones -->
          <div class="panel stack">
            <div class="actions" style="justify-content: space-between">
              <h2 style="margin: 0">Transcripciones</h2>
              <div class="add">
                <select v-model="idioma" class="control">
                  <option value="es">Español</option>
                  <option value="en">Inglés</option>
                </select>
                <AppButton :disabled="busy" @click="transcribir">Solicitar transcripción</AppButton>
              </div>
            </div>

            <p v-if="!detalle.transcripts || detalle.transcripts.length === 0" class="empty">Aún no hay transcripciones.</p>
            <div v-for="t in detalle.transcripts ?? []" :key="t.id" class="transcript">
              <div class="transcript__head">
                <div>
                  <strong>{{ t.language.toUpperCase() }}</strong>
                  <span class="muted small">· {{ t.provider }}</span>
                  <span class="chip" :class="estadoTranscripcion[t.status].clase">{{ estadoTranscripcion[t.status].label }}</span>
                </div>
                <AppButton variant="ghost" :disabled="busy" @click="verSegmentos(t)">
                  {{ abierto === t.id ? 'Ocultar' : 'Ver segmentos' }}
                </AppButton>
              </div>
              <div v-if="abierto === t.id" class="segments">
                <p v-if="(segmentos[t.id]?.length ?? 0) === 0" class="muted small">Sin segmentos disponibles.</p>
                <div v-for="(seg, i) in segmentos[t.id] ?? []" :key="i" class="seg">
                  <span class="seg__time muted">{{ tiempo(seg.start_ms) }}</span>
                  <span class="seg__text"><strong v-if="seg.speaker">{{ seg.speaker }}: </strong>{{ seg.text }}</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Clips -->
          <div class="panel stack">
            <h2>Clips</h2>
            <div class="crear">
              <label class="field"><span>Título</span><input v-model="nuevoClip.title" class="control" placeholder="Momento destacado" /></label>
              <label class="field"><span>Inicio (seg.)</span><input v-model="nuevoClip.start" type="number" min="0" class="control" placeholder="0" /></label>
              <label class="field"><span>Fin (seg.)</span><input v-model="nuevoClip.end" type="number" min="0" class="control" placeholder="30" /></label>
              <AppButton :disabled="busy || !nuevoClip.title.trim()" @click="crearClip">Crear clip</AppButton>
            </div>

            <p v-if="!detalle.clips || detalle.clips.length === 0" class="empty">Aún no hay clips.</p>
            <ul v-else class="lista">
              <li v-for="c in detalle.clips ?? []" :key="c.id">
                <div>
                  <strong>{{ c.title }}</strong>
                  <span class="muted small">{{ tiempo(c.start_ms) }} – {{ tiempo(c.end_ms) }}</span>
                </div>
                <span class="chip" :class="estadoClip[c.status].clase">{{ estadoClip[c.status].label }}</span>
              </li>
            </ul>
          </div>
        </template>
      </div>
    </div>

    <p v-if="error && recordings.length" role="alert" class="error-text">{{ error }}</p>
  </section>
</template>

<style scoped>
.back { text-decoration: none; font-size: 0.85rem; }
.back:hover { color: var(--escenia-color-text); }
.small { font-size: 0.8rem; margin: 4px 0 0; }
.add { display: flex; gap: var(--escenia-space-2); flex-wrap: wrap; }

.empty-state { text-align: center; display: flex; flex-direction: column; align-items: center; gap: var(--escenia-space-3); padding: var(--escenia-space-8) var(--escenia-space-4); }
.head-link {
  padding: 9px 14px; font-size: 0.85rem; font-weight: 600; color: var(--escenia-color-primary);
  text-decoration: none; border: 1px solid color-mix(in srgb, var(--escenia-color-primary) 40%, transparent);
  border-radius: var(--escenia-radius-sm);
}
.head-link:hover { background: color-mix(in srgb, var(--escenia-color-primary) 10%, transparent); }

.split { display: grid; grid-template-columns: minmax(240px, 320px) 1fr; gap: var(--escenia-space-6); align-items: start; }

.rec-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: var(--escenia-space-2); }
.rec { display: block; width: 100%; text-align: left; padding: 10px 12px; font: inherit; color: var(--escenia-color-text); background: rgba(4, 16, 29, 0.35); border: 1px solid var(--escenia-color-border); border-radius: var(--escenia-radius-sm); cursor: pointer; transition: all 0.14s ease; }
.rec:hover { border-color: var(--escenia-color-border-strong); }
.rec.is-active { border-color: color-mix(in srgb, var(--escenia-color-primary) 55%, transparent); background: color-mix(in srgb, var(--escenia-color-primary) 8%, transparent); }
.rec__top { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 4px; }

.player { width: 100%; border-radius: var(--escenia-radius-sm); background: #000; max-height: 420px; }
.player-audio { width: 100%; }
.tracks { display: flex; gap: var(--escenia-space-2); flex-wrap: wrap; }

.transcript { border-top: 1px solid var(--escenia-color-border); padding-top: var(--escenia-space-3); }
.transcript__head { display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; }
.segments { margin-top: var(--escenia-space-3); display: flex; flex-direction: column; gap: 6px; max-height: 320px; overflow-y: auto; padding-right: 6px; }
.seg { display: flex; gap: var(--escenia-space-3); font-size: 0.88rem; }
.seg__time { flex-shrink: 0; font-variant-numeric: tabular-nums; min-width: 44px; }

.crear { display: grid; grid-template-columns: 2fr 1fr 1fr auto; gap: var(--escenia-space-3); align-items: end; }
.lista { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: var(--escenia-space-2); }
.lista li { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 10px 12px; border: 1px solid var(--escenia-color-border); border-radius: var(--escenia-radius-sm); background: rgba(4, 16, 29, 0.35); }
.lista .small { margin-left: 8px; }

@media (max-width: 860px) {
  .split { grid-template-columns: 1fr; }
  .crear { grid-template-columns: 1fr; }
}
</style>
