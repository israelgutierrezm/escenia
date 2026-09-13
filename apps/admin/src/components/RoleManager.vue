<script setup lang="ts">
import { computed, ref } from 'vue'
import { AppButton } from '@escenia/ui'
import { ApiError } from '@escenia/api-client'
import type { PermissionGroup, RoleModel } from '@escenia/types'

import ListToolbar from '@/components/ListToolbar.vue'
import PaginationBar from '@/components/PaginationBar.vue'
import PermissionPicker from '@/components/PermissionPicker.vue'
import { usePagination } from '@/composables/usePagination'
import { api } from '@/lib/api'

const props = defineProps<{ roles: RoleModel[]; catalog: PermissionGroup[] }>()
const emit = defineEmits<{ changed: [] }>()

const newName = ref('')
const newPerms = ref<string[]>([])
const editing = ref<string | null>(null)
const editPerms = ref<string[]>([])
const busy = ref(false)
const error = ref<string | null>(null)

const search = ref('')
const tipoFiltro = ref('')
const vista = ref<'lista' | 'cuadricula'>('lista')

const filtered = computed(() => {
  const term = search.value.trim().toLowerCase()
  return props.roles.filter((r) => {
    const okSearch = term === '' || r.name.toLowerCase().includes(term)
    const okTipo =
      tipoFiltro.value === '' ||
      (tipoFiltro.value === 'system' ? r.is_system : !r.is_system)
    return okSearch && okTipo
  })
})

const filterCount = computed(() => (tipoFiltro.value ? 1 : 0))
const { page, pageCount, total, pageItems, from, to, go } = usePagination(filtered, 8)

function message(e: unknown): string {
  return e instanceof ApiError ? e.message : 'Algo salió mal.'
}

async function create(): Promise<void> {
  error.value = null
  busy.value = true
  try {
    await api.createRole({ name: newName.value, permissions: newPerms.value })
    newName.value = ''
    newPerms.value = []
    emit('changed')
  } catch (e) {
    error.value = message(e)
  } finally {
    busy.value = false
  }
}

function startEdit(role: RoleModel): void {
  editing.value = role.name
  editPerms.value = [...role.permissions]
}

function cancelEdit(): void {
  editing.value = null
  editPerms.value = []
}

async function saveEdit(): Promise<void> {
  if (editing.value === null) return
  error.value = null
  busy.value = true
  try {
    await api.updateRole(editing.value, { permissions: editPerms.value })
    cancelEdit()
    emit('changed')
  } catch (e) {
    error.value = message(e)
  } finally {
    busy.value = false
  }
}

async function remove(role: RoleModel): Promise<void> {
  error.value = null
  busy.value = true
  try {
    await api.deleteRole(role.name)
    if (editing.value === role.name) cancelEdit()
    emit('changed')
  } catch (e) {
    error.value = message(e)
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <div class="stack">
    <ListToolbar
      v-model:search="search"
      v-model:view="vista"
      view-key="roles"
      placeholder="Buscar rol…"
      :filter-count="filterCount"
    >
      <template #filters>
        <select v-model="tipoFiltro" class="control">
          <option value="">Tipo: todos</option>
          <option value="system">De sistema</option>
          <option value="custom">Personalizados</option>
        </select>
        <button v-if="filterCount" type="button" class="icon-btn" @click="tipoFiltro = ''">Limpiar</button>
      </template>
    </ListToolbar>

    <div class="panel">
      <h2>Roles</h2>

      <p v-if="total === 0" class="empty">Sin roles que coincidan.</p>

      <div v-else-if="vista === 'lista'" class="table-wrap">
        <table class="admin-table">
          <thead>
            <tr><th>Rol</th><th>Tipo</th><th>Permisos</th><th></th></tr>
          </thead>
          <tbody>
            <tr v-for="role in pageItems" :key="role.name">
              <td><strong>{{ role.name }}</strong></td>
              <td>
                <span class="chip" :class="role.is_system ? '' : 'chip--accent'">
                  {{ role.is_system ? 'sistema' : 'personalizado' }}
                </span>
              </td>
              <td class="muted">{{ role.permissions.length }}</td>
              <td class="actions">
                <template v-if="!role.is_system">
                  <AppButton variant="ghost" :disabled="busy" @click="startEdit(role)">Editar</AppButton>
                  <AppButton variant="danger" :disabled="busy" @click="remove(role)">Eliminar</AppButton>
                </template>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-else class="grid-cards">
        <article v-for="role in pageItems" :key="role.name" class="card">
          <div class="actions" style="justify-content: space-between">
            <strong>{{ role.name }}</strong>
            <span class="chip" :class="role.is_system ? '' : 'chip--accent'">
              {{ role.is_system ? 'sistema' : 'personalizado' }}
            </span>
          </div>
          <span class="muted">{{ role.permissions.length }} permisos</span>
          <div v-if="!role.is_system" class="actions">
            <AppButton variant="ghost" :disabled="busy" @click="startEdit(role)">Editar</AppButton>
            <AppButton variant="danger" :disabled="busy" @click="remove(role)">Eliminar</AppButton>
          </div>
        </article>
      </div>

      <PaginationBar :page="page" :page-count="pageCount" :total="total" :from="from" :to="to" @go="go" />
    </div>

    <div v-if="editing" class="panel stack">
      <h3>Editando «{{ editing }}»</h3>
      <PermissionPicker v-model="editPerms" :catalog="catalog" />
      <div class="actions">
        <AppButton :disabled="busy" @click="saveEdit">Guardar rol</AppButton>
        <AppButton variant="ghost" :disabled="busy" @click="cancelEdit">Cancelar</AppButton>
      </div>
    </div>

    <div class="panel stack">
      <h3>Nuevo rol personalizado</h3>
      <label class="field">
        <span>Nombre (letras, dígitos, - y _)</span>
        <input v-model="newName" type="text" placeholder="p. ej. Editor" />
      </label>
      <PermissionPicker v-model="newPerms" :catalog="catalog" />
      <div class="actions">
        <AppButton :disabled="busy || newName.length === 0" @click="create">Crear rol</AppButton>
      </div>
    </div>

    <p v-if="error" role="alert" class="error-text">{{ error }}</p>
  </div>
</template>
