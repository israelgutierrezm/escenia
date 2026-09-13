<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { AppButton } from '@escenia/ui'
import { ApiError } from '@escenia/api-client'
import type {
  AssessmentQuestionType,
  AssessmentSubmission,
  Certificate,
} from '@escenia/types'

import { fecha } from '@/lib/eventLabels'
import { api } from '@/lib/api'

interface OpcionForm {
  key: string
  label: string
  correct: boolean
}
interface PreguntaForm {
  prompt: string
  type: AssessmentQuestionType
  points: number
  options: OpcionForm[]
}

const route = useRoute()
const id = route.params.id as string

type Tab = 'evaluacion' | 'finalizacion' | 'entregas' | 'certificados'
const tab = ref<Tab>('evaluacion')
const tabs: [Tab, string][] = [
  ['evaluacion', 'Evaluación'],
  ['finalizacion', 'Finalización'],
  ['entregas', 'Entregas'],
  ['certificados', 'Certificados'],
]

const loading = ref(true)
const error = ref<string | null>(null)
const ok = ref<string | null>(null)
const busy = ref(false)
const copiado = ref<string | null>(null)

// Evaluación
const evalForm = ref<{ title: string; passing_score: number; is_published: boolean; questions: PreguntaForm[] }>({
  title: '',
  passing_score: 70,
  is_published: false,
  questions: [],
})
const tipos: [AssessmentQuestionType, string][] = [
  ['single_choice', 'Opción única'],
  ['multiple_choice', 'Opción múltiple'],
  ['true_false', 'Verdadero / Falso'],
]

// Finalización
const reglaForm = ref<{ minutos: string; require_assessment: boolean }>({ minutos: '', require_assessment: false })
const reglaExiste = ref(false)

// Entregas
const submissions = ref<AssessmentSubmission[]>([])

// Certificados
const certificates = ref<Certificate[]>([])

function message(e: unknown): string {
  return e instanceof ApiError ? e.message : 'Algo salió mal.'
}

async function run(fn: () => Promise<void>, successMsg?: string): Promise<void> {
  busy.value = true
  error.value = null
  ok.value = null
  try {
    await fn()
    if (successMsg !== undefined) ok.value = successMsg
  } catch (e) {
    error.value = message(e)
  } finally {
    busy.value = false
  }
}

async function load(): Promise<void> {
  loading.value = true
  error.value = null
  try {
    const [a, r, s, c] = await Promise.all([
      api.assessment(id),
      api.completionRule(id),
      api.submissions(id),
      api.certificates(id),
    ])
    if (a.data !== null) {
      evalForm.value = {
        title: a.data.title,
        passing_score: a.data.passing_score,
        is_published: a.data.is_published ?? false,
        questions: a.data.questions.map((q) => ({
          prompt: q.prompt,
          type: q.type,
          points: q.points ?? 1,
          options: q.options.map((o) => ({ key: o.key, label: o.label, correct: o.correct ?? false })),
        })),
      }
    }
    if (r.data !== null) {
      reglaExiste.value = true
      reglaForm.value = {
        minutos: r.data.min_watch_seconds !== null ? String(Math.round(r.data.min_watch_seconds / 60)) : '',
        require_assessment: r.data.require_assessment,
      }
    }
    submissions.value = s.data
    certificates.value = c.data
  } catch (e) {
    error.value = message(e)
  } finally {
    loading.value = false
  }
}

// --- Constructor de evaluación ---
function opcionNueva(i: number): OpcionForm {
  return { key: `o${i}`, label: '', correct: false }
}
function preguntaNueva(): PreguntaForm {
  return { prompt: '', type: 'single_choice', points: 1, options: [opcionNueva(1), opcionNueva(2)] }
}
function agregarPregunta(): void {
  if (evalForm.value.questions.length < 100) evalForm.value.questions.push(preguntaNueva())
}
function quitarPregunta(i: number): void {
  evalForm.value.questions.splice(i, 1)
}
function cambiarTipo(q: PreguntaForm): void {
  if (q.type === 'true_false') {
    q.options = [
      { key: 'true', label: 'Verdadero', correct: true },
      { key: 'false', label: 'Falso', correct: false },
    ]
  } else if (q.options.some((o) => o.key === 'true' || o.key === 'false')) {
    q.options = [opcionNueva(1), opcionNueva(2)]
  }
}
function agregarOpcion(q: PreguntaForm): void {
  if (q.options.length < 10) q.options.push(opcionNueva(q.options.length + 1))
}
function quitarOpcion(q: PreguntaForm, i: number): void {
  if (q.options.length > 2) q.options.splice(i, 1)
}
function marcarCorrecta(q: PreguntaForm, i: number): void {
  if (q.type === 'multiple_choice') {
    const opt = q.options[i]
    if (opt) opt.correct = !opt.correct
  } else {
    q.options.forEach((o, idx) => {
      o.correct = idx === i
    })
  }
}

