<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { AppButton } from '@escenia/ui'
import { ApiError } from '@escenia/api-client'
import type {
  Automation,
  AutomationRun,
  AutomationRunStatus,
  AutomationStep,
  AutomationStepType,
  TriggerEvent,
} from '@escenia/types'

import { fecha } from '@/lib/eventLabels'
import { api } from '@/lib/api'

interface PasoForm {
  type: AutomationStepType
  url: string
  secret: string
  tag: string
  subject: string
  body: string
  seconds: string
}

const automations = ref<Automation[]>([])
const runs = ref<Record<string, AutomationRun[]>>({})
const runsAbiertos = ref<string | null>(null)
const loading = ref(true)
const error = ref<string | null>(null)
const busy = ref(false)
const creando = ref(false)

const nombre = ref('')
const disparador = ref<TriggerEvent>('registration.completed')
const pasos = ref<PasoForm[]>([nuevoPaso()])

const disparadores: [TriggerEvent, string][] = [
  ['registration.completed', 'Registro completado'],
  ['order.paid', 'Orden pagada'],
  ['event.ended', 'Evento finalizado'],
]
const tiposPaso: [AutomationStepType, string][] = [
  ['notify', 'Notificar'],
  ['webhook', 'Webhook'],
  ['tag_contact', 'Etiquetar contacto'],
  ['wait', 'Esperar'],
]
const tipoPasoLabel: Record<AutomationStepType, string> = {
  notify: 'Notificar',
  webhook: 'Webhook',
  tag_contact: 'Etiquetar contacto',
  wait: 'Esperar',
}
const disparadorLabel: Record<TriggerEvent, string> = {
  'registration.completed': 'Registro completado',
  'order.paid': 'Orden pagada',
  'event.ended': 'Evento finalizado',
}
const estadoEjecucion: Record<AutomationRunStatus, { label: string; clase: string }> = {
  running: { label: 'En curso', clase: 'chip--primary' },
  waiting: { label: 'En espera', clase: '' },
  completed: { label: 'Completada', clase: 'chip--live' },
  failed: { label: 'Falló', clase: 'chip--danger' },
}

function nuevoPaso(): PasoForm {
  return { type: 'notify', url: '', secret: '', tag: '', subject: '', body: '', seconds: '60' }
}

function message(e: unknown): string {
  return e instanceof ApiError ? e.message : 'Algo salió mal.'
}

