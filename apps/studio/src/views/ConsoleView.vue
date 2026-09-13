<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { AppButton } from '@escenia/ui'
import { ApiError } from '@escenia/api-client'
import type {
  BroadcastSession,
  ParticipantStage,
  Scene,
  StreamDestination,
  Studio,
  StudioParticipant,
} from '@escenia/types'

import { api } from '@/lib/api'

const route = useRoute()
const router = useRouter()
const id = route.params.eventId as string

const studio = ref<Studio | null>(null)
const participants = ref<StudioParticipant[]>([])
const scenes = ref<Scene[]>([])
const destinations = ref<StreamDestination[]>([])
const broadcast = ref<BroadcastSession | null>(null)
const previewSceneId = ref<string | null>(null)
const programSceneId = ref<string | null>(null)
const destinoSel = ref<string[]>([])
const grabar = ref(true)

const loading = ref(true)
const error = ref<string | null>(null)
const busy = ref(false)

const enVivo = computed(() => studio.value?.status === 'live')
const emitiendo = computed(
  () => broadcast.value !== null && (broadcast.value.status === 'live' || broadcast.value.status === 'starting'),
)
const nombreEscena = (ulid: string | null): string =>
  ulid === null ? 'Sin escena' : (scenes.value.find((s) => s.id === ulid)?.name ?? 'Sin escena')

const escenarioLabel: Record<ParticipantStage, string> = {
  invited: 'Invitado',
  green_room: 'Sala verde',
  backstage: 'Backstage',
  stage: 'En escena',
  left: 'Salió',
}
const escenarios = Object.entries(escenarioLabel) as [ParticipantStage, string][]
const enEscena = computed(() => participants.value.filter((p) => p.stage === 'stage'))

function iniciales(nombre: string): string {
  return nombre
    .split(' ')
    .map((p) => p.charAt(0))
    .slice(0, 2)
    .join('')
    .toUpperCase()
}

function message(e: unknown): string {
  return e instanceof ApiError ? e.message : 'Algo salió mal.'
}

