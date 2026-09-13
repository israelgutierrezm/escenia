<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { AppButton } from '@escenia/ui'
import { ApiError } from '@escenia/api-client'
import type { MemberSummary, PermissionGroup, RoleModel } from '@escenia/types'

import ListToolbar from '@/components/ListToolbar.vue'
import PaginationBar from '@/components/PaginationBar.vue'
import MemberAccessEditor from '@/components/MemberAccessEditor.vue'
import RoleManager from '@/components/RoleManager.vue'
import { usePagination } from '@/composables/usePagination'
import { api } from '@/lib/api'

const members = ref<MemberSummary[]>([])
const roles = ref<RoleModel[]>([])
const catalog = ref<PermissionGroup[]>([])
const selected = ref<MemberSummary | null>(null)
const loading = ref(true)
const error = ref<string | null>(null)

const search = ref('')
const rolFiltro = ref('')
const vista = ref<'lista' | 'cuadricula'>('lista')

const customRoleNames = computed(() => roles.value.filter((r) => !r.is_system).map((r) => r.name))

const roleMap: Record<string, string> = { owner: 'Propietario', admin: 'Administrador', member: 'Miembro' }
function roleLabel(role: string): string {
  return roleMap[role] ?? role
}

function initials(name: string): string {
  return name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((w) => w[0]?.toUpperCase() ?? '')
    .join('')
}

const filtered = computed(() => {
  const term = search.value.trim().toLowerCase()
  return members.value.filter((m) => {
    const okSearch =
      term === '' || m.name.toLowerCase().includes(term) || m.email.toLowerCase().includes(term)
    const okRole = rolFiltro.value === '' || m.membership_role === rolFiltro.value
    return okSearch && okRole
  })
})

const filterCount = computed(() => (rolFiltro.value ? 1 : 0))
const { page, pageCount, total, pageItems, from, to, go } = usePagination(filtered, 8)

function message(e: unknown): string {
  return e instanceof ApiError ? e.message : 'Algo salió mal.'
}

async function loadAll(): Promise<void> {
  loading.value = true
  error.value = null
  try {
    const [m, r, c] = await Promise.all([api.members(), api.roles(), api.permissionCatalog()])
    members.value = m.data
    roles.value = r.data
    catalog.value = c.data
  } catch (e) {
    error.value = message(e)
  } finally {
    loading.value = false
  }
}

async function reloadRoles(): Promise<void> {
  roles.value = (await api.roles()).data
}

async function reloadMembers(): Promise<void> {
  members.value = (await api.members()).data
}

onMounted(loadAll)
</script>

<template>
  <section class="stack">
    <div class="page-head">
      <h1>Miembros y roles</h1>
      <p>Gestiona quién pertenece a esta organización y qué puede hacer.</p>
    </div>

    <p v-if="loading" class="muted">Cargando…</p>
    <p v-else-if="error" role="alert" class="error-text">{{ error }}</p>

    <template v-else>
      <ListToolbar
        v-model:search="search"
        v-model:view="vista"
        view-key="miembros"
        placeholder="Buscar por nombre o correo…"
        :filter-count="filterCount"
      >
        <template #filters>
          <select v-model="rolFiltro" class="control">
            <option value="">Rol: todos</option>
            <option value="owner">Propietario</option>
            <option value="admin">Administrador</option>
            <option value="member">Miembro</option>
          </select>
          <button v-if="filterCount" type="button" class="icon-btn" @click="rolFiltro = ''">Limpiar</button>
        </template>
      </ListToolbar>

      <div class="panel">
        <p v-if="total === 0" class="empty">Sin miembros que coincidan con la búsqueda.</p>

        <!-- Vista lista -->
        <div v-else-if="vista === 'lista'" class="table-wrap">
          <table class="admin-table">
            <thead>
              <tr><th>Nombre</th><th>Correo</th><th>Rol</th><th></th></tr>
            </thead>
            <tbody>
              <tr v-for="member in pageItems" :key="member.id">
                <td>
                  <div class="who">
                    <span class="card__avatar sm">{{ initials(member.name) }}</span>
                    {{ member.name }}
                  </div>
                </td>
                <td class="muted">{{ member.email }}</td>
                <td><span class="chip role-badge">{{ roleLabel(member.membership_role) }}</span></td>
                <td class="actions">
                  <AppButton variant="ghost" @click="selected = member">Gestionar</AppButton>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Vista cuadrícula -->
        <div v-else class="grid-cards">
          <article v-for="member in pageItems" :key="member.id" class="card">
            <div class="who">
              <span class="card__avatar">{{ initials(member.name) }}</span>
              <div>
                <strong>{{ member.name }}</strong>
                <div class="muted email">{{ member.email }}</div>
              </div>
            </div>
            <span class="chip role-badge">{{ roleLabel(member.membership_role) }}</span>
            <AppButton variant="ghost" @click="selected = member">Gestionar</AppButton>
          </article>
        </div>

        <PaginationBar :page="page" :page-count="pageCount" :total="total" :from="from" :to="to" @go="go" />
      </div>

      <MemberAccessEditor
        v-if="selected"
        :key="selected.id"
        :member="selected"
        :custom-roles="customRoleNames"
        :catalog="catalog"
        @updated="reloadMembers"
      />

      <RoleManager :roles="roles" :catalog="catalog" @changed="reloadRoles" />
    </template>
  </section>
</template>

<style scoped>
.who {
  display: flex;
  align-items: center;
  gap: 10px;
}

.card__avatar.sm {
  width: 30px;
  height: 30px;
  font-size: 0.75rem;
}

.email {
  font-size: 0.8rem;
}
</style>