function guardarEvaluacion(): void {
  if (evalForm.value.title.trim() === '') {
    error.value = 'La evaluación necesita un título.'
    return
  }
  run(async () => {
    const saved = (await api.saveAssessment(id, {
      title: evalForm.value.title,
      passing_score: evalForm.value.passing_score,
      is_published: evalForm.value.is_published,
      questions: evalForm.value.questions.map((q) => ({
        prompt: q.prompt,
        type: q.type,
        points: q.points,
        options: q.options.map((o, i) => ({ key: o.key || `o${i + 1}`, label: o.label, correct: o.correct })),
      })),
    })).data
    evalForm.value.is_published = saved.is_published ?? evalForm.value.is_published
  }, 'Evaluación guardada.')
}

function guardarRegla(): void {
  run(async () => {
    const saved = (await api.saveCompletionRule(id, {
      min_watch_seconds: reglaForm.value.minutos !== '' ? Math.round(Number(reglaForm.value.minutos) * 60) : null,
      require_assessment: reglaForm.value.require_assessment,
    })).data
    reglaExiste.value = true
    reglaForm.value = {
      minutos: saved.min_watch_seconds !== null ? String(Math.round(saved.min_watch_seconds / 60)) : '',
      require_assessment: saved.require_assessment,
    }
  }, 'Regla de finalización guardada.')
}

function emitirCertificados(): void {
  run(async () => {
    const res = (await api.issueCertificates(id)).data
    certificates.value = (await api.certificates(id)).data
    ok.value = `Se emitieron ${res.issued} certificado(s).`
  })
}

async function copiar(text: string, key: string): Promise<void> {
  try {
    await navigator.clipboard.writeText(text)
    copiado.value = key
    setTimeout(() => {
      if (copiado.value === key) copiado.value = null
    }, 1600)
  } catch {
    error.value = 'No se pudo copiar al portapapeles.'
  }
}

onMounted(load)
</script>

