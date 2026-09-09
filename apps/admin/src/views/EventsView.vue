<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { AppButton } from '@escenia/ui'
import { ApiError } from '@escenia/api-client'
import type { EventModel, EventStatus, EventTemplate, EventTypeKey, Workspace } from '@escenia/types'

import ListToolbar from '@/components/ListToolbar.vue'
import PaginationBar from '@/components/PaginationBar.vue'
import { usePagination } from '@/composables/usePagination'
import { estadoEvento, fecha, tipoEvento } from '@/lib/eventLabels'
import { api } from '@/lib/api'

const router = useRouter()

const events = ref<EventModel[]>([])
const workspaces = ref<Workspace[]>([])
const templates = ref<EventTemplate[]>([])
const loading = ref(true)
const error = ref<string | null>(null)

const search = ref('')
const estadoFiltro = ref('')
const vista = ref<'lista' | 'cuadricula'>('cuadricula')

const creando = ref(false)
const guardando = ref(false)
const form = ref({ title: '', type: 'webinar' as EventTypeKey, workspace_id: '', template_id: '', description: '' })

const tipos = Object.entries(tipoEvento) as [EventTypeKey, string][]
const estados: EventStatus[] = ['draft', 'scheduled', 'live', 'ended', 'archived', 'canceled']

const filtered = computed(() => {
  const term = search.value.trim().toLowerCase()
  return events.value.filter((e) => {
    const okSearch = term === '' || e.title.toLowerCase().includes(term)
    const okEstado = estadoFiltro.value === '' || e.status === estadoFiltro.value
    return okSearch && okEstado
  })
})

const filterCount = computed(() => (estadoFiltro.value ? 1 : 0))
const { page, pageCount, total, pageItems, from, to, go } = usePagination(filtered, 9)

function message(e: unknown): string {
  return e instanceof ApiError ? e.message : 'Algo salió mal.'
}

async function load(): Promise<void> {
  loading.value = true
  error.value = null
  try {
    const [ev, ws, tp] = await Promise.all([api.events(), api.workspaces(), api.eventTemplates()])
    events.value = ev.data
    workspaces.value = ws.data
    templates.value = tp.data
    form.value.workspace_id = ws.data[0]?.id ?? ''
  } catch (e) {
    error.value = message(e)
  } finally {
    loading.value = false
  }
}

async function crear(): Promise<void> {
  if (form.value.title.trim() === '' || form.value.workspace_id === '') return
  guardando.value = true
  error.value = null
  try {
    const created = await api.createEvent({
      workspace_id: form.value.workspace_id,
      title: form.value.title,
      type: form.value.type,
      template_id: form.value.template_id || undefined,
      description: form.value.description || undefined,
    })
    await router.push({ name: 'event-detail', params: { id: created.data.id } })
  } catch (e) {
    error.value = message(e)
  } finally {
    guardando.value = false
  }
}

function abrir(event: EventModel): void {
  router.push({ name: 'event-detail', params: { id: event.id } })
}

onMounted(load)
</script>

<template>
  <section class="stack">
    <div class="page-head">
      <h1>Eventos</h1>
      <p>Crea y gestiona los eventos de tu organización.</p>
    </div>

    <p v-if="loading" class="muted">Cargando…</p>
    <p v-else-if="error" class="error-text">{{ error }}</p>

    <template v-else>
      <ListToolbar
        v-model:search="search"
        v-model:view="vista"
        view-key="eventos"
        placeholder="Buscar evento…"
        :filter-count="filterCount"
      >
        <template #actions>
          <AppButton @click="creando = !creando">{{ creando ? 'Cerrar' : '+ Nuevo evento' }}</AppButton>
        </template>
        <template #filters>
          <select v-model="estadoFiltro" class="control">
            <option value="">Estado: todos</option>
            <option v-for="s in estados" :key="s" :value="s">{{ estadoEvento[s].label }}</option>
          </select>
          <button v-if="filterCount" type="button" class="icon-btn" @click="estadoFiltro = ''">Limpiar</button>
        </template>
      </ListToolbar>

      <!-- Crear evento -->
      <div v-if="creando" class="panel stack">
        <h2>Nuevo evento</h2>
        <div class="form-grid">
          <label class="field">
            <span>Título</span>
            <input v-model="form.title" type="text" placeholder="p. ej. Lanzamiento Q3" />
          </label>
          <label class="field">
            <span>Tipo</span>
            <select v-model="form.type">
              <option v-for="[key, label] in tipos" :key="key" :value="key">{{ label }}</option>
            </select>
          </label>
          <label v-if="workspaces.length > 1" class="field">
            <span>Espacio de trabajo</span>
            <select v-model="form.workspace_id">
              <option v-for="w in workspaces" :key="w.id" :value="w.id">{{ w.name }}</option>
            </select>
          </label>
          <label class="field">
            <span>Plantilla (opcional)</span>
            <select v-model="form.template_id">
              <option value="">Sin plantilla</option>
              <option v-for="t in templates" :key="t.id" :value="t.id">{{ t.name }}</option>
            </select>
          </label>
        </div>
        <label class="field">
          <span>Descripción (opcional)</span>
          <textarea v-model="form.description" rows="2"></textarea>
        </label>
        <div class="actions">
          <AppButton :disabled="guardando || !form.title.trim()" @click="crear">Crear evento</AppButton>
          <AppButton variant="ghost" :disabled="guardando" @click="creando = false">Cancelar</AppButton>
        </div>
      </div>

      <p v-if="total === 0" class="empty">No hay eventos que coincidan.</p>

      <!-- Cuadrícula -->
      <div v-else-if="vista === 'cuadricula'" class="grid-cards">
        <article v-for="event in pageItems" :key="event.id" class="card ev" @click="abrir(event)">
          <div class="actions" style="justify-content: space-between">
            <span class="chip role-badge">{{ tipoEvento[event.type] }}</span>
            <span class="chip" :class="estadoEvento[event.status].clase">{{ estadoEvento[event.status].label }}</span>
          </div>
          <strong class="ev__title">{{ event.title }}</strong>
          <span class="muted">{{ fecha(event.scheduled_start_at) }}</span>
        </article>
      </div>

      <!-- Lista -->
      <div v-else class="panel table-wrap">
        <table class="admin-table">
          <thead>
            <tr><th>Evento</th><th>Tipo</th><th>Estado</th><th>Inicio</th><th></th></tr>
          </thead>
          <tbody>
            <tr v-for="event in pageItems" :key="event.id">
              <td><strong>{{ event.title }}</strong></td>
              <td class="muted">{{ tipoEvento[event.type] }}</td>
              <td><span class="chip" :class="estadoEvento[event.status].clase">{{ estadoEvento[event.status].label }}</span></td>
              <td class="muted">{{ fecha(event.scheduled_start_at) }}</td>
              <td class="actions"><AppButton variant="ghost" @click="abrir(event)">Abrir</AppButton></td>
            </tr>
          </tbody>
        </table>
      </div>

      <PaginationBar :page="page" :page-count="pageCount" :total="total" :from="from" :to="to" @go="go" />
    </template>
  </section>
</template>

<style scoped>
.form-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: var(--escenia-space-3);
}

.ev {
  cursor: pointer;
}

.ev__title {
  font-size: 1.05rem;
}
</style>
