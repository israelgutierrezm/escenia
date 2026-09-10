<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { AppButton } from '@escenia/ui'
import { ApiError } from '@escenia/api-client'
import type { ChatMessage, EventResource, Poll, PollStatus, Question } from '@escenia/types'

import { api } from '@/lib/api'

const route = useRoute()
const id = route.params.id as string

type Tab = 'chat' | 'qa' | 'encuestas' | 'recursos'
const tab = ref<Tab>('chat')
const tabs: [Tab, string][] = [
  ['chat', 'Chat'],
  ['qa', 'Preguntas'],
  ['encuestas', 'Encuestas'],
  ['recursos', 'Recursos'],
]

const chat = ref<ChatMessage[]>([])
const questions = ref<Question[]>([])
const polls = ref<Poll[]>([])
const resources = ref<EventResource[]>([])
const loading = ref(true)
const error = ref<string | null>(null)
const busy = ref(false)

const nuevoMensaje = ref('')
const respuestas = ref<Record<string, string>>({})
const nuevaPoll = ref({ question: '', options: ['', ''] })
const nuevoRecurso = ref({ title: '', url: '' })

function message(e: unknown): string {
  return e instanceof ApiError ? e.message : 'Algo salió mal.'
}

async function load(): Promise<void> {
  loading.value = true
  error.value = null
  try {
    const [c, q, p, r] = await Promise.all([
      api.eventChat(id),
      api.eventQuestions(id),
      api.eventPolls(id),
      api.eventResources(id),
    ])
    chat.value = c.data
    questions.value = q.data
    polls.value = p.data
    resources.value = r.data
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

function enviarChat(): void {
  if (nuevoMensaje.value.trim() === '') return
  run(async () => {
    await api.postEventChat(id, nuevoMensaje.value)
    nuevoMensaje.value = ''
    chat.value = (await api.eventChat(id)).data
  })
}

function responder(qid: string): void {
  const texto = respuestas.value[qid]
  if (!texto || texto.trim() === '') return
  run(async () => {
    await api.answerQuestion(qid, texto)
    respuestas.value[qid] = ''
    questions.value = (await api.eventQuestions(id)).data
  })
}

function crearPoll(): void {
  const opciones = nuevaPoll.value.options.map((o) => o.trim()).filter((o) => o !== '')
  if (nuevaPoll.value.question.trim() === '' || opciones.length < 2) return
  run(async () => {
    await api.createPoll(id, { question: nuevaPoll.value.question, options: opciones })
    nuevaPoll.value = { question: '', options: ['', ''] }
    polls.value = (await api.eventPolls(id)).data
  })
}

function togglePoll(poll: Poll): void {
  run(async () => {
    if (poll.status === 'open') await api.closePoll(poll.id)
    else await api.openPoll(poll.id)
    polls.value = (await api.eventPolls(id)).data
  })
}

function agregarRecurso(): void {
  if (nuevoRecurso.value.title.trim() === '' || nuevoRecurso.value.url.trim() === '') return
  run(async () => {
    await api.addEventResource(id, { title: nuevoRecurso.value.title, url: nuevoRecurso.value.url })
    nuevoRecurso.value = { title: '', url: '' }
    resources.value = (await api.eventResources(id)).data
  })
}

function pollTotal(poll: Poll): number {
  return (poll.options ?? []).reduce((s, o) => s + o.votes_count, 0)
}

const pollEstado: Record<PollStatus, { label: string; clase: string }> = {
  draft: { label: 'Borrador', clase: '' },
  open: { label: 'Abierta', clase: 'chip--live' },
  closed: { label: 'Cerrada', clase: '' },
}

const abiertas = computed(() => questions.value.filter((q) => q.status === 'open'))

onMounted(load)
</script>

<template>
  <section class="stack">
    <RouterLink :to="{ name: 'event-detail', params: { id } }" class="back muted">‹ Volver al evento</RouterLink>

    <div class="page-head">
      <h1>Engagement</h1>
      <p>Chat, preguntas, encuestas y recursos del evento en vivo.</p>
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
        <span v-if="key === 'qa' && abiertas.length" class="count">{{ abiertas.length }}</span>
      </button>
    </div>

    <p v-if="loading" class="muted">Cargando…</p>
    <p v-else-if="error" class="error-text">{{ error }}</p>

    <template v-else>
      <!-- Chat -->
      <div v-if="tab === 'chat'" class="panel stack">
        <div class="chat-box">
          <p v-if="chat.length === 0" class="empty">Sin mensajes.</p>
          <div v-for="m in chat" :key="m.id" class="msg">
            <strong :class="{ host: m.is_host }">{{ m.author_name }}<span v-if="m.is_host" class="chip chip--primary tag">Anfitrión</span></strong>
            <span>{{ m.body }}</span>
          </div>
        </div>
        <div class="actions">
          <input v-model="nuevoMensaje" class="control grow" placeholder="Escribe como anfitrión…" @keyup.enter="enviarChat" />
          <AppButton :disabled="busy || !nuevoMensaje.trim()" @click="enviarChat">Enviar</AppButton>
        </div>
      </div>

      <!-- Q&A -->
      <div v-else-if="tab === 'qa'" class="panel stack">
        <p v-if="questions.length === 0" class="empty">Sin preguntas.</p>
        <div v-for="q in questions" :key="q.id" class="qa">
          <div class="qa__head">
            <div>
              <strong>{{ q.author_name }}</strong> · <span class="muted">{{ q.votes_count }} votos</span>
              <span class="chip" :class="q.status === 'answered' ? 'chip--live' : ''">{{ q.status === 'answered' ? 'Respondida' : 'Abierta' }}</span>
            </div>
          </div>
          <p class="qa__body">{{ q.body }}</p>
          <p v-if="q.answer" class="qa__answer"><strong>Respuesta:</strong> {{ q.answer }}</p>
          <div v-else class="actions">
            <input v-model="respuestas[q.id]" class="control grow" placeholder="Responder…" />
            <AppButton :disabled="busy" @click="responder(q.id)">Responder</AppButton>
          </div>
        </div>
      </div>

      <!-- Encuestas -->
      <div v-else-if="tab === 'encuestas'" class="stack">
        <div class="panel stack">
          <h2>Nueva encuesta</h2>
          <label class="field"><span>Pregunta</span><input v-model="nuevaPoll.question" class="control" placeholder="¿Cuál prefieres?" /></label>
          <div v-for="(_, i) in nuevaPoll.options" :key="i" class="field">
            <input v-model="nuevaPoll.options[i]" class="control" :placeholder="`Opción ${i + 1}`" />
          </div>
          <div class="actions">
            <AppButton variant="ghost" @click="nuevaPoll.options.push('')">+ Opción</AppButton>
            <AppButton :disabled="busy" @click="crearPoll">Crear encuesta</AppButton>
          </div>
        </div>

        <div v-for="poll in polls" :key="poll.id" class="panel stack">
          <div class="actions" style="justify-content: space-between">
            <strong>{{ poll.question }}</strong>
            <div class="actions">
              <span class="chip" :class="pollEstado[poll.status].clase">{{ pollEstado[poll.status].label }}</span>
              <AppButton variant="ghost" :disabled="busy" @click="togglePoll(poll)">{{ poll.status === 'open' ? 'Cerrar' : 'Abrir' }}</AppButton>
            </div>
          </div>
          <div v-for="o in poll.options ?? []" :key="o.id" class="opt">
            <div class="opt__bar" :style="{ width: `${pollTotal(poll) ? (o.votes_count / pollTotal(poll)) * 100 : 0}%` }"></div>
            <span class="opt__label">{{ o.label }}</span>
            <span class="opt__count muted">{{ o.votes_count }}</span>
          </div>
        </div>
      </div>

      <!-- Recursos -->
      <div v-else class="panel stack">
        <div class="crear">
          <label class="field"><span>Título</span><input v-model="nuevoRecurso.title" class="control" placeholder="Presentación" /></label>
          <label class="field"><span>URL</span><input v-model="nuevoRecurso.url" class="control" placeholder="https://…" /></label>
          <AppButton :disabled="busy" @click="agregarRecurso">Añadir recurso</AppButton>
        </div>
        <p v-if="resources.length === 0" class="empty">Sin recursos.</p>
        <ul v-else class="lista">
          <li v-for="r in resources" :key="r.id">
            <div><strong>{{ r.title }}</strong> <a :href="r.url" target="_blank" rel="noopener" class="muted">{{ r.url }}</a></div>
            <span class="muted">{{ r.downloads_count }} descargas</span>
          </li>
        </ul>
      </div>
    </template>
  </section>
</template>

<style scoped>
.back { text-decoration: none; font-size: 0.85rem; }
.back:hover { color: var(--escenia-color-text); }

.tabs { display: flex; gap: var(--escenia-space-2); flex-wrap: wrap; }
.tab {
  display: inline-flex; align-items: center; gap: 8px;
  padding: 9px 16px; font: inherit; font-weight: 600; font-size: 0.85rem;
  color: var(--escenia-color-text-muted); background: transparent;
  border: 1px solid var(--escenia-color-border); border-radius: var(--escenia-radius-sm);
  cursor: pointer; transition: all 0.14s ease;
}
.tab:hover { color: var(--escenia-color-text); }
.tab.is-active { color: #fff; background: var(--escenia-color-primary); border-color: transparent; }
.tab .count { background: rgba(255,255,255,0.25); border-radius: 999px; padding: 0 7px; font-size: 0.7rem; }

.grow { flex: 1; }
.tag { margin-left: 8px; }

.chat-box { display: flex; flex-direction: column; gap: var(--escenia-space-2); max-height: 360px; overflow-y: auto; }
.msg { display: flex; flex-direction: column; gap: 2px; padding: 8px 10px; border-radius: var(--escenia-radius-sm); background: rgba(4,16,29,0.4); }
.msg strong.host { color: var(--escenia-color-primary); }

.qa { border-top: 1px solid var(--escenia-color-border); padding-top: var(--escenia-space-3); }
.qa__body { margin: 6px 0; }
.qa__answer { margin: 6px 0 0; color: var(--escenia-color-text-muted); }

.opt { position: relative; padding: 10px 12px; border: 1px solid var(--escenia-color-border); border-radius: var(--escenia-radius-sm); display: flex; align-items: center; gap: 10px; overflow: hidden; }
.opt__bar { position: absolute; inset: 0 auto 0 0; background: color-mix(in srgb, var(--escenia-color-primary) 18%, transparent); transition: width 0.3s ease; }
.opt__label { position: relative; flex: 1; }
.opt__count { position: relative; }

.crear { display: grid; grid-template-columns: 1fr 1fr auto; gap: var(--escenia-space-3); align-items: end; }
.lista { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: var(--escenia-space-2); }
.lista li { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 10px 12px; border: 1px solid var(--escenia-color-border); border-radius: var(--escenia-radius-sm); background: rgba(4,16,29,0.35); }
.lista a { text-decoration: none; }

@media (max-width: 640px) { .crear { grid-template-columns: 1fr; } }
</style>
