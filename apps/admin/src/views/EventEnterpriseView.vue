<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { AppButton } from '@escenia/ui'
import { ApiError } from '@escenia/api-client'
import type {
  AgendaSession,
  Booth,
  BoothLead,
  LeaderboardEntry,
  Sponsor,
  SponsorTier,
  Track,
} from '@escenia/types'

import { fecha } from '@/lib/eventLabels'
import { api } from '@/lib/api'

const route = useRoute()
const id = route.params.id as string

type Tab = 'agenda' | 'patrocinadores' | 'expo' | 'ranking'
const tab = ref<Tab>('agenda')
const tabs: [Tab, string][] = [
  ['agenda', 'Agenda'],
  ['patrocinadores', 'Patrocinadores'],
  ['expo', 'Expo'],
  ['ranking', 'Ranking'],
]

const loading = ref(true)
const error = ref<string | null>(null)
const busy = ref(false)

// Agenda
const tracks = ref<Track[]>([])
const sessions = ref<AgendaSession[]>([])
const nuevaPista = ref({ name: '', color: '#2ea6ff' })
const edits = ref<Record<string, { track: string; room: string; capacity: string }>>({})
const agenda = ref<Record<string, AgendaSession>>({})

// Patrocinadores
const sponsors = ref<Sponsor[]>([])
const nuevoSponsor = ref({ name: '', tier: 'gold' as SponsorTier, logo_url: '', website_url: '' })
const tiers: [SponsorTier, string][] = [
  ['platinum', 'Platino'],
  ['gold', 'Oro'],
  ['silver', 'Plata'],
  ['bronze', 'Bronce'],
  ['community', 'Comunidad'],
]
const tierLabel: Record<SponsorTier, string> = {
  platinum: 'Platino',
  gold: 'Oro',
  silver: 'Plata',
  bronze: 'Bronce',
  community: 'Comunidad',
}

// Expo
const booths = ref<Booth[]>([])
const leads = ref<BoothLead[]>([])
const nuevoBooth = ref({ sponsor: '', name: '', description: '', url: '' })

// Ranking
const leaderboard = ref<LeaderboardEntry[]>([])

function message(e: unknown): string {
  return e instanceof ApiError ? e.message : 'Algo salió mal.'
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
  loading.value = true
  error.value = null
  try {
    const [t, se, sp, b, l, lb] = await Promise.all([
      api.tracks(id),
      api.agendaSessions(id),
      api.sponsors(id),
      api.booths(id),
      api.leads(id),
      api.leaderboard(id),
    ])
    tracks.value = t.data
    sessions.value = se.data
    sponsors.value = sp.data
    booths.value = b.data
    leads.value = l.data
    leaderboard.value = lb.data
    for (const s of se.data) {
      edits.value[s.id] = {
        track: s.track ?? '',
        room: s.room ?? '',
        capacity: s.capacity !== null ? String(s.capacity) : '',
      }
      agenda.value[s.id] = s
    }
  } catch (e) {
    error.value = message(e)
  } finally {
    loading.value = false
  }
}

// Agenda
function crearPista(): void {
  if (nuevaPista.value.name.trim() === '') return
  run(async () => {
    await api.createTrack(id, { name: nuevaPista.value.name, color: nuevaPista.value.color })
    nuevaPista.value = { name: '', color: '#2ea6ff' }
    tracks.value = (await api.tracks(id)).data
  })
}
function guardarAgenda(s: AgendaSession): void {
  const e = edits.value[s.id]
  if (e === undefined) return
  run(async () => {
    const res = (await api.setSessionAgenda(id, s.id, {
      track: e.track || undefined,
      room: e.room || undefined,
      capacity: e.capacity ? Number(e.capacity) : undefined,
    })).data
    agenda.value[s.id] = res
  })
}
function nombrePista(ulid: string | undefined): string {
  if (ulid === undefined) return '—'
  return tracks.value.find((t) => t.id === ulid)?.name ?? '—'
}

// Patrocinadores
function crearSponsor(): void {
  const s = nuevoSponsor.value
  if (s.name.trim() === '') return
  run(async () => {
    await api.createSponsor(id, {
      name: s.name,
      tier: s.tier,
      logo_url: s.logo_url.trim() || undefined,
      website_url: s.website_url.trim() || undefined,
    })
    nuevoSponsor.value = { name: '', tier: 'gold', logo_url: '', website_url: '' }
    sponsors.value = (await api.sponsors(id)).data
  })
}

