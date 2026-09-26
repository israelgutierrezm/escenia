<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { AppButton } from '@escenia/ui'
import { ApiError } from '@escenia/api-client'
import type { Connection, DirectoryPerson, Meeting, NetworkPerson } from '@escenia/types'

import { api } from '@/lib/attendeeApi'
import { useAttendeeStore } from '@/stores/attendee'

const store = useAttendeeStore()
const yo = computed(() => store.attendee?.id ?? '')

type Sub = 'personas' | 'conexiones' | 'reuniones'
const sub = ref<Sub>('personas')
const subs: [Sub, string][] = [
  ['personas', 'Personas'],
  ['conexiones', 'Conexiones'],
  ['reuniones', 'Reuniones'],
]

const personas = ref<DirectoryPerson[]>([])
const conexiones = ref<Connection[]>([])
const reuniones = ref<Meeting[]>([])
const busqueda = ref('')
const error = ref<string | null>(null)
const busy = ref(false)
const optIn = ref(false)

// Propuesta de reunión.
const paraId = ref('')
const cuando = ref('')
const duracion = ref(30)
const tema = ref('')

const conectados = computed(() =>
  conexiones.value
    .filter((c) => c.status === 'accepted')
    .map((c) => otro(c))
    .filter((p): p is NetworkPerson => p !== null),
)

function message(e: unknown): string {
  return e instanceof ApiError ? e.message : 'Algo salió mal.'
}

function otro(c: Connection): NetworkPerson | null {
  return c.requester?.id === yo.value ? c.addressee : c.requester
}

function esEntrante(c: Connection): boolean {
  return c.addressee?.id === yo.value
}

function fechaHora(iso: string): string {
  return new Date(iso).toLocaleString('es', { weekday: 'short', hour: '2-digit', minute: '2-digit', day: 'numeric', month: 'short' })
}

async function cargarPersonas(): Promise<void> {
  personas.value = (await api.networkingDirectory(busqueda.value.trim())).data
}
async function cargarConexiones(): Promise<void> {
  conexiones.value = (await api.connections()).data
}
async function cargarReuniones(): Promise<void> {
  reuniones.value = (await api.meetings()).data
}
async function cargarPreferencias(): Promise<void> {
  optIn.value = (await api.networkingPreferences()).data.opt_in
}

