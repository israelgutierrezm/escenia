<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { AppButton } from '@escenia/ui'
import { ApiError } from '@escenia/api-client'
import type {
  BrandKit,
  BroadcastHealth,
  BroadcastSession,
  BroadcastStatus,
  DestinationProtocol,
  ParticipantRole,
  ParticipantStage,
  RunOfShowItem,
  Scene,
  StreamDestination,
  Studio,
  StudioParticipant,
  StudioStatus,
} from '@escenia/types'

import { api } from '@/lib/api'

const route = useRoute()
const id = route.params.id as string

type Tab = 'sala' | 'escenas' | 'guion' | 'emision' | 'marca'
const tab = ref<Tab>('sala')
const tabs: [Tab, string][] = [
  ['sala', 'Sala'],
  ['escenas', 'Escenas'],
  ['guion', 'Guion'],
  ['emision', 'Emisión'],
  ['marca', 'Marca'],
]

const studio = ref<Studio | null>(null)
const participants = ref<StudioParticipant[]>([])
const scenes = ref<Scene[]>([])
const runOfShow = ref<RunOfShowItem[]>([])
const destinations = ref<StreamDestination[]>([])
const broadcast = ref<BroadcastSession | null>(null)
const brandKits = ref<BrandKit[]>([])
const nuevoKit = ref({ name: '', tokens: '', is_default: false })
const previewSceneId = ref<string | null>(null)
const programSceneId = ref<string | null>(null)

const loading = ref(true)
const error = ref<string | null>(null)
const busy = ref(false)

const nuevoParticipante = ref({ name: '', role: 'guest' as ParticipantRole })
const nuevaEscena = ref('')
const nuevoItem = ref({ title: '', scene_id: '', duration: '' })
const nuevoDestino = ref({
  name: '',
  protocol: 'rtmps' as DestinationProtocol,
  url: '',
  stream_key: '',
})
const destinosSeleccionados = ref<string[]>([])
const grabar = ref(true)

const estadoStudio: Record<StudioStatus, { label: string; clase: string }> = {
  idle: { label: 'Inactivo', clase: '' },
  live: { label: 'En vivo', clase: 'chip--live' },
  ended: { label: 'Finalizado', clase: '' },
}

const rolLabel: Record<ParticipantRole, string> = {
  host: 'Anfitrión',
  producer: 'Productor',
  speaker: 'Ponente',
  guest: 'Invitado',
}
const rolesParticipante = Object.entries(rolLabel) as [ParticipantRole, string][]

const escenarioLabel: Record<ParticipantStage, string> = {
  invited: 'Invitado',
  green_room: 'Sala verde',
  backstage: 'Backstage',
  stage: 'En escena',
  left: 'Salió',
}
const escenarios = Object.entries(escenarioLabel) as [ParticipantStage, string][]

const protocolos: DestinationProtocol[] = ['rtmps', 'rtmp', 'srt']

const estadoEmision: Record<BroadcastStatus, { label: string; clase: string }> = {
  idle: { label: 'Inactiva', clase: '' },
  starting: { label: 'Iniciando', clase: 'chip--primary' },
  live: { label: 'En vivo', clase: 'chip--live' },
  ended: { label: 'Finalizada', clase: '' },
  failed: { label: 'Falló', clase: 'chip--danger' },
}
const saludEmision: Record<BroadcastHealth, { label: string; clase: string }> = {
  unknown: { label: 'Desconocida', clase: '' },
  healthy: { label: 'Saludable', clase: 'chip--live' },
  degraded: { label: 'Degradada', clase: 'chip--primary' },
  failed: { label: 'Falló', clase: 'chip--danger' },
}

const enVivo = computed(() => studio.value?.status === 'live')
const emisionActiva = computed(
  () => broadcast.value !== null && (broadcast.value.status === 'live' || broadcast.value.status === 'starting'),
)
const nombreEscena = (ulid: string | null): string =>
  ulid === null ? '—' : (scenes.value.find((s) => s.id === ulid)?.name ?? '—')