async function load(): Promise<void> {
  loading.value = true
  error.value = null
  try {
    const [st, sc, dest, bc] = await Promise.all([
      api.studio(id),
      api.scenes(id),
      api.streamDestinations(id),
      api.broadcast(id),
    ])
    studio.value = st.data
    scenes.value = sc.data
    destinations.value = dest.data
    broadcast.value = bc.data
    if (st.data.status === 'live') participants.value = (await api.studioParticipants(id)).data
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

function iniciar(): void {
  run(async () => {
    studio.value = (await api.startStudio(id)).data
    participants.value = (await api.studioParticipants(id)).data
  })
}

function finalizar(): void {
  run(async () => {
    studio.value = (await api.endStudio(id)).data
    participants.value = []
    broadcast.value = (await api.broadcast(id)).data
  })
}

function previsualizar(scene: Scene): void {
  run(async () => {
    const res = await api.previewScene(id, scene.id)
    previewSceneId.value = res.data.preview_scene ?? scene.id
    programSceneId.value = res.data.program_scene ?? programSceneId.value
  })
}

function tomar(): void {
  const sceneId = previewSceneId.value
  run(async () => {
    const res = await api.takeScene(id, sceneId ?? undefined)
    programSceneId.value = res.data.program_scene ?? sceneId
    previewSceneId.value = res.data.preview_scene ?? previewSceneId.value
  })
}

function mover(p: StudioParticipant, stage: ParticipantStage): void {
  if (p.stage === stage) return
  run(async () => {
    await api.moveParticipant(p.id, stage)
    participants.value = (await api.studioParticipants(id)).data
  })
}

function alSuelo(p: StudioParticipant): void {
  mover(p, p.stage === 'stage' ? 'backstage' : 'stage')
}

function iniciarEmision(): void {
  if (destinoSel.value.length === 0) return
  run(async () => {
    broadcast.value = (await api.startBroadcast(id, destinoSel.value, grabar.value)).data
  })
}

function detenerEmision(): void {
  if (broadcast.value === null) return
  const bid = broadcast.value.id
  run(async () => {
    broadcast.value = (await api.stopBroadcast(bid)).data
  })
}

function alternarDestino(d: string): void {
  const i = destinoSel.value.indexOf(d)
  if (i === -1) destinoSel.value.push(d)
  else destinoSel.value.splice(i, 1)
}

function salir(): void {
  router.push({ name: 'events' })
}

onMounted(load)
</script>

<template>
  <div class="consola">
    <header class="bar">
      <div class="bar__left">
        <button type="button" class="volver" aria-label="Volver a eventos" @click="salir">‹</button>
        <strong>{{ studio?.name ?? 'Studio' }}</strong>
        <span class="chip" :class="enVivo ? 'chip--live' : ''">{{ enVivo ? 'En vivo' : 'Inactivo' }}</span>
        <span v-if="emitiendo" class="chip chip--danger">● Emitiendo</span>
      </div>
      <div class="bar__right">
        <AppButton v-if="!enVivo" :disabled="busy" @click="iniciar">Iniciar studio</AppButton>
        <AppButton v-else variant="danger" :disabled="busy" @click="finalizar">Finalizar</AppButton>
      </div>
    </header>

    <p v-if="loading" class="cargando muted">Cargando consola…</p>
    <p v-else-if="error && !studio" class="cargando error-text" role="alert">{{ error }}</p>

    <div v-else class="grid">
      <!-- Monitores -->
      <section class="monitores">
        <div class="monitor monitor--program">
          <span class="monitor__tag">Al aire</span>
          <div class="monitor__video"><span>{{ nombreEscena(programSceneId) }}</span></div>
        </div>
        <div class="monitor monitor--preview">
          <span class="monitor__tag">Previsualización</span>
          <div class="monitor__video"><span>{{ nombreEscena(previewSceneId) }}</span></div>
          <AppButton class="tomar" :disabled="busy || previewSceneId === null" @click="tomar">Tomar al aire ▸</AppButton>
        </div>

        <!-- Escenas -->
        <div class="panel escenas">
          <h2>Escenas</h2>
          <p v-if="scenes.length === 0" class="empty">No hay escenas. Créalas desde el panel de administración.</p>
          <div v-else class="escenas__grid">
            <button
              v-for="s in scenes"
              :key="s.id"
              type="button"
              class="escena"
              :class="{ 'is-program': s.id === programSceneId, 'is-preview': s.id === previewSceneId }"
              :disabled="busy"
              @click="previsualizar(s)"
            >
              {{ s.name }}
              <span v-if="s.id === programSceneId" class="mini chip chip--live">Al aire</span>
              <span v-else-if="s.id === previewSceneId" class="mini chip chip--primary">Preview</span>
            </button>
          </div>
        </div>
      </section>

      <!-- Lateral: participantes + emisión -->
      <aside class="lateral">
        <div class="panel">
          <h2>Participantes <span class="muted">({{ enEscena.length }} en escena)</span></h2>
          <p v-if="!enVivo" class="muted small">Inicia el studio para ver a los participantes.</p>
          <p v-else-if="participants.length === 0" class="empty">Aún no hay participantes.</p>
          <div v-else class="tiles">
            <div v-for="p in participants" :key="p.id" class="tile" :class="{ 'on-air': p.stage === 'stage' }">
              <div class="tile__video"><span class="tile__ini">{{ iniciales(p.name) }}</span></div>
              <div class="tile__row">
                <strong class="tile__name">{{ p.name }}</strong>
                <button
                  type="button"
                  class="tile__act"
                  :disabled="busy"
                  @click="alSuelo(p)"
                >
                  {{ p.stage === 'stage' ? 'Bajar' : 'Al aire' }}
                </button>
              </div>
              <select
                class="control tile__stage"
                :value="p.stage"
                :disabled="busy"
                @change="mover(p, ($event.target as HTMLSelectElement).value as ParticipantStage)"
              >
                <option v-for="[s, label] in escenarios" :key="s" :value="s">{{ label }}</option>
              </select>
            </div>
          </div>
        </div>

        <div class="panel">
          <h2>Emisión</h2>
          <template v-if="emitiendo && broadcast">
            <div class="actions">
              <span class="chip chip--live">En vivo</span>
              <span v-if="broadcast.record" class="chip chip--danger">Grabando</span>
            </div>
            <div class="actions">
              <AppButton variant="danger" :disabled="busy" @click="detenerEmision">Detener emisión</AppButton>
            </div>
          </template>
          <template v-else>
            <p v-if="!enVivo" class="muted small">Inicia el studio antes de emitir.</p>
            <p v-else-if="destinations.length === 0" class="muted small">Añade un destino de streaming desde administración.</p>
            <template v-else>
              <label v-for="d in destinations" :key="d.id" class="dest">
                <input type="checkbox" :checked="destinoSel.includes(d.id)" @change="alternarDestino(d.id)" />
                <span>{{ d.name }} · {{ d.protocol.toUpperCase() }}</span>
              </label>
              <label class="dest"><input v-model="grabar" type="checkbox" /><span>Grabar</span></label>
              <div class="actions">
                <AppButton :disabled="busy || destinoSel.length === 0" @click="iniciarEmision">Iniciar emisión</AppButton>
              </div>
            </template>
          </template>
        </div>

        <p class="muted xsmall">
          Los tiles muestran a los participantes; el vídeo en directo se conecta con el servidor de medios
          (LiveKit) en producción.
        </p>
      </aside>
    </div>

    <p v-if="error && studio" class="error-flotante error-text" role="alert">{{ error }}</p>
  </div>
</template>

<style scoped>
.consola {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}

.bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--escenia-space-3);
  padding: var(--escenia-space-3) var(--escenia-space-4);
  border-bottom: 1px solid var(--escenia-color-border);
  background: rgba(6, 18, 31, 0.72);
  backdrop-filter: blur(14px);
}

.bar__left {
  display: flex;
  align-items: center;
  gap: var(--escenia-space-2);
  min-width: 0;
}

.volver {
  font: inherit;
  font-size: 1.3rem;
  line-height: 1;
  width: 30px;
  height: 30px;
  color: var(--escenia-color-text-muted);
  background: transparent;
  border: 1px solid var(--escenia-color-border);
  border-radius: var(--escenia-radius-sm);
  cursor: pointer;
}