function cambiarOptIn(valor: boolean): void {
  run(async () => {
    optIn.value = (await api.setNetworkingOptIn(valor)).data.opt_in
  })
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

function conectar(p: DirectoryPerson): void {
  run(async () => {
    await api.requestConnection(p.id)
    await Promise.all([cargarPersonas(), cargarConexiones()])
  })
}
function responderConexion(id: string, aceptar: boolean): void {
  run(async () => {
    await (aceptar ? api.acceptConnection(id) : api.declineConnection(id))
    await Promise.all([cargarPersonas(), cargarConexiones()])
  })
}
function proponer(): void {
  if (paraId.value === '' || cuando.value === '') return
  run(async () => {
    await api.proposeMeeting({
      attendee_id: paraId.value,
      scheduled_at: new Date(cuando.value).toISOString(),
      duration_minutes: duracion.value,
      topic: tema.value.trim() || undefined,
    })
    paraId.value = ''
    cuando.value = ''
    tema.value = ''
    await cargarReuniones()
  })
}
function responderReunion(id: string, aceptar: boolean): void {
  run(async () => {
    await (aceptar ? api.acceptMeeting(id) : api.declineMeeting(id))
    await cargarReuniones()
  })
}
function cancelarReunion(id: string): void {
  run(async () => {
    await api.cancelMeeting(id)
    await cargarReuniones()
  })
}

const estadoConexion: Record<string, string> = {
  pending: 'Pendiente',
  accepted: 'Conectado',
  declined: 'Rechazada',
}
const estadoReunion: Record<string, string> = {
  proposed: 'Propuesta',
  accepted: 'Confirmada',
  declined: 'Rechazada',
  canceled: 'Cancelada',
}

onMounted(() => {
  void run(async () => {
    await Promise.all([cargarPersonas(), cargarConexiones(), cargarReuniones(), cargarPreferencias()])
  })
})
</script>

<template>
  <section class="panel net">
    <div class="net__tabs" role="tablist" aria-label="Networking">
      <button
        v-for="[k, label] in subs"
        :key="k"
        type="button"
        role="tab"
        class="net__tab"
        :class="{ 'is-active': sub === k }"
        :aria-selected="sub === k"
        @click="sub = k"
      >
        {{ label }}
      </button>
    </div>

    <p v-if="error" class="error-text" role="alert">{{ error }}</p>

    <div class="optin" :class="{ 'is-on': optIn }">
      <div class="optin__text">
        <strong>{{ optIn ? 'Tu perfil de networking está activo' : 'Activa tu perfil de networking' }}</strong>
        <span class="muted small">{{ optIn ? 'Apareces en el directorio y otros pueden invitarte a conectar o reunirse.' : 'Para aparecer en el directorio y recibir solicitudes de conexión y reuniones.' }}</span>
      </div>
      <button type="button" class="mini" :class="{ 'mini--ghost': optIn }" :disabled="busy" @click="cambiarOptIn(!optIn)">
        {{ optIn ? 'Desactivar' : 'Activar' }}
      </button>
    </div>

    <!-- Personas -->
    <div v-if="sub === 'personas'" class="net__body">
      <input v-model="busqueda" class="control" placeholder="Buscar personas…" aria-label="Buscar personas" @input="run(cargarPersonas)" />
      <p v-if="personas.length === 0" class="muted small">No hay más personas en el evento.</p>
      <ul v-else class="lista">
        <li v-for="p in personas" :key="p.id" class="fila">
          <span class="fila__nombre">{{ p.name }}</span>
          <span class="fila__acc">
            <template v-if="p.connection_status === 'connected'"><span class="chip chip--primary">Conectado</span></template>
            <template v-else-if="p.connection_status === 'pending_out'"><span class="muted small">Solicitud enviada</span></template>
            <template v-else-if="p.connection_status === 'pending_in' && p.connection_id">
              <button type="button" class="mini" :disabled="busy" @click="responderConexion(p.connection_id, true)">Aceptar</button>
              <button type="button" class="mini mini--ghost" :disabled="busy" @click="responderConexion(p.connection_id, false)">Rechazar</button>
            </template>
            <template v-else><AppButton :disabled="busy" @click="conectar(p)">Conectar</AppButton></template>
          </span>
        </li>
      </ul>
    </div>

    <!-- Conexiones -->
    <div v-else-if="sub === 'conexiones'" class="net__body">
      <p v-if="conexiones.length === 0" class="muted small">Aún no tienes conexiones.</p>
      <ul v-else class="lista">
        <li v-for="c in conexiones" :key="c.id" class="fila">
          <span class="fila__nombre">{{ otro(c)?.name }}</span>
          <span class="fila__acc">
            <template v-if="c.status === 'pending' && esEntrante(c)">
              <button type="button" class="mini" :disabled="busy" @click="responderConexion(c.id, true)">Aceptar</button>
              <button type="button" class="mini mini--ghost" :disabled="busy" @click="responderConexion(c.id, false)">Rechazar</button>
            </template>
            <span v-else class="chip" :class="c.status === 'accepted' ? 'chip--primary' : ''">{{ estadoConexion[c.status] }}</span>
          </span>
        </li>
      </ul>
    </div>

    <!-- Reuniones -->
    <div v-else class="net__body">
      <form class="proponer" @submit.prevent="proponer">
        <h3>Proponer reunión 1:1</h3>
        <p v-if="conectados.length === 0" class="muted small">Conecta con alguien para proponerle una reunión.</p>
        <template v-else>
          <label class="field"><span>Con</span>
            <select v-model="paraId" class="control" required>
              <option value="" disabled>Elige a alguien…</option>
              <option v-for="p in conectados" :key="p.id" :value="p.id">{{ p.name }}</option>
            </select>
          </label>
          <label class="field"><span>Cuándo</span><input v-model="cuando" type="datetime-local" class="control" required /></label>
          <label class="field"><span>Duración</span>
            <select v-model.number="duracion" class="control">
              <option :value="15">15 min</option><option :value="30">30 min</option>
              <option :value="45">45 min</option><option :value="60">60 min</option>
            </select>
          </label>
          <label class="field"><span>Tema (opcional)</span><input v-model="tema" class="control" maxlength="300" /></label>
          <AppButton type="submit" :disabled="busy || paraId === '' || cuando === ''">Proponer</AppButton>
        </template>
      </form>

      <h3>Mis reuniones</h3>
      <p v-if="reuniones.length === 0" class="muted small">No tienes reuniones todavía.</p>
      <ul v-else class="lista">
        <li v-for="m in reuniones" :key="m.id" class="reunion">
          <div class="reunion__info">
            <strong>{{ (m.proposer?.id === yo ? m.invitee : m.proposer)?.name }}</strong>
            <span class="muted small">{{ fechaHora(m.scheduled_at) }} · {{ m.duration_minutes }} min<template v-if="m.topic"> · {{ m.topic }}</template></span>
          </div>
          <div class="reunion__acc">
            <span class="chip" :class="m.status === 'accepted' ? 'chip--primary' : ''">{{ estadoReunion[m.status] }}</span>
            <template v-if="m.status === 'proposed' && m.invitee?.id === yo">
              <button type="button" class="mini" :disabled="busy" @click="responderReunion(m.id, true)">Aceptar</button>
              <button type="button" class="mini mini--ghost" :disabled="busy" @click="responderReunion(m.id, false)">Rechazar</button>
            </template>
            <button v-else-if="m.status === 'proposed' || m.status === 'accepted'" type="button" class="mini mini--ghost" :disabled="busy" @click="cancelarReunion(m.id)">Cancelar</button>
          </div>
        </li>
      </ul>
    </div>
  </section>
</template>

<style scoped>
.optin {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--escenia-space-3);
  padding: 12px 14px;
  margin-bottom: var(--escenia-space-3);
  border: 1px solid color-mix(in srgb, var(--escenia-color-primary) 30%, transparent);
  border-radius: var(--escenia-radius-sm);
  background: color-mix(in srgb, var(--escenia-color-primary) 8%, transparent);
}

