<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { AppButton } from '@escenia/ui'
import { ApiError } from '@escenia/api-client'
import type {
  CapabilityKey,
  EventCapability,
  EventModel,
  EventSession,
  EventSpeaker,
  EventStatus,
  SpeakerRole,
} from '@escenia/types'

import { capacidad, estadoEvento, fecha, tipoEvento, todasLasCapacidades } from '@/lib/eventLabels'
import { api } from '@/lib/api'

const route = useRoute()
const id = route.params.id as string

const event = ref<EventModel | null>(null)
const capabilities = ref<EventCapability[]>([])
const loading = ref(true)
const error = ref<string | null>(null)
const busy = ref(false)

const accionEstado: Record<EventStatus, string> = {
  draft: 'Volver a borrador',
  scheduled: 'Programar',
  live: 'Iniciar',
  ended: 'Finalizar',
  archived: 'Archivar',
  canceled: 'Cancelar',
}

const enabledMap = computed(() => new Map(capabilities.value.map((c) => [c.capability, c.enabled])))

const sesiones = ref<EventSession[]>([])
const ponentes = ref<EventSpeaker[]>([])

const nuevaSesion = ref('')
const nuevoPonente = ref({ name: '', role: 'speaker' as SpeakerRole })

const rolesPonente: [SpeakerRole, string][] = [
  ['host', 'Anfitrión'],
  ['speaker', 'Ponente'],
  ['moderator', 'Moderador'],
  ['panelist', 'Panelista'],
]

function message(e: unknown): string {
  return e instanceof ApiError ? e.message : 'Algo salió mal.'
}

async function load(): Promise<void> {
  loading.value = true
  error.value = null
  try {
    const [ev, caps, ss, sp] = await Promise.all([
      api.event(id),
      api.eventCapabilities(id),
      api.eventSessions(id),
      api.eventSpeakers(id),
    ])
    event.value = ev.data
    capabilities.value = caps.data
    sesiones.value = ss.data
    ponentes.value = sp.data
  } catch (e) {
    error.value = message(e)
  } finally {
    loading.value = false
  }
}

async function crearSesion(): Promise<void> {
  if (nuevaSesion.value.trim() === '') return
  busy.value = true
  error.value = null
  try {
    await api.createEventSession(id, { title: nuevaSesion.value })
    nuevaSesion.value = ''
    sesiones.value = (await api.eventSessions(id)).data
  } catch (e) {
    error.value = message(e)
  } finally {
    busy.value = false
  }
}

async function agregarPonente(): Promise<void> {
  if (nuevoPonente.value.name.trim() === '') return
  busy.value = true
  error.value = null
  try {
    await api.addEventSpeaker(id, { name: nuevoPonente.value.name, role: nuevoPonente.value.role })
    nuevoPonente.value = { name: '', role: 'speaker' }
    ponentes.value = (await api.eventSpeakers(id)).data
  } catch (e) {
    error.value = message(e)
  } finally {
    busy.value = false
  }
}

async function transicionar(status: EventStatus): Promise<void> {
  busy.value = true
  error.value = null
  try {
    event.value = (await api.transitionEvent(id, status)).data
  } catch (e) {
    error.value = message(e)
  } finally {
    busy.value = false
  }
}

async function alternarCapacidad(key: CapabilityKey): Promise<void> {
  busy.value = true
  error.value = null
  try {
    const enabled = enabledMap.value.get(key) === true
    await api.setEventCapability(id, key, !enabled)
    capabilities.value = (await api.eventCapabilities(id)).data
  } catch (e) {
    error.value = message(e)
  } finally {
    busy.value = false
  }
}

const sessionLabel: Record<string, string> = { scheduled: 'Programada', live: 'En vivo', ended: 'Finalizada', canceled: 'Cancelada' }
const roleLabel: Record<string, string> = { host: 'Anfitrión', speaker: 'Ponente', moderator: 'Moderador', panelist: 'Panelista' }

onMounted(load)
</script>

