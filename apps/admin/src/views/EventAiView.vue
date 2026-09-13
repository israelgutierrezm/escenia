<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { AppButton } from '@escenia/ui'
import { ApiError } from '@escenia/api-client'
import type {
  ContentSummary,
  QaAnswer,
  Recording,
  SearchHit,
  SummaryKind,
  SummaryStatus,
} from '@escenia/types'

import { api } from '@/lib/api'
import TabList from '@/components/TabList.vue'

const route = useRoute()
const id = route.params.id as string

type Tab = 'busqueda' | 'preguntas' | 'resumenes' | 'arquitecto'
const tab = ref<Tab>('busqueda')
const tabs: [Tab, string][] = [
  ['busqueda', 'Búsqueda'],
  ['preguntas', 'Preguntas'],
  ['resumenes', 'Resúmenes'],
  ['arquitecto', 'Arquitecto'],
]

const error = ref<string | null>(null)
const busy = ref(false)

// Búsqueda semántica
const consulta = ref('')
const hits = ref<SearchHit[]>([])
const buscado = ref(false)

// Preguntas (RAG)
const pregunta = ref('')
const respuesta = ref<QaAnswer | null>(null)

// Resúmenes
const recordings = ref<Recording[]>([])
const recordingSel = ref('')
const kindSel = ref<SummaryKind>('summary')
const resumen = ref<ContentSummary | null>(null)
const kinds: [SummaryKind, string][] = [
  ['summary', 'Resumen'],
  ['chapters', 'Capítulos'],
  ['highlights', 'Destacados'],
]
const estadoResumen: Record<SummaryStatus, { label: string; clase: string }> = {
  pending: { label: 'Pendiente', clase: '' },
  processing: { label: 'Procesando', clase: 'chip--primary' },
  ready: { label: 'Listo', clase: 'chip--live' },
  failed: { label: 'Falló', clase: 'chip--danger' },
}

// Arquitecto
const brief = ref('')
const plan = ref<{ plan: string; model: string } | null>(null)

function message(e: unknown): string {
  return e instanceof ApiError ? e.message : 'Algo salió mal.'
}