function message(e: unknown): string {
  return e instanceof ApiError ? e.message : 'Algo salió mal.'
}

async function load(): Promise<void> {
  loading.value = true
  error.value = null
  try {
    const [st, sc, ros, dest, bc, bk] = await Promise.all([
      api.studio(id),
      api.scenes(id),
      api.runOfShow(id),
      api.streamDestinations(id),
      api.broadcast(id),
      api.brandKits(id),
    ])
    studio.value = st.data
    scenes.value = sc.data
    runOfShow.value = ros.data
    destinations.value = dest.data
    broadcast.value = bc.data
    brandKits.value = bk.data
    if (st.data.status === 'live') {
      participants.value = (await api.studioParticipants(id)).data
    }
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

function iniciarStudio(): void {
  run(async () => {
    studio.value = (await api.startStudio(id)).data
    participants.value = (await api.studioParticipants(id)).data
  })
}

function finalizarStudio(): void {
  run(async () => {
    studio.value = (await api.endStudio(id)).data
    participants.value = []
    broadcast.value = (await api.broadcast(id)).data
  })
}

function admitir(): void {
  if (nuevoParticipante.value.name.trim() === '') return
  run(async () => {
    await api.admitParticipant(id, {
      name: nuevoParticipante.value.name,
      role: nuevoParticipante.value.role,
    })
    nuevoParticipante.value = { name: '', role: 'guest' }
    studio.value = (await api.studio(id)).data
    participants.value = (await api.studioParticipants(id)).data
  })
}

function moverParticipante(p: StudioParticipant, stage: ParticipantStage): void {
  if (p.stage === stage) return
  run(async () => {
    await api.moveParticipant(p.id, stage)
    participants.value = (await api.studioParticipants(id)).data
  })
}

function crearEscena(): void {
  if (nuevaEscena.value.trim() === '') return
  run(async () => {
    await api.createScene(id, nuevaEscena.value)
    nuevaEscena.value = ''
    scenes.value = (await api.scenes(id)).data
  })
}

function previsualizar(scene: Scene): void {
  run(async () => {
    const res = await api.previewScene(id, scene.id)
    previewSceneId.value = res.data.preview_scene ?? scene.id
    programSceneId.value = res.data.program_scene ?? programSceneId.value
  })
}

function emitir(scene: Scene): void {
  run(async () => {
    const res = await api.takeScene(id, scene.id)
    programSceneId.value = res.data.program_scene ?? scene.id
    previewSceneId.value = res.data.preview_scene ?? previewSceneId.value
  })
}

function agregarItem(): void {
  if (nuevoItem.value.title.trim() === '') return
  run(async () => {
    await api.addRunOfShowItem(id, {
      title: nuevoItem.value.title,
      scene_id: nuevoItem.value.scene_id || undefined,
      duration_seconds: nuevoItem.value.duration ? Number(nuevoItem.value.duration) : undefined,
    })
    nuevoItem.value = { title: '', scene_id: '', duration: '' }
    runOfShow.value = (await api.runOfShow(id)).data
  })
}

function moverItem(index: number, delta: number): void {
  const destino = index + delta
  const lista = runOfShow.value
  if (destino < 0 || destino >= lista.length) return
  const a = lista[index]
  const b = lista[destino]
  if (a === undefined || b === undefined) return
  const orden = lista.map((i) => i.id)
  orden[index] = b.id
  orden[destino] = a.id
  run(async () => {
    runOfShow.value = (await api.reorderRunOfShow(id, orden)).data
  })
}

function crearDestino(): void {
  const d = nuevoDestino.value
  if (d.name.trim() === '' || d.url.trim() === '' || d.stream_key.trim() === '') return
  run(async () => {
    await api.createStreamDestination(id, {
      name: d.name,
      protocol: d.protocol,
      url: d.url,
      stream_key: d.stream_key,
    })
    nuevoDestino.value = { name: '', protocol: 'rtmps', url: '', stream_key: '' }
    destinations.value = (await api.streamDestinations(id)).data
  })
}

function alternarDestino(destId: string): void {
  const i = destinosSeleccionados.value.indexOf(destId)
  if (i === -1) destinosSeleccionados.value.push(destId)
  else destinosSeleccionados.value.splice(i, 1)
}

function iniciarEmision(): void {
  if (destinosSeleccionados.value.length === 0) return
  run(async () => {
    broadcast.value = (await api.startBroadcast(id, destinosSeleccionados.value, grabar.value)).data
  })
}

function detenerEmision(): void {
  if (broadcast.value === null) return
  const bid = broadcast.value.id
  run(async () => {
    broadcast.value = (await api.stopBroadcast(bid)).data
  })
}

function crearKit(): void {
  if (nuevoKit.value.name.trim() === '') return
  let tokens: Record<string, unknown> | undefined
  if (nuevoKit.value.tokens.trim() !== '') {
    try {
      tokens = JSON.parse(nuevoKit.value.tokens) as Record<string, unknown>
    } catch {
      error.value = 'Los tokens de marca no son un JSON válido.'
      return
    }
  }
  run(async () => {
    await api.createBrandKit(id, { name: nuevoKit.value.name, tokens, is_default: nuevoKit.value.is_default })
    nuevoKit.value = { name: '', tokens: '', is_default: false }
    brandKits.value = (await api.brandKits(id)).data
  })
}

function predeterminarKit(kit: BrandKit): void {
  run(async () => {
    await api.setDefaultBrandKit(kit.id)
    brandKits.value = (await api.brandKits(id)).data
  })
}

const duracion = (segs: number | null): string => {
  if (segs === null) return '—'
  const m = Math.floor(segs / 60)
  const s = segs % 60
  return `${m}:${String(s).padStart(2, '0')}`
}

onMounted(load)
</script>

<template>
  <section class="stack">
    <RouterLink :to="{ name: 'event-detail', params: { id } }" class="back muted">‹ Volver al evento</RouterLink>

    <div class="page-head">
      <h1>Studio</h1>
      <p>Sala de producción: participantes, escenas, guion y emisión en vivo.</p>
    </div>

    <p v-if="loading" class="muted">Cargando…</p>
    <p v-else-if="error && !studio" role="alert" class="error-text">{{ error }}</p>

    <template v-else-if="studio">
      <!-- Barra de estado + control del studio -->
      <div class="panel control-bar">
        <div class="control-bar__state">
          <span class="chip" :class="estadoStudio[studio.status].clase">{{ estadoStudio[studio.status].label }}</span>
          <div>
            <strong>{{ studio.name }}</strong>
            <p class="muted small">
              Proveedor: {{ studio.provider }}
              <template v-if="studio.current_session">· Sala: {{ studio.current_session.room }}</template>
            </p>
          </div>
        </div>
        <div class="actions">
          <AppButton v-if="!enVivo" :disabled="busy" @click="iniciarStudio">Iniciar studio</AppButton>
          <AppButton v-else variant="danger" :disabled="busy" @click="finalizarStudio">Finalizar studio</AppButton>
        </div>
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
          <span v-if="key === 'sala' && participants.length" class="count">{{ participants.length }}</span>
        </button>
      </div>

      <!-- SALA -->
      <div v-if="tab === 'sala'" class="panel stack">
        <h2>Participantes</h2>
        <p v-if="!enVivo" class="muted">Inicia el studio para admitir participantes.</p>
        <template v-else>
          <div class="add">
            <input v-model="nuevoParticipante.name" class="control grow" placeholder="Nombre del participante…" @keyup.enter="admitir" />
            <select v-model="nuevoParticipante.role" class="control">
              <option v-for="[r, label] in rolesParticipante" :key="r" :value="r">{{ label }}</option>
            </select>
            <AppButton :disabled="busy || !nuevoParticipante.name.trim()" @click="admitir">Admitir</AppButton>
          </div>

          <p v-if="participants.length === 0" class="empty">Aún no hay participantes.</p>
          <ul v-else class="lista">
            <li v-for="p in participants" :key="p.id">
              <div>
                <strong>{{ p.name }}</strong>
                <span class="chip role-badge">{{ rolLabel[p.role] }}</span>
              </div>
              <select
                class="control stage-select"
                :value="p.stage"
                :disabled="busy"
                @change="moverParticipante(p, ($event.target as HTMLSelectElement).value as ParticipantStage)"
              >
                <option v-for="[s, label] in escenarios" :key="s" :value="s">{{ label }}</option>
              </select>
            </li>
          </ul>
        </template>
      </div>

      <!-- ESCENAS -->
      <div v-else-if="tab === 'escenas'" class="stack">
        <div class="bus">
          <div class="bus__slot bus__slot--preview">
            <span class="bus__tag">Previsualización</span>
            <strong>{{ nombreEscena(previewSceneId) }}</strong>
          </div>
          <div class="bus__slot bus__slot--program">
            <span class="bus__tag">Al aire</span>
            <strong>{{ nombreEscena(programSceneId) }}</strong>
          </div>
        </div>

        <div class="panel stack">
          <h2>Escenas</h2>
          <div class="add">
            <input v-model="nuevaEscena" class="control grow" placeholder="Nueva escena…" @keyup.enter="crearEscena" />
            <AppButton :disabled="busy || !nuevaEscena.trim()" @click="crearEscena">Crear escena</AppButton>
          </div>

          <p v-if="scenes.length === 0" class="empty">Aún no hay escenas.</p>
          <ul v-else class="lista">
            <li v-for="s in scenes" :key="s.id">
              <div>
                <strong>{{ s.name }}</strong>
                <span v-if="s.id === programSceneId" class="chip chip--live">Al aire</span>
                <span v-else-if="s.id === previewSceneId" class="chip chip--primary">En preview</span>
              </div>
              <div class="actions">
                <AppButton variant="ghost" :disabled="busy" @click="previsualizar(s)">Previsualizar</AppButton>
                <AppButton :disabled="busy" @click="emitir(s)">Emitir</AppButton>
              </div>
            </li>
          </ul>
        </div>
      </div>

      <!-- GUION -->
      <div v-else-if="tab === 'guion'" class="panel stack">
        <h2>Guion del programa</h2>
        <div class="crear">
          <label class="field"><span>Título</span><input v-model="nuevoItem.title" class="control" placeholder="Apertura" /></label>
          <label class="field">
            <span>Escena (opcional)</span>
            <select v-model="nuevoItem.scene_id" class="control">
              <option value="">Ninguna</option>
              <option v-for="s in scenes" :key="s.id" :value="s.id">{{ s.name }}</option>
            </select>
          </label>
          <label class="field"><span>Duración (seg.)</span><input v-model="nuevoItem.duration" type="number" min="0" class="control" placeholder="300" /></label>
          <AppButton :disabled="busy || !nuevoItem.title.trim()" @click="agregarItem">Añadir</AppButton>
        </div>

        <p v-if="runOfShow.length === 0" class="empty">El guion está vacío.</p>
        <ol v-else class="guion">
          <li v-for="(item, i) in runOfShow" :key="item.id">
            <span class="guion__pos">{{ i + 1 }}</span>
            <div class="guion__body">
              <strong>{{ item.title }}</strong>
              <p v-if="item.notes" class="muted small">{{ item.notes }}</p>
              <p class="muted small">
                <template v-if="item.scene">Escena: {{ nombreEscena(item.scene) }} · </template>
                Duración: {{ duracion(item.duration_seconds) }}
              </p>
            </div>
            <div class="guion__move">
              <button type="button" class="mini" :disabled="busy || i === 0" aria-label="Subir en el guion" title="Subir" @click="moverItem(i, -1)">↑</button>
              <button type="button" class="mini" :disabled="busy || i === runOfShow.length - 1" aria-label="Bajar en el guion" title="Bajar" @click="moverItem(i, 1)">↓</button>
            </div>
          </li>
        </ol>
      </div>

      <!-- MARCA -->
      <div v-else-if="tab === 'marca'" class="stack">
        <div class="panel stack">
          <h2>Kits de marca</h2>
          <p class="muted small">Define paletas y estilos reutilizables para las escenas del evento.</p>
          <div class="fila">
            <label class="field grow"><span>Nombre</span><input v-model="nuevoKit.name" class="control" placeholder="Marca principal" /></label>
          </div>
          <label class="field">
            <span>Tokens (JSON, opcional)</span>
            <textarea v-model="nuevoKit.tokens" class="control" rows="3" placeholder='{ "primary": "#2ea6ff", "logo": "https://…" }'></textarea>
          </label>
          <label class="dest-check">
            <input v-model="nuevoKit.is_default" type="checkbox" />
            <span>Usar como predeterminado</span>
          </label>
          <div class="actions">
            <AppButton :disabled="busy || !nuevoKit.name.trim()" @click="crearKit">Crear kit</AppButton>
          </div>
        </div>

        <p v-if="brandKits.length === 0" class="panel empty">Aún no hay kits de marca.</p>
        <ul v-else class="lista">
          <li v-for="k in brandKits" :key="k.id">
            <div>
              <strong>{{ k.name }}</strong>
              <span v-if="k.is_default" class="chip chip--live">Predeterminado</span>
            </div>
            <AppButton v-if="!k.is_default" variant="ghost" :disabled="busy" @click="predeterminarKit(k)">Predeterminar</AppButton>
          </li>
        </ul>
      </div>

      <!-- EMISIÓN -->
      <div v-else class="stack">
        <div class="panel stack">
          <h2>Emisión</h2>
          <template v-if="emisionActiva && broadcast">
            <div class="emision-live">
              <span class="chip" :class="estadoEmision[broadcast.status].clase">{{ estadoEmision[broadcast.status].label }}</span>
              <span class="chip" :class="saludEmision[broadcast.health].clase">Señal: {{ saludEmision[broadcast.health].label }}</span>
              <span v-if="broadcast.record" class="chip chip--danger">Grabando</span>
            </div>
            <p class="muted small">Destinos activos: {{ broadcast.destinations?.length ?? 0 }}</p>
            <div class="actions">
              <AppButton variant="danger" :disabled="busy" @click="detenerEmision">Detener emisión</AppButton>
            </div>
          </template>

          <template v-else>
            <p v-if="!enVivo" class="muted">Inicia el studio antes de emitir.</p>
            <template v-else>
              <p v-if="destinations.length === 0" class="muted">Añade un destino de streaming para poder emitir.</p>
              <template v-else>
                <p class="muted">Selecciona a dónde emitir:</p>
                <label v-for="d in destinations" :key="d.id" class="dest-check">
                  <input type="checkbox" :checked="destinosSeleccionados.includes(d.id)" @change="alternarDestino(d.id)" />
                  <span><strong>{{ d.name }}</strong> · <span class="muted">{{ d.protocol.toUpperCase() }}</span></span>
                </label>
                <label class="dest-check">
                  <input v-model="grabar" type="checkbox" />
                  <span>Grabar la emisión</span>
                </label>
                <div class="actions">
                  <AppButton :disabled="busy || destinosSeleccionados.length === 0" @click="iniciarEmision">Iniciar emisión</AppButton>
                </div>
              </template>
            </template>
          </template>
        </div>

        <div class="panel stack">
          <h2>Destinos de streaming</h2>
          <div class="crear">
            <label class="field"><span>Nombre</span><input v-model="nuevoDestino.name" class="control" placeholder="YouTube principal" /></label>
            <label class="field">
              <span>Protocolo</span>
              <select v-model="nuevoDestino.protocol" class="control">
                <option v-for="p in protocolos" :key="p" :value="p">{{ p.toUpperCase() }}</option>
              </select>
            </label>
            <label class="field"><span>URL</span><input v-model="nuevoDestino.url" class="control" placeholder="rtmps://…" /></label>
            <label class="field"><span>Clave de stream</span><input v-model="nuevoDestino.stream_key" type="password" class="control" placeholder="••••••" /></label>
            <AppButton :disabled="busy || !nuevoDestino.name.trim() || !nuevoDestino.url.trim() || !nuevoDestino.stream_key.trim()" @click="crearDestino">Añadir destino</AppButton>
          </div>

          <p v-if="destinations.length === 0" class="empty">Aún no hay destinos.</p>
          <ul v-else class="lista">
            <li v-for="d in destinations" :key="d.id">
              <div><strong>{{ d.name }}</strong> <span class="muted">{{ d.url }}</span></div>
              <span class="chip">{{ d.protocol.toUpperCase() }}</span>
            </li>
          </ul>
        </div>
      </div>

      <p v-if="error" role="alert" class="error-text">{{ error }}</p>
    </template>
  </section>
</template>

<style scoped>
.back { text-decoration: none; font-size: 0.85rem; }
.back:hover { color: var(--escenia-color-text); }
.small { font-size: 0.8rem; margin: 4px 0 0; }
.grow { flex: 1; }

.control-bar { display: flex; align-items: center; justify-content: space-between; gap: var(--escenia-space-4); flex-wrap: wrap; }
.control-bar__state { display: flex; align-items: center; gap: var(--escenia-space-3); }

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
.tab .count { background: rgba(255, 255, 255, 0.25); border-radius: 999px; padding: 0 7px; font-size: 0.7rem; }

.add { display: flex; gap: var(--escenia-space-2); flex-wrap: wrap; }
.fila { display: flex; gap: var(--escenia-space-3); flex-wrap: wrap; align-items: end; }
.stage-select { max-width: 170px; }
textarea.control { resize: vertical; }

.lista { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: var(--escenia-space-2); }
.lista li { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 10px 12px; border: 1px solid var(--escenia-color-border); border-radius: var(--escenia-radius-sm); background: rgba(4, 16, 29, 0.35); }

.bus { display: grid; grid-template-columns: 1fr 1fr; gap: var(--escenia-space-4); }
.bus__slot { padding: var(--escenia-space-4); border-radius: var(--escenia-radius-md); border: 1px solid var(--escenia-color-border); background: rgba(4, 16, 29, 0.4); display: flex; flex-direction: column; gap: 6px; }
.bus__slot strong { font-size: 1.1rem; }
.bus__slot--preview { border-color: color-mix(in srgb, var(--escenia-color-primary) 45%, transparent); }
.bus__slot--program { border-color: color-mix(in srgb, var(--escenia-color-accent) 55%, transparent); }
.bus__tag { font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.06em; color: var(--escenia-color-text-muted); }

.crear { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: var(--escenia-space-3); align-items: end; }

.guion { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: var(--escenia-space-2); }
.guion li { display: flex; align-items: center; gap: var(--escenia-space-3); padding: 12px; border: 1px solid var(--escenia-color-border); border-radius: var(--escenia-radius-sm); background: rgba(4, 16, 29, 0.35); }
.guion__pos { display: grid; place-items: center; width: 28px; height: 28px; flex-shrink: 0; border-radius: 50%; background: color-mix(in srgb, var(--escenia-color-primary) 18%, transparent); color: var(--escenia-color-primary); font-weight: 700; font-size: 0.85rem; }
.guion__body { flex: 1; }
.guion__move { display: flex; flex-direction: column; gap: 4px; }
.mini { width: 26px; height: 22px; font: inherit; line-height: 1; color: var(--escenia-color-text-muted); background: transparent; border: 1px solid var(--escenia-color-border); border-radius: 6px; cursor: pointer; }
.mini:hover:not(:disabled) { color: var(--escenia-color-text); border-color: var(--escenia-color-border-strong); }
.mini:disabled { opacity: 0.4; cursor: not-allowed; }

.emision-live { display: flex; gap: var(--escenia-space-2); flex-wrap: wrap; }
.dest-check { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border: 1px solid var(--escenia-color-border); border-radius: var(--escenia-radius-sm); background: rgba(4, 16, 29, 0.3); cursor: pointer; }

@media (max-width: 640px) {
  .bus { grid-template-columns: 1fr; }
  .crear { grid-template-columns: 1fr; }
}
</style>