<template>
  <section class="stack">
    <RouterLink :to="{ name: 'events' }" class="back muted">‹ Eventos</RouterLink>

    <p v-if="loading" class="muted">Cargando…</p>
    <p v-else-if="error && !event" class="error-text">{{ error }}</p>

    <template v-else-if="event">
      <div class="head panel">
        <div>
          <div class="actions" style="margin-bottom: 8px">
            <span class="chip role-badge">{{ tipoEvento[event.type] }}</span>
            <span class="chip" :class="estadoEvento[event.status].clase">{{ estadoEvento[event.status].label }}</span>
          </div>
          <h1>{{ event.title }}</h1>
          <p v-if="event.description" class="muted">{{ event.description }}</p>
          <p class="muted small">
            Inicio: {{ fecha(event.scheduled_start_at) }} · Zona horaria: {{ event.timezone }}
          </p>
        </div>
        <div class="head-links">
          <RouterLink :to="{ name: 'event-studio', params: { id } }" class="head-link">
            Studio ›
          </RouterLink>
          <RouterLink :to="{ name: 'event-registration', params: { id } }" class="head-link">
            Registro ›
          </RouterLink>
          <RouterLink :to="{ name: 'event-analytics', params: { id } }" class="head-link">
            Analíticas ›
          </RouterLink>
          <RouterLink :to="{ name: 'event-commerce', params: { id } }" class="head-link">
            Comercio ›
          </RouterLink>
          <RouterLink :to="{ name: 'event-engagement', params: { id } }" class="head-link">
            Engagement ›
          </RouterLink>
        </div>
      </div>

      <div class="panel stack">
        <h2>Estado del evento</h2>
        <p class="muted">
          Estado actual: <strong>{{ estadoEvento[event.status].label }}</strong>. Transiciones
          permitidas:
        </p>
        <div class="actions">
          <AppButton
            v-for="s in event.allowed_transitions"
            :key="s"
            :variant="s === 'canceled' ? 'danger' : 'primary'"
            :disabled="busy"
            @click="transicionar(s)"
          >
            {{ accionEstado[s] }}
          </AppButton>
          <span v-if="event.allowed_transitions.length === 0" class="muted">Sin transiciones disponibles.</span>
        </div>
      </div>

      <div class="panel stack">
        <h2>Capacidades</h2>
        <p class="muted">Activa las funciones que este evento debe ofrecer.</p>
        <div class="caps">
          <button
            v-for="key in todasLasCapacidades"
            :key="key"
            type="button"
            class="cap"
            :class="{ 'is-on': enabledMap.get(key) === true }"
            :disabled="busy"
            @click="alternarCapacidad(key)"
          >
            <span class="cap__dot"></span>
            {{ capacidad[key] }}
          </button>
        </div>
      </div>

      <div class="cols">
        <div class="panel stack">
          <h2>Sesiones</h2>
          <p v-if="sesiones.length === 0" class="muted">Aún no hay sesiones.</p>
          <ul v-else class="lista">
            <li v-for="s in sesiones" :key="s.id">
              <strong>{{ s.title }}</strong>
              <span class="chip" :class="s.status === 'live' ? 'chip--live' : ''">{{ sessionLabel[s.status] }}</span>
            </li>
          </ul>
          <div class="add">
            <input v-model="nuevaSesion" class="control grow" placeholder="Nueva sesión…" @keyup.enter="crearSesion" />
            <AppButton :disabled="busy || !nuevaSesion.trim()" @click="crearSesion">Añadir</AppButton>
          </div>
        </div>

        <div class="panel stack">
          <h2>Ponentes</h2>
          <p v-if="ponentes.length === 0" class="muted">Aún no hay ponentes.</p>
          <ul v-else class="lista">
            <li v-for="p in ponentes" :key="p.id">
              <strong>{{ p.name }}</strong>
              <span class="chip role-badge">{{ roleLabel[p.role] }}</span>
            </li>
          </ul>
          <div class="add">
            <input v-model="nuevoPonente.name" class="control grow" placeholder="Nombre del ponente…" />
            <select v-model="nuevoPonente.role" class="control">
              <option v-for="[r, label] in rolesPonente" :key="r" :value="r">{{ label }}</option>
            </select>
            <AppButton :disabled="busy || !nuevoPonente.name.trim()" @click="agregarPonente">Añadir</AppButton>
          </div>
        </div>
      </div>

      <p v-if="error" class="error-text">{{ error }}</p>
    </template>
  </section>
</template>

<style scoped>
.back {
  text-decoration: none;
  font-size: 0.85rem;
}

.back:hover {
  color: var(--escenia-color-text);
}

.head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--escenia-space-4);
}

.head h1 {
  margin: 0;
  font-size: 1.5rem;
}

.head-links {
  display: flex;
  flex-direction: column;
  gap: var(--escenia-space-2);
  flex-shrink: 0;
}

.head-link {
  flex-shrink: 0;
  padding: 9px 14px;
  font-size: 0.85rem;
  font-weight: 600;
  color: var(--escenia-color-primary);
  text-decoration: none;
  border: 1px solid color-mix(in srgb, var(--escenia-color-primary) 40%, transparent);
  border-radius: var(--escenia-radius-sm);
  transition: background 0.15s ease;
}

.head-link:hover {
  background: color-mix(in srgb, var(--escenia-color-primary) 10%, transparent);
}

.small {
  font-size: 0.8rem;
  margin-top: 6px;
}

.caps {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
  gap: var(--escenia-space-2);
}

.cap {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px var(--escenia-space-3);
  font: inherit;
  font-size: 0.85rem;
  text-align: left;
  color: var(--escenia-color-text-muted);
  background: rgba(4, 16, 29, 0.4);
  border: 1px solid var(--escenia-color-border);
  border-radius: var(--escenia-radius-sm);
  cursor: pointer;
  transition: all 0.14s ease;
}

.cap:hover:not(:disabled) {
  border-color: var(--escenia-color-border-strong);
}

.cap__dot {
  width: 9px;
  height: 9px;
  border-radius: 50%;
  background: var(--escenia-color-border-strong);
  transition: background 0.14s ease;
}

.cap.is-on {
  color: var(--escenia-color-text);
  border-color: color-mix(in srgb, var(--escenia-color-primary) 45%, transparent);
  background: color-mix(in srgb, var(--escenia-color-primary) 8%, transparent);
}

.cap.is-on .cap__dot {
  background: var(--escenia-color-primary);
}

.cols {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
  gap: var(--escenia-space-6);
}

.lista {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: var(--escenia-space-2);
}

.lista li {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  padding: 10px var(--escenia-space-3);
  border: 1px solid var(--escenia-color-border);
  border-radius: var(--escenia-radius-sm);
  background: rgba(4, 16, 29, 0.35);
}
</style>