// Expo
function crearBooth(): void {
  const b = nuevoBooth.value
  if (b.sponsor === '' || b.name.trim() === '') return
  run(async () => {
    await api.createBooth(id, {
      sponsor: b.sponsor,
      name: b.name,
      description: b.description.trim() || undefined,
      url: b.url.trim() || undefined,
    })
    nuevoBooth.value = { sponsor: '', name: '', description: '', url: '' }
    booths.value = (await api.booths(id)).data
  })
}

onMounted(load)
</script>

<template>
  <section class="stack">
    <RouterLink :to="{ name: 'event-detail', params: { id } }" class="back muted">‹ Volver al evento</RouterLink>

    <div class="page-head">
      <h1>Agenda y expo</h1>
      <p>Agenda multi-track, patrocinadores, expo virtual y ranking de participación.</p>
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
      </button>
    </div>

    <p v-if="loading" class="muted">Cargando…</p>

    <template v-else>
      <!-- AGENDA -->
      <div v-if="tab === 'agenda'" class="stack">
        <div class="panel stack">
          <h2>Pistas (tracks)</h2>
          <div class="add">
            <input v-model="nuevaPista.name" class="control grow" placeholder="Nombre de la pista" @keyup.enter="crearPista" />
            <input v-model="nuevaPista.color" type="color" class="color" title="Color de la pista" />
            <AppButton :disabled="busy || !nuevaPista.name.trim()" @click="crearPista">Añadir pista</AppButton>
          </div>
          <div v-if="tracks.length" class="tracks-row">
            <span v-for="t in tracks" :key="t.id" class="track-chip">
              <span class="dot" :style="{ background: t.color ?? 'var(--escenia-color-border-strong)' }"></span>{{ t.name }}
            </span>
          </div>
          <p v-else class="muted small">Aún no hay pistas.</p>
        </div>

        <div class="panel stack">
          <h2>Sesiones</h2>
          <p v-if="sessions.length === 0" class="empty">Este evento no tiene sesiones. Créalas desde el detalle del evento.</p>
          <div v-for="s in sessions" :key="s.id" class="sesion">
            <div class="sesion__title">
              <strong>{{ s.title }}</strong>
              <span v-if="agenda[s.id]" class="chip" :class="agenda[s.id]?.has_capacity ? 'chip--live' : 'chip--danger'">
                {{ agenda[s.id]?.registered_count }}<template v-if="agenda[s.id]?.capacity">/{{ agenda[s.id]?.capacity }}</template> inscritos
                · {{ nombrePista(agenda[s.id]?.track) }}
              </span>
            </div>
            <div v-if="edits[s.id]" class="sesion__edit">
              <select v-model="edits[s.id]!.track" class="control">
                <option value="">Sin pista</option>
                <option v-for="t in tracks" :key="t.id" :value="t.id">{{ t.name }}</option>
              </select>
              <input v-model="edits[s.id]!.room" class="control" placeholder="Sala / sala virtual" />
              <input v-model="edits[s.id]!.capacity" type="number" min="1" class="control cap" placeholder="Cupo" />
              <AppButton variant="ghost" :disabled="busy" @click="guardarAgenda(s)">Guardar</AppButton>
            </div>
          </div>
        </div>
      </div>

      <!-- PATROCINADORES -->
      <div v-else-if="tab === 'patrocinadores'" class="stack">
        <div class="panel stack">
          <h2>Nuevo patrocinador</h2>
          <div class="fila">
            <label class="field grow"><span>Nombre</span><input v-model="nuevoSponsor.name" class="control" placeholder="Acme Inc." /></label>
            <label class="field">
              <span>Nivel</span>
              <select v-model="nuevoSponsor.tier" class="control">
                <option v-for="[t, label] in tiers" :key="t" :value="t">{{ label }}</option>
              </select>
            </label>
          </div>
          <div class="fila">
            <label class="field grow"><span>Logo (URL, opcional)</span><input v-model="nuevoSponsor.logo_url" class="control" placeholder="https://…/logo.png" /></label>
            <label class="field grow"><span>Sitio web (opcional)</span><input v-model="nuevoSponsor.website_url" class="control" placeholder="https://acme.com" /></label>
          </div>
          <div class="actions">
            <AppButton :disabled="busy || !nuevoSponsor.name.trim()" @click="crearSponsor">Añadir patrocinador</AppButton>
          </div>
        </div>

        <p v-if="sponsors.length === 0" class="panel empty">Aún no hay patrocinadores.</p>
        <div v-else class="grid-cards">
          <div v-for="sp in sponsors" :key="sp.id" class="panel stack sponsor">
            <div class="sponsor__head">
              <img v-if="sp.logo_url" :src="sp.logo_url" :alt="sp.name" class="sponsor__logo" />
              <strong>{{ sp.name }}</strong>
            </div>
            <div class="actions" style="gap: 8px">
              <span class="chip" :class="`tier tier--${sp.tier}`">{{ tierLabel[sp.tier] }}</span>
              <a v-if="sp.website_url" :href="sp.website_url" target="_blank" rel="noopener" class="muted small">Sitio web ↗</a>
            </div>
          </div>
        </div>
      </div>

      <!-- EXPO -->
      <div v-else-if="tab === 'expo'" class="stack">
        <div class="panel stack">
          <h2>Nuevo stand</h2>
          <p v-if="sponsors.length === 0" class="muted">Crea un patrocinador antes de añadir un stand.</p>
          <template v-else>
            <div class="fila">
              <label class="field">
                <span>Patrocinador</span>
                <select v-model="nuevoBooth.sponsor" class="control">
                  <option value="">Selecciona…</option>
                  <option v-for="sp in sponsors" :key="sp.id" :value="sp.id">{{ sp.name }}</option>
                </select>
              </label>
              <label class="field grow"><span>Nombre del stand</span><input v-model="nuevoBooth.name" class="control" placeholder="Stand principal" /></label>
            </div>
            <div class="fila">
              <label class="field grow"><span>Descripción (opcional)</span><input v-model="nuevoBooth.description" class="control" placeholder="Qué ofrece el stand" /></label>
              <label class="field grow"><span>URL (opcional)</span><input v-model="nuevoBooth.url" class="control" placeholder="https://…" /></label>
            </div>
            <div class="actions">
              <AppButton :disabled="busy || nuevoBooth.sponsor === '' || !nuevoBooth.name.trim()" @click="crearBooth">Añadir stand</AppButton>
            </div>
          </template>
        </div>

        <div class="panel">
          <h2>Stands <span class="muted">({{ booths.length }})</span></h2>
          <p v-if="booths.length === 0" class="empty">Aún no hay stands.</p>
          <ul v-else class="lista">
            <li v-for="b in booths" :key="b.id">
              <div>
                <strong>{{ b.name }}</strong>
                <span v-if="b.sponsor" class="muted small">· {{ b.sponsor.name }} ({{ tierLabel[b.sponsor.tier] }})</span>
                <p v-if="b.description" class="muted small">{{ b.description }}</p>
              </div>
              <span class="chip">{{ b.leads_count }} leads</span>
            </li>
          </ul>
        </div>

        <div class="panel">
          <h2>Leads captados <span class="muted">({{ leads.length }})</span></h2>
          <p v-if="leads.length === 0" class="empty">Aún no hay leads.</p>
          <div v-else class="table-wrap">
            <table class="admin-table">
              <thead><tr><th>Asistente</th><th>Nota</th><th>Fecha</th></tr></thead>
              <tbody>
                <tr v-for="l in leads" :key="l.id">
                  <td>
                    <strong>{{ l.attendee?.name ?? '—' }}</strong>
                    <div class="muted email">{{ l.attendee?.email }}</div>
                  </td>
                  <td class="muted">{{ l.note ?? '—' }}</td>
                  <td class="muted">{{ fecha(l.created_at) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- RANKING -->
      <div v-else class="panel">
        <h2>Ranking de participación</h2>
        <p class="muted small">Puntos por asistir a sesiones y visitar stands.</p>
        <p v-if="leaderboard.length === 0" class="empty">Aún no hay puntos registrados.</p>
        <ol v-else class="ranking">
          <li v-for="(row, i) in leaderboard" :key="row.attendee">
            <span class="ranking__pos" :class="{ top: i < 3 }">{{ i + 1 }}</span>
            <strong class="ranking__name">{{ row.name || 'Asistente' }}</strong>
            <span class="ranking__pts">{{ row.points }} pts</span>
          </li>
        </ol>
      </div>

      <p v-if="error" class="error-text">{{ error }}</p>
    </template>
  </section>
</template>

<style scoped>
.back { text-decoration: none; font-size: 0.85rem; }
.back:hover { color: var(--escenia-color-text); }
.small { font-size: 0.8rem; margin: 2px 0 0; }
.grow { flex: 1; }
.add { display: flex; gap: var(--escenia-space-2); flex-wrap: wrap; align-items: center; }
.fila { display: flex; gap: var(--escenia-space-3); flex-wrap: wrap; align-items: end; }
.email { font-size: 0.78rem; }

.tabs { display: flex; gap: var(--escenia-space-2); flex-wrap: wrap; }
.tab { padding: 9px 16px; font: inherit; font-weight: 600; font-size: 0.85rem; color: var(--escenia-color-text-muted); background: transparent; border: 1px solid var(--escenia-color-border); border-radius: var(--escenia-radius-sm); cursor: pointer; transition: all 0.14s ease; }
.tab:hover { color: var(--escenia-color-text); }
.tab.is-active { color: #fff; background: var(--escenia-color-primary); border-color: transparent; }

.color { width: 44px; height: 40px; padding: 2px; background: transparent; border: 1px solid var(--escenia-color-border); border-radius: var(--escenia-radius-sm); cursor: pointer; }
.tracks-row { display: flex; gap: var(--escenia-space-2); flex-wrap: wrap; }
.track-chip { display: inline-flex; align-items: center; gap: 8px; padding: 6px 12px; font-size: 0.82rem; border: 1px solid var(--escenia-color-border); border-radius: 999px; background: rgba(4, 16, 29, 0.35); }
.track-chip .dot { width: 10px; height: 10px; border-radius: 50%; }

.sesion { border-top: 1px solid var(--escenia-color-border); padding-top: var(--escenia-space-3); display: flex; flex-direction: column; gap: var(--escenia-space-2); }
.sesion__title { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.sesion__edit { display: flex; gap: var(--escenia-space-2); flex-wrap: wrap; align-items: center; }
.sesion__edit .control { max-width: 200px; }
.sesion__edit .cap { max-width: 100px; }

.sponsor__head { display: flex; align-items: center; gap: 10px; }
.sponsor__logo { width: 40px; height: 40px; object-fit: contain; border-radius: 8px; background: #fff; padding: 3px; }
.tier { text-transform: uppercase; font-size: 0.68rem; letter-spacing: 0.04em; }
.tier--platinum { background: color-mix(in srgb, #cfe3ff 22%, transparent); color: #cfe3ff; }
.tier--gold { background: color-mix(in srgb, #ffd56b 20%, transparent); color: #ffd56b; }
.tier--silver { background: color-mix(in srgb, #d6dde6 20%, transparent); color: #d6dde6; }
.tier--bronze { background: color-mix(in srgb, #d8926b 22%, transparent); color: #e0a07e; }
.tier--community { background: color-mix(in srgb, var(--escenia-color-primary) 16%, transparent); color: var(--escenia-color-primary); }

.lista { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: var(--escenia-space-2); }
.lista li { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 10px 12px; border: 1px solid var(--escenia-color-border); border-radius: var(--escenia-radius-sm); background: rgba(4, 16, 29, 0.35); }
.table-wrap { overflow-x: auto; }

.ranking { list-style: none; margin: var(--escenia-space-3) 0 0; padding: 0; display: flex; flex-direction: column; gap: var(--escenia-space-2); }
.ranking li { display: flex; align-items: center; gap: var(--escenia-space-3); padding: 10px 12px; border: 1px solid var(--escenia-color-border); border-radius: var(--escenia-radius-sm); background: rgba(4, 16, 29, 0.35); }
.ranking__pos { display: grid; place-items: center; width: 28px; height: 28px; flex-shrink: 0; border-radius: 50%; background: var(--escenia-color-border); color: var(--escenia-color-text-muted); font-weight: 700; font-size: 0.82rem; }
.ranking__pos.top { background: color-mix(in srgb, var(--escenia-color-accent, #17e0a6) 80%, transparent); color: #04101d; }
.ranking__name { flex: 1; }
.ranking__pts { font-variant-numeric: tabular-nums; color: var(--escenia-color-primary); font-weight: 600; }

@media (max-width: 640px) { .sesion__edit .control { max-width: none; flex: 1; } }
</style>