.cargando {
  padding: var(--escenia-space-8);
  text-align: center;
}

.grid {
  flex: 1;
  display: grid;
  grid-template-columns: 1fr 320px;
  gap: var(--escenia-space-4);
  padding: var(--escenia-space-4);
  align-items: start;
}

.monitores {
  display: flex;
  flex-direction: column;
  gap: var(--escenia-space-4);
}

.monitor {
  position: relative;
}

.monitor__tag {
  position: absolute;
  top: var(--escenia-space-2);
  left: var(--escenia-space-2);
  z-index: 2;
  padding: 3px 10px;
  font-size: 0.68rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  border-radius: var(--escenia-radius-pill);
  background: rgba(4, 16, 29, 0.75);
}

.monitor--program .monitor__tag {
  color: var(--escenia-color-accent);
}

.monitor__video {
  aspect-ratio: 16 / 9;
  display: grid;
  place-items: center;
  border-radius: var(--escenia-radius-md);
  background: radial-gradient(120% 120% at 50% 0%, rgba(46, 166, 255, 0.12), rgba(4, 16, 29, 0.7));
  border: 1px solid var(--escenia-color-border-strong);
  font-size: 1.2rem;
  font-weight: 600;
  color: var(--escenia-color-text);
}

.monitor--program .monitor__video {
  border-color: color-mix(in srgb, var(--escenia-color-accent) 45%, transparent);
}

.monitor--preview {
  max-width: 420px;
}

.monitor--preview .monitor__video {
  font-size: 1rem;
  color: var(--escenia-color-text-muted);
}

.tomar {
  margin-top: var(--escenia-space-2);
}

.escenas__grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
  gap: var(--escenia-space-2);
}

.escena {
  display: flex;
  flex-direction: column;
  gap: 6px;
  align-items: flex-start;
  padding: var(--escenia-space-3);
  font: inherit;
  font-weight: 600;
  font-size: 0.85rem;
  text-align: left;
  color: var(--escenia-color-text);
  background: rgba(4, 16, 29, 0.4);
  border: 1px solid var(--escenia-color-border);
  border-radius: var(--escenia-radius-sm);
  cursor: pointer;
  transition: all 0.14s ease;
}

.escena:hover:not(:disabled) {
  border-color: var(--escenia-color-border-strong);
}

.escena.is-preview {
  border-color: color-mix(in srgb, var(--escenia-color-primary) 55%, transparent);
}

.escena.is-program {
  border-color: color-mix(in srgb, var(--escenia-color-accent) 55%, transparent);
}

.mini {
  font-size: 0.65rem;
}

.lateral {
  display: flex;
  flex-direction: column;
  gap: var(--escenia-space-4);
}

.small {
  font-size: 0.8rem;
}

.xsmall {
  font-size: 0.75rem;
  margin: 0;
}

.tiles {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--escenia-space-2);
}

.tile {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 8px;
  border: 1px solid var(--escenia-color-border);
  border-radius: var(--escenia-radius-sm);
  background: rgba(4, 16, 29, 0.4);
}

.tile.on-air {
  border-color: color-mix(in srgb, var(--escenia-color-accent) 55%, transparent);
}

.tile__video {
  aspect-ratio: 16 / 9;
  display: grid;
  place-items: center;
  border-radius: 6px;
  background: #04101d;
}

.tile__ini {
  width: 34px;
  height: 34px;
  display: grid;
  place-items: center;
  border-radius: 50%;
  font-size: 0.8rem;
  font-weight: 700;
  color: var(--escenia-color-primary);
  background: color-mix(in srgb, var(--escenia-color-primary) 18%, transparent);
}

.tile__row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 6px;
}

.tile__name {
  font-size: 0.8rem;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.tile__act {
  flex-shrink: 0;
  font: inherit;
  font-size: 0.72rem;
  font-weight: 600;
  padding: 3px 8px;
  color: var(--escenia-color-primary);
  background: transparent;
  border: 1px solid color-mix(in srgb, var(--escenia-color-primary) 40%, transparent);
  border-radius: 6px;
  cursor: pointer;
}

.tile__stage {
  font-size: 0.78rem;
  padding: 5px 8px;
}

.dest {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 10px;
  font-size: 0.85rem;
  border: 1px solid var(--escenia-color-border);
  border-radius: var(--escenia-radius-sm);
  background: rgba(4, 16, 29, 0.3);
  cursor: pointer;
  margin-bottom: var(--escenia-space-2);
}

.error-flotante {
  position: fixed;
  bottom: var(--escenia-space-4);
  left: 50%;
  transform: translateX(-50%);
  padding: 10px 16px;
  border-radius: var(--escenia-radius-sm);
  background: color-mix(in srgb, var(--escenia-color-danger) 14%, var(--escenia-color-surface-solid));
  border: 1px solid color-mix(in srgb, var(--escenia-color-danger) 40%, transparent);
}

@media (max-width: 860px) {
  .grid {
    grid-template-columns: 1fr;
  }
}
</style>