<template>
  <section class="stack">
    <RouterLink :to="{ name: 'event-detail', params: { id } }" class="back muted">‹ Volver al evento</RouterLink>

    <div class="page-head">
      <h1>Educación</h1>
      <p>Evaluación, reglas de finalización, entregas y certificados del evento.</p>
    </div>

    <div class="tabs">
      <button
        v-for="[key, label] in tabs"
        :key="key"
        type="button"
        class="tab"
        :class="{ 'is-active': tab === key }"
        @click="tab = key"
      >
        {{ label }}
        <span v-if="key === 'entregas' && submissions.length" class="count">{{ submissions.length }}</span>
        <span v-else-if="key === 'certificados' && certificates.length" class="count">{{ certificates.length }}</span>
      </button>
    </div>

    <p v-if="loading" class="muted">Cargando…</p>

    <template v-else>
      <!-- EVALUACIÓN -->
      <div v-if="tab === 'evaluacion'" class="stack">
        <div class="panel stack">
          <div class="fila">
            <label class="field grow"><span>Título de la evaluación</span><input v-model="evalForm.title" class="control" placeholder="Examen final" /></label>
            <label class="field"><span>Puntaje para aprobar (%)</span><input v-model.number="evalForm.passing_score" type="number" min="0" max="100" class="control" /></label>
          </div>
          <label class="check">
            <input v-model="evalForm.is_published" type="checkbox" />
            <span>Publicada (visible para asistentes)</span>
          </label>
        </div>

        <div v-for="(q, qi) in evalForm.questions" :key="qi" class="panel stack pregunta">
          <div class="pregunta__head">
            <span class="pregunta__num">{{ qi + 1 }}</span>
            <input v-model="q.prompt" class="control grow" placeholder="Enunciado de la pregunta" />
            <button type="button" class="mini" title="Quitar pregunta" aria-label="Quitar pregunta" @click="quitarPregunta(qi)">✕</button>
          </div>
          <div class="fila">
            <label class="field">
              <span>Tipo</span>
              <select v-model="q.type" class="control" @change="cambiarTipo(q)">
                <option v-for="[t, label] in tipos" :key="t" :value="t">{{ label }}</option>
              </select>
            </label>
            <label class="field"><span>Puntos</span><input v-model.number="q.points" type="number" min="1" max="100" class="control puntos" /></label>
          </div>

          <div class="opciones">
            <div v-for="(o, oi) in q.options" :key="oi" class="opcion">
              <button
                type="button"
                class="marca"
                :class="{ 'is-correct': o.correct, multi: q.type === 'multiple_choice' }"
                :title="o.correct ? 'Respuesta correcta' : 'Marcar como correcta'"
                :aria-label="o.correct ? 'Respuesta correcta' : 'Marcar como correcta'"
                :aria-pressed="o.correct"
                @click="marcarCorrecta(q, oi)"
              >
                <span v-if="o.correct" aria-hidden="true">✓</span>
              </button>
              <input
                v-model="o.label"
                class="control grow"
                :placeholder="`Opción ${oi + 1}`"
                :disabled="q.type === 'true_false'"
              />
              <button
                type="button"
                class="mini"
                :disabled="q.options.length <= 2 || q.type === 'true_false'"
                title="Quitar opción"
                aria-label="Quitar opción"
                @click="quitarOpcion(q, oi)"
              >
                ✕
              </button>
            </div>
            <AppButton
              v-if="q.type !== 'true_false'"
              variant="ghost"
              :disabled="q.options.length >= 10"
              @click="agregarOpcion(q)"
            >
              + Opción
            </AppButton>
          </div>
        </div>

        <div class="actions">
          <AppButton variant="ghost" :disabled="evalForm.questions.length >= 100" @click="agregarPregunta">+ Añadir pregunta</AppButton>
          <AppButton :disabled="busy || !evalForm.title.trim()" @click="guardarEvaluacion">Guardar evaluación</AppButton>
        </div>
      </div>

      <!-- FINALIZACIÓN -->
      <div v-else-if="tab === 'finalizacion'" class="panel stack">
        <h2>Regla de finalización</h2>
        <p class="muted">Define qué debe cumplir un asistente para considerarse que completó el evento.</p>
        <label class="field">
          <span>Minutos mínimos de visualización (opcional)</span>
          <input v-model="reglaForm.minutos" type="number" min="0" class="control" placeholder="Sin mínimo" />
        </label>
        <label class="check">
          <input v-model="reglaForm.require_assessment" type="checkbox" />
          <span>Requiere aprobar la evaluación</span>
        </label>
        <div class="actions">
          <AppButton :disabled="busy" @click="guardarRegla">{{ reglaExiste ? 'Actualizar regla' : 'Crear regla' }}</AppButton>
        </div>
      </div>

      <!-- ENTREGAS -->
      <div v-else-if="tab === 'entregas'" class="panel">
        <h2>Entregas de evaluación <span class="muted">({{ submissions.length }})</span></h2>
        <p v-if="submissions.length === 0" class="empty">Aún no hay entregas.</p>
        <div v-else class="table-wrap">
          <table class="admin-table">
            <thead><tr><th>Asistente</th><th>Puntaje</th><th>Resultado</th><th>Fecha</th></tr></thead>
            <tbody>
              <tr v-for="s in submissions" :key="s.id">
                <td>
                  <strong>{{ s.attendee?.name ?? '—' }}</strong>
                  <div class="muted email">{{ s.attendee?.email }}</div>
                </td>
                <td>{{ s.score }}%</td>
                <td><span class="chip" :class="s.passed ? 'chip--live' : 'chip--danger'">{{ s.passed ? 'Aprobado' : 'No aprobado' }}</span></td>
                <td class="muted">{{ fecha(s.submitted_at) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- CERTIFICADOS -->
      <div v-else class="panel stack">
        <div class="actions" style="justify-content: space-between">
          <h2 style="margin: 0">Certificados <span class="muted">({{ certificates.length }})</span></h2>
          <AppButton :disabled="busy" @click="emitirCertificados">Emitir certificados</AppButton>
        </div>
        <p class="muted small">Emite certificados para los asistentes que cumplen la regla de finalización.</p>

        <p v-if="certificates.length === 0" class="empty">Aún no se han emitido certificados.</p>
        <ul v-else class="lista">
          <li v-for="c in certificates" :key="c.id">
            <div>
              <strong>{{ c.recipient_name }}</strong>
              <span class="muted small mono">{{ c.code }} · {{ fecha(c.issued_at) }}</span>
            </div>
            <AppButton variant="ghost" @click="copiar(c.verify_url, c.id)">{{ copiado === c.id ? 'Copiado' : 'Copiar verificación' }}</AppButton>
          </li>
        </ul>
      </div>

      <p v-if="error" role="alert" class="error-text">{{ error }}</p>
      <p v-if="ok" role="status" class="ok-text">{{ ok }}</p>
    </template>
  </section>
</template>

<style scoped>
.back { text-decoration: none; font-size: 0.85rem; }
.back:hover { color: var(--escenia-color-text); }
.small { font-size: 0.8rem; margin: 4px 0 0; }
.grow { flex: 1; }
.mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }

.tabs { display: flex; gap: var(--escenia-space-2); flex-wrap: wrap; }
.tab { display: inline-flex; align-items: center; gap: 8px; padding: 9px 16px; font: inherit; font-weight: 600; font-size: 0.85rem; color: var(--escenia-color-text-muted); background: transparent; border: 1px solid var(--escenia-color-border); border-radius: var(--escenia-radius-sm); cursor: pointer; transition: all 0.14s ease; }
.tab:hover { color: var(--escenia-color-text); }
.tab.is-active { color: #fff; background: var(--escenia-color-primary); border-color: transparent; }
.tab .count { background: rgba(255, 255, 255, 0.25); border-radius: 999px; padding: 0 7px; font-size: 0.7rem; }

.fila { display: flex; gap: var(--escenia-space-3); flex-wrap: wrap; align-items: end; }
.check { display: flex; align-items: center; gap: 10px; font-size: 0.9rem; }
.puntos { max-width: 90px; }

.pregunta__head { display: flex; align-items: center; gap: var(--escenia-space-2); }
.pregunta__num { display: grid; place-items: center; width: 26px; height: 26px; flex-shrink: 0; border-radius: 50%; background: color-mix(in srgb, var(--escenia-color-primary) 18%, transparent); color: var(--escenia-color-primary); font-weight: 700; font-size: 0.8rem; }

.opciones { display: flex; flex-direction: column; gap: var(--escenia-space-2); padding-left: 34px; }
.opcion { display: flex; align-items: center; gap: var(--escenia-space-2); }
.marca { width: 26px; height: 26px; flex-shrink: 0; display: grid; place-items: center; border: 1px solid var(--escenia-color-border-strong); background: transparent; color: #fff; cursor: pointer; border-radius: 50%; font-size: 0.8rem; font-weight: 700; transition: all 0.14s ease; }
.marca.multi { border-radius: 6px; }
.marca:hover { border-color: var(--escenia-color-primary); }
.marca.is-correct { background: var(--escenia-color-accent, #17e0a6); border-color: transparent; color: #04101d; }

.mini { width: 28px; height: 28px; flex-shrink: 0; font: inherit; color: var(--escenia-color-text-muted); background: transparent; border: 1px solid var(--escenia-color-border); border-radius: 6px; cursor: pointer; }
.mini:hover:not(:disabled) { color: var(--escenia-color-text); border-color: var(--escenia-color-border-strong); }
.mini:disabled { opacity: 0.4; cursor: not-allowed; }

.table-wrap { overflow-x: auto; }
.email { font-size: 0.78rem; }
.lista { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: var(--escenia-space-2); }
.lista li { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 10px 12px; border: 1px solid var(--escenia-color-border); border-radius: var(--escenia-radius-sm); background: rgba(4, 16, 29, 0.35); }
.lista .mono { margin-left: 8px; }

.ok-text { color: var(--escenia-color-accent, #17e0a6); font-size: 0.9rem; }

@media (max-width: 640px) { .opciones { padding-left: 0; } }
</style>