function tiempo(ms: number): string {
  const total = Math.round(ms / 1000)
  const m = Math.floor(total / 60)
  const s = total % 60
  return `${m}:${String(s).padStart(2, '0')}`
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

async function load(): Promise<void> {
  try {
    recordings.value = (await api.recordings(id)).data
    const first = recordings.value[0]
    if (first !== undefined) recordingSel.value = first.id
  } catch (e) {
    error.value = message(e)
  }
}

function buscar(): void {
  if (consulta.value.trim() === '') return
  run(async () => {
    hits.value = (await api.searchContent(id, consulta.value, 12)).data
    buscado.value = true
  })
}

function preguntar(): void {
  if (pregunta.value.trim() === '') return
  run(async () => {
    respuesta.value = (await api.askContent(id, pregunta.value)).data
  })
}

function pedirResumen(): void {
  if (recordingSel.value === '') return
  run(async () => {
    resumen.value = (await api.requestSummary(recordingSel.value, kindSel.value)).data
  })
}

function actualizarResumen(): void {
  if (resumen.value === null) return
  const sid = resumen.value.id
  run(async () => {
    resumen.value = (await api.contentSummary(sid)).data
  })
}

function disenar(): void {
  if (brief.value.trim() === '') return
  run(async () => {
    plan.value = (await api.designEvent(id, brief.value)).data
  })
}

onMounted(load)
</script>

<template>
  <section class="stack">
    <RouterLink :to="{ name: 'event-detail', params: { id } }" class="back muted">‹ Volver al evento</RouterLink>

    <div class="page-head">
      <h1>Inteligencia artificial</h1>
      <p>Búsqueda semántica, preguntas sobre el contenido, resúmenes y diseño de eventos.</p>
    </div>

    <TabList v-model="tab" :tabs="tabs" label="Secciones de IA" base="ia" />

    <!-- BÚSQUEDA -->
    <div v-if="tab === 'busqueda'" class="panel stack">
      <h2>Búsqueda semántica</h2>
      <p class="muted small">Busca en las transcripciones del evento por significado, no solo por palabras.</p>
      <div class="add">
        <input v-model="consulta" class="control grow" placeholder="¿De qué se habló sobre…?" @keyup.enter="buscar" />
        <AppButton :disabled="busy || !consulta.trim()" @click="buscar">Buscar</AppButton>
      </div>

      <p v-if="buscado && hits.length === 0" class="empty">Sin resultados. (Requiere transcripciones indexadas.)</p>
      <ul v-else-if="hits.length" class="lista">
        <li v-for="h in hits" :key="h.id">
          <div class="hit__head">
            <span class="chip">{{ tiempo(h.start_ms) }} – {{ tiempo(h.end_ms) }}</span>
            <span class="muted small">relevancia {{ (h.score * 100).toFixed(0) }}%</span>
          </div>
          <p class="hit__text">{{ h.text }}</p>
        </li>
      </ul>
    </div>

    <!-- PREGUNTAS -->
    <div v-else-if="tab === 'preguntas'" class="panel stack">
      <h2>Preguntas sobre el contenido</h2>
      <p class="muted small">Respuestas generadas a partir de las transcripciones, con citas.</p>
      <div class="add">
        <input v-model="pregunta" class="control grow" placeholder="¿Qué se dijo sobre precios?" @keyup.enter="preguntar" />
        <AppButton :disabled="busy || !pregunta.trim()" @click="preguntar">Preguntar</AppButton>
      </div>

      <div v-if="respuesta" class="stack">
        <div class="answer">
          <p>{{ respuesta.answer }}</p>
          <p class="muted small">Modelo: {{ respuesta.model }}</p>
        </div>
        <div v-if="respuesta.citations.length" class="stack">
          <h3 class="sub">Citas</h3>
          <ul class="lista">
            <li v-for="c in respuesta.citations" :key="c.id">
              <span class="chip">{{ tiempo(c.start_ms) }} – {{ tiempo(c.end_ms) }}</span>
              <p class="hit__text">{{ c.text }}</p>
            </li>
          </ul>
        </div>
      </div>
    </div>

    <!-- RESÚMENES -->
    <div v-else-if="tab === 'resumenes'" class="panel stack">
      <h2>Resúmenes de grabaciones</h2>
      <p v-if="recordings.length === 0" class="muted">No hay grabaciones que resumir. Emite un evento con grabación para generarlas.</p>
      <template v-else>
        <div class="fila">
          <label class="field grow">
            <span>Grabación</span>
            <select v-model="recordingSel" class="control">
              <option v-for="r in recordings" :key="r.id" :value="r.id">{{ r.title ?? 'Grabación' }}</option>
            </select>
          </label>
          <label class="field">
            <span>Tipo</span>
            <select v-model="kindSel" class="control">
              <option v-for="[k, label] in kinds" :key="k" :value="k">{{ label }}</option>
            </select>
          </label>
          <AppButton :disabled="busy || recordingSel === ''" @click="pedirResumen">Generar</AppButton>
        </div>

        <div v-if="resumen" class="stack">
          <div class="actions" style="justify-content: space-between">
            <span class="chip" :class="estadoResumen[resumen.status].clase">{{ estadoResumen[resumen.status].label }}</span>
            <AppButton variant="ghost" :disabled="busy" @click="actualizarResumen">Actualizar</AppButton>
          </div>
          <pre v-if="resumen.content" class="content">{{ resumen.content }}</pre>
          <p v-else class="muted small">El resumen se está generando. Pulsa «Actualizar» en unos segundos.</p>
        </div>
      </template>
    </div>

    <!-- ARQUITECTO -->
    <div v-else class="panel stack">
      <h2>Arquitecto de eventos</h2>
      <p class="muted small">Describe tu evento y la IA propondrá un plan.</p>
      <textarea v-model="brief" class="control" rows="4" placeholder="p. ej. Un webinar de 90 min sobre IA para 500 asistentes, con Q&A y encuestas…"></textarea>
      <div class="actions">
        <AppButton :disabled="busy || !brief.trim()" @click="disenar">Diseñar plan</AppButton>
      </div>
      <div v-if="plan" class="stack">
        <pre class="content">{{ plan.plan }}</pre>
        <p class="muted small">Modelo: {{ plan.model }}</p>
      </div>
    </div>

    <p v-if="error" role="alert" class="error-text">{{ error }}</p>
  </section>
</template>

<style scoped>
.back { text-decoration: none; font-size: 0.85rem; }
.back:hover { color: var(--escenia-color-text); }
.small { font-size: 0.8rem; margin: 2px 0 0; }
.sub { font-size: 0.95rem; margin: 0; }
.grow { flex: 1; }
.add { display: flex; gap: var(--escenia-space-2); flex-wrap: wrap; }
.fila { display: flex; gap: var(--escenia-space-3); flex-wrap: wrap; align-items: end; }

.tabs { display: flex; gap: var(--escenia-space-2); flex-wrap: wrap; }
.tab { padding: 9px 16px; font: inherit; font-weight: 600; font-size: 0.85rem; color: var(--escenia-color-text-muted); background: transparent; border: 1px solid var(--escenia-color-border); border-radius: var(--escenia-radius-sm); cursor: pointer; transition: all 0.14s ease; }
.tab:hover { color: var(--escenia-color-text); }
.tab.is-active { color: #fff; background: var(--escenia-color-primary); border-color: transparent; }

.lista { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: var(--escenia-space-2); }
.lista li { padding: 12px; border: 1px solid var(--escenia-color-border); border-radius: var(--escenia-radius-sm); background: rgba(4, 16, 29, 0.35); display: flex; flex-direction: column; gap: 6px; }
.hit__head { display: flex; align-items: center; gap: 10px; }
.hit__text { margin: 0; font-size: 0.9rem; }

.answer { padding: var(--escenia-space-4); border-radius: var(--escenia-radius-md); border: 1px solid color-mix(in srgb, var(--escenia-color-primary) 40%, transparent); background: color-mix(in srgb, var(--escenia-color-primary) 6%, transparent); }
.answer p { margin: 0 0 6px; }

.content { margin: 0; padding: var(--escenia-space-4); border-radius: var(--escenia-radius-sm); background: rgba(4, 16, 29, 0.5); border: 1px solid var(--escenia-color-border); white-space: pre-wrap; word-break: break-word; font: inherit; font-size: 0.9rem; line-height: 1.5; }
textarea.control { resize: vertical; }
</style>
