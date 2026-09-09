<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { AppButton } from '@escenia/ui'
import { ApiError } from '@escenia/api-client'
import type { FieldType, Registrant, RegistrationField } from '@escenia/types'

import ListToolbar from '@/components/ListToolbar.vue'
import PaginationBar from '@/components/PaginationBar.vue'
import { usePagination } from '@/composables/usePagination'
import { fecha } from '@/lib/eventLabels'
import { api } from '@/lib/api'

const route = useRoute()
const id = route.params.id as string

const isOpen = ref(false)
const fields = ref<RegistrationField[]>([])
const registrants = ref<Registrant[]>([])
const loading = ref(true)
const saving = ref(false)
const error = ref<string | null>(null)
const saved = ref(false)

const search = ref('')

const tiposCampo: [FieldType, string][] = [
  ['text', 'Texto'],
  ['email', 'Correo'],
  ['textarea', 'Texto largo'],
  ['select', 'Selección'],
  ['checkbox', 'Casilla'],
]

const filtered = computed(() => {
  const term = search.value.trim().toLowerCase()
  if (term === '') return registrants.value
  return registrants.value.filter((r) => {
    const n = (r.contact.name ?? '').toLowerCase()
    const e = (r.contact.email ?? '').toLowerCase()
    return n.includes(term) || e.includes(term)
  })
})

const { page, pageCount, total, pageItems, from, to, go } = usePagination(filtered, 10)

function message(e: unknown): string {
  return e instanceof ApiError ? e.message : 'Algo salió mal.'
}

function slug(label: string): string {
  return label
    .toLowerCase()
    .trim()
    .replace(/[^a-z0-9]+/g, '_')
    .replace(/^_|_$/g, '')
}

async function load(): Promise<void> {
  loading.value = true
  error.value = null
  try {
    const [form, regs] = await Promise.all([api.registrationForm(id), api.registrations(id)])
    if (form.data !== null) {
      isOpen.value = form.data.is_open
      fields.value = form.data.fields.map((f) => ({ ...f }))
    }
    registrants.value = regs.data
  } catch (e) {
    error.value = message(e)
  } finally {
    loading.value = false
  }
}

function addField(): void {
  fields.value.push({ key: '', label: '', type: 'text', required: false })
}

function removeField(index: number): void {
  fields.value.splice(index, 1)
}

async function save(): Promise<void> {
  saving.value = true
  saved.value = false
  error.value = null
  try {
    const clean = fields.value
      .filter((f) => f.label.trim() !== '')
      .map((f) => ({ ...f, key: f.key || slug(f.label) }))
    await api.saveRegistrationForm(id, { is_open: isOpen.value, fields: clean })
    fields.value = clean
    saved.value = true
  } catch (e) {
    error.value = message(e)
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<template>
  <section class="stack">
    <RouterLink :to="{ name: 'event-detail', params: { id } }" class="back muted">‹ Volver al evento</RouterLink>

    <div class="page-head">
      <h1>Registro</h1>
      <p>Configura el formulario público y consulta los inscritos.</p>
    </div>

    <p v-if="loading" class="muted">Cargando…</p>

    <template v-else>
      <!-- Formulario de registro -->
      <div class="panel stack">
        <h2>Formulario de registro</h2>

        <label class="switch">
          <input v-model="isOpen" type="checkbox" />
          <span>Registro abierto al público</span>
        </label>

        <p class="muted small">
          Nombre y correo se piden siempre. Aquí añades campos personalizados extra.
        </p>

        <div v-for="(field, i) in fields" :key="i" class="field-row">
          <input v-model="field.label" class="control" type="text" placeholder="Etiqueta del campo" />
          <select v-model="field.type" class="control">
            <option v-for="[t, label] in tiposCampo" :key="t" :value="t">{{ label }}</option>
          </select>
          <label class="req">
            <input v-model="field.required" type="checkbox" /> Obligatorio
          </label>
          <button type="button" class="icon-btn" @click="removeField(i)">Quitar</button>
        </div>

        <div class="actions">
          <AppButton variant="ghost" @click="addField">+ Añadir campo</AppButton>
          <AppButton :disabled="saving" @click="save">{{ saving ? 'Guardando…' : 'Guardar formulario' }}</AppButton>
          <span v-if="saved" class="ok-text">Guardado.</span>
        </div>
      </div>

      <!-- Inscritos -->
      <ListToolbar v-model:search="search" :with-view="false" placeholder="Buscar inscrito…" />

      <div class="panel">
        <h2>Inscritos <span class="muted">({{ registrants.length }})</span></h2>
        <p v-if="total === 0" class="empty">Aún no hay inscritos.</p>
        <div v-else class="table-wrap">
          <table class="admin-table">
            <thead>
              <tr><th>Nombre</th><th>Correo</th><th>Fecha de registro</th></tr>
            </thead>
            <tbody>
              <tr v-for="r in pageItems" :key="r.id">
                <td><strong>{{ r.contact.name ?? '—' }}</strong></td>
                <td class="muted">{{ r.contact.email ?? '—' }}</td>
                <td class="muted">{{ fecha(r.registered_at) }}</td>
              </tr>
            </tbody>
          </table>
          <PaginationBar :page="page" :page-count="pageCount" :total="total" :from="from" :to="to" @go="go" />
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

.small {
  font-size: 0.8rem;
}

.switch {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 0.9rem;
}

.field-row {
  display: grid;
  grid-template-columns: 1fr 160px auto auto;
  gap: var(--escenia-space-2);
  align-items: center;
}

.req {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 0.82rem;
  color: var(--escenia-color-text-muted);
  white-space: nowrap;
}

@media (max-width: 640px) {
  .field-row {
    grid-template-columns: 1fr;
  }
}
</style>