.optin.is-on {
  border-color: var(--escenia-color-border);
  background: rgba(4, 16, 29, 0.3);
}

.optin__text {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.net__tabs {
  display: flex;
  gap: 6px;
  margin-bottom: var(--escenia-space-3);
}

.net__tab {
  font: inherit;
  font-size: 0.82rem;
  font-weight: 600;
  padding: 6px 12px;
  color: var(--escenia-color-text-muted);
  background: transparent;
  border: 1px solid var(--escenia-color-border);
  border-radius: var(--escenia-radius-pill);
  cursor: pointer;
}

.net__tab.is-active {
  color: var(--escenia-color-text);
  border-color: color-mix(in srgb, var(--escenia-color-primary) 55%, transparent);
}

.net__body {
  display: flex;
  flex-direction: column;
  gap: var(--escenia-space-3);
}

.lista {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.fila,
.reunion {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--escenia-space-3);
  padding: 10px 12px;
  border: 1px solid var(--escenia-color-border);
  border-radius: var(--escenia-radius-sm);
  background: rgba(4, 16, 29, 0.3);
}

.fila__nombre {
  font-weight: 600;
  font-size: 0.9rem;
}

.fila__acc,
.reunion__acc {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.reunion__info {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.mini {
  font: inherit;
  font-size: 0.75rem;
  font-weight: 600;
  padding: 4px 10px;
  color: var(--escenia-color-primary);
  background: transparent;
  border: 1px solid color-mix(in srgb, var(--escenia-color-primary) 40%, transparent);
  border-radius: var(--escenia-radius-pill);
  cursor: pointer;
}

.mini--ghost {
  color: var(--escenia-color-text-muted);
  border-color: var(--escenia-color-border);
}

.proponer {
  display: flex;
  flex-direction: column;
  gap: var(--escenia-space-2);
  padding: var(--escenia-space-3);
  border: 1px solid var(--escenia-color-border);
  border-radius: var(--escenia-radius-sm);
}

.proponer h3,
.net__body > h3 {
  margin: 0;
  font-size: 0.95rem;
}

.field {
  display: flex;
  flex-direction: column;
  gap: 4px;
  font-size: 0.82rem;
}

.small {
  font-size: 0.82rem;
}
</style>