async function load(): Promise<void> {
  loading.value = true
  error.value = null
  try {
    automations.value = (await api.automations()).data
  } catch (e) {
    error.value = message(e)
  } finally {
    loading.value = false
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

function agregarPaso(): void {
  if (pasos.value.length < 20) pasos.value.push(nuevoPaso())
}

function quitarPaso(i: number): void {
  if (pasos.value.length > 1) pasos.value.splice(i, 1)
}

function configDePaso(p: PasoForm): Record<string, unknown> {
  switch (p.type) {
    case 'webhook':
      return p.secret.trim() ? { url: p.url, secret: p.secret } : { url: p.url }
    case 'tag_contact':
      return { tag: p.tag }
    case 'notify':
      return { subject: p.subject, body: p.body }
    case 'wait':
      return { seconds: Math.max(1, Number(p.seconds) || 60) }
  }
}

function crear(): void {
  if (nombre.value.trim() === '') return
  run(async () => {
    await api.createAutomation({
      name: nombre.value,
      trigger: disparador.value,
      steps: pasos.value.map((p) => ({ type: p.type, config: configDePaso(p) })),
    })
    nombre.value = ''
    disparador.value = 'registration.completed'
    pasos.value = [nuevoPaso()]
    creando.value = false
    automations.value = (await api.automations()).data
  })
}

function alternarActiva(a: Automation): void {
  run(async () => {
    const updated = (await api.setAutomationActive(a.id, !a.is_active)).data
    const i = automations.value.findIndex((x) => x.id === a.id)
    if (i !== -1) automations.value[i] = updated
  })
}

function verEjecuciones(a: Automation): void {
  if (runsAbiertos.value === a.id) {
    runsAbiertos.value = null
    return
  }
  runsAbiertos.value = a.id
  if (runs.value[a.id] !== undefined) return
  run(async () => {
    runs.value[a.id] = (await api.automationRuns(a.id)).data
  })
}

function detallePaso(step: AutomationStep): string {
  const c = step.config ?? {}
  switch (step.type) {
    case 'webhook':
      return typeof c.url === 'string' ? c.url : ''
    case 'tag_contact':
      return typeof c.tag === 'string' ? c.tag : ''
    case 'notify':
      return typeof c.subject === 'string' ? c.subject : ''
    case 'wait':
      return typeof c.seconds === 'number' ? `${c.seconds}s` : ''
    default:
      return ''
  }
}

onMounted(load)
</script>

<template>
  <section class="stack">
    <div class="page-head">
      <h1>Automatizaciones</h1>
      <p>Flujos que reaccionan a eventos del sistema: registros, pagos y cierres de evento.</p>
    </div>

    <div class="actions" style="justify-content: flex-end">
      <AppButton @click="creando = !creando">{{ creando ? 'Cerrar' : '+ Nueva automatización' }}</AppButton>
    </div>

    <!-- Crear -->
    <div v-if="creando" class="panel stack">
      <h2>Nueva automatización</h2>
      <div class="fila">
        <label class="field grow"><span>Nombre</span><input v-model="nombre" class="control" placeholder="Bienvenida al registrarse" /></label>
        <label class="field">
          <span>Disparador</span>
          <select v-model="disparador" class="control">
            <option v-for="[t, label] in disparadores" :key="t" :value="t">{{ label }}</option>
          </select>
        </label>
      </div>

      <h3 class="sub">Pasos</h3>
      <div v-for="(p, i) in pasos" :key="i" class="paso">
        <div class="paso__head">
          <span class="paso__num">{{ i + 1 }}</span>
          <select v-model="p.type" class="control">
            <option v-for="[t, label] in tiposPaso" :key="t" :value="t">{{ label }}</option>
          </select>
          <button type="button" class="mini" :disabled="pasos.length === 1" title="Quitar paso" aria-label="Quitar paso" @click="quitarPaso(i)">✕</button>
        </div>

        <div class="paso__cfg">
          <template v-if="p.type === 'webhook'">
            <label class="field grow"><span>URL</span><input v-model="p.url" class="control" placeholder="https://…" /></label>
            <label class="field grow"><span>Secreto (opcional)</span><input v-model="p.secret" class="control" placeholder="Firma HMAC" /></label>
          </template>
          <template v-else-if="p.type === 'tag_contact'">
            <label class="field grow"><span>Etiqueta</span><input v-model="p.tag" class="control" placeholder="vip" /></label>
          </template>
          <template v-else-if="p.type === 'notify'">
            <label class="field grow"><span>Asunto</span><input v-model="p.subject" class="control" placeholder="¡Gracias por registrarte!" /></label>
            <label class="field grow"><span>Mensaje</span><input v-model="p.body" class="control" placeholder="Nos vemos en el evento." /></label>
          </template>
          <template v-else>
            <label class="field"><span>Esperar (seg.)</span><input v-model="p.seconds" type="number" min="1" class="control" placeholder="60" /></label>
          </template>
        </div>
      </div>

      <div class="actions">
        <AppButton variant="ghost" :disabled="pasos.length >= 20" @click="agregarPaso">+ Añadir paso</AppButton>
        <AppButton :disabled="busy || !nombre.trim()" @click="crear">Crear automatización</AppButton>
      </div>
    </div>

    <p v-if="loading" class="muted">Cargando…</p>
    <p v-else-if="error && automations.length === 0" role="alert" class="error-text">{{ error }}</p>
    <p v-else-if="automations.length === 0" class="panel empty">Aún no hay automatizaciones.</p>

    <div v-else class="stack">
      <div v-for="a in automations" :key="a.id" class="panel stack auto-card">
        <div class="auto-head">
          <div>
            <div class="actions" style="gap: 8px; margin-bottom: 6px">
              <strong class="auto-name">{{ a.name }}</strong>
              <span class="chip" :class="a.is_active ? 'chip--live' : ''">{{ a.is_active ? 'Activa' : 'Pausada' }}</span>
            </div>
            <p class="muted small">Cuando: {{ disparadorLabel[a.trigger] }}</p>
          </div>
          <div class="actions">
            <AppButton variant="ghost" :disabled="busy" @click="verEjecuciones(a)">
              {{ runsAbiertos === a.id ? 'Ocultar' : 'Ejecuciones' }}
            </AppButton>
            <AppButton :variant="a.is_active ? 'danger' : 'primary'" :disabled="busy" @click="alternarActiva(a)">
              {{ a.is_active ? 'Pausar' : 'Activar' }}
            </AppButton>
          </div>
        </div>

        <ol v-if="a.steps && a.steps.length" class="flow">
          <li v-for="s in a.steps" :key="s.id">
            <span class="chip chip--primary">{{ tipoPasoLabel[s.type] }}</span>
            <span v-if="detallePaso(s)" class="muted small flow__detail">{{ detallePaso(s) }}</span>
          </li>
        </ol>

        <div v-if="runsAbiertos === a.id" class="runs">
          <p v-if="(runs[a.id]?.length ?? 0) === 0" class="muted small">Sin ejecuciones registradas.</p>
          <table v-else class="admin-table">
            <thead>
              <tr><th>Estado</th><th>Paso</th><th>Fecha</th><th>Registro</th></tr>
            </thead>
            <tbody>
              <tr v-for="r in runs[a.id] ?? []" :key="r.id">
                <td><span class="chip" :class="estadoEjecucion[r.status].clase">{{ estadoEjecucion[r.status].label }}</span></td>
                <td class="muted">{{ r.current_position }}</td>
                <td class="muted">{{ fecha(r.created_at) }}</td>
                <td class="muted small">{{ (r.log ?? []).map((l) => l.summary).join(' · ') || '—' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <p v-if="error && automations.length" role="alert" class="error-text">{{ error }}</p>
  </section>
</template>

<style scoped>
.small { font-size: 0.8rem; margin: 0; }
.sub { font-size: 0.95rem; margin: var(--escenia-space-2) 0 0; }
.grow { flex: 1; }
.fila { display: flex; gap: var(--escenia-space-3); flex-wrap: wrap; align-items: end; }

.paso { border: 1px solid var(--escenia-color-border); border-radius: var(--escenia-radius-sm); padding: var(--escenia-space-3); background: rgba(4, 16, 29, 0.3); display: flex; flex-direction: column; gap: var(--escenia-space-2); }
.paso__head { display: flex; align-items: center; gap: var(--escenia-space-2); }
.paso__head .control { max-width: 220px; }
.paso__num { display: grid; place-items: center; width: 24px; height: 24px; flex-shrink: 0; border-radius: 50%; background: color-mix(in srgb, var(--escenia-color-primary) 18%, transparent); color: var(--escenia-color-primary); font-weight: 700; font-size: 0.78rem; }
.paso__cfg { display: flex; gap: var(--escenia-space-3); flex-wrap: wrap; padding-left: 32px; }
.mini { width: 28px; height: 28px; margin-left: auto; font: inherit; color: var(--escenia-color-text-muted); background: transparent; border: 1px solid var(--escenia-color-border); border-radius: 6px; cursor: pointer; }
.mini:hover:not(:disabled) { color: var(--escenia-color-danger, #ff6b6b); border-color: var(--escenia-color-border-strong); }
.mini:disabled { opacity: 0.4; cursor: not-allowed; }

.auto-head { display: flex; align-items: flex-start; justify-content: space-between; gap: var(--escenia-space-4); flex-wrap: wrap; }
.auto-name { font-size: 1.05rem; }

.flow { list-style: none; margin: 0; padding: 0; display: flex; gap: var(--escenia-space-2); flex-wrap: wrap; align-items: center; }
.flow li { display: inline-flex; align-items: center; gap: 8px; }
.flow li:not(:last-child)::after { content: '→'; color: var(--escenia-color-text-muted); margin-left: var(--escenia-space-2); }
.flow__detail { max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.runs { border-top: 1px solid var(--escenia-color-border); padding-top: var(--escenia-space-3); overflow-x: auto; }
</style>
