<script setup lang="ts">
import { ref, watch } from 'vue'
import { AppButton } from '@escenia/ui'
import { ApiError } from '@escenia/api-client'
import type { MemberAccess, MemberSummary, PermissionGroup, TenantRoleKey } from '@escenia/types'

import PermissionPicker from '@/components/PermissionPicker.vue'
import { api } from '@/lib/api'

const props = defineProps<{
  member: MemberSummary
  customRoles: string[]
  catalog: PermissionGroup[]
}>()

const emit = defineEmits<{ updated: [] }>()

const access = ref<MemberAccess | null>(null)
const baseRole = ref<string>('member')
const selectedCustom = ref<string[]>([])
const directPerms = ref<string[]>([])
const busy = ref(false)
const error = ref<string | null>(null)
const saved = ref<string | null>(null)

function message(e: unknown): string {
  return e instanceof ApiError ? e.message : 'Algo salió mal.'
}

function hydrate(next: MemberAccess): void {
  access.value = next
  baseRole.value = next.membership_role ?? 'member'
  selectedCustom.value = [...next.custom_roles]
  directPerms.value = [...next.direct_permissions]
}

async function load(): Promise<void> {
  error.value = null
  saved.value = null
  try {
    hydrate((await api.memberAccess(props.member.id)).data)
  } catch (e) {
    error.value = message(e)
  }
}

watch(() => props.member.id, load, { immediate: true })

async function run(fn: () => Promise<MemberAccess>, label: string): Promise<void> {
  error.value = null
  saved.value = null
  busy.value = true
  try {
    hydrate(await fn())
    saved.value = label
    emit('updated')
  } catch (e) {
    error.value = message(e)
  } finally {
    busy.value = false
  }
}

function applyBase(): Promise<void> {
  return run(
    async () => (await api.changeMembershipRole(props.member.id, baseRole.value as TenantRoleKey)).data,
    'Rol base actualizado',
  )
}

function applyRoles(): Promise<void> {
  return run(async () => (await api.syncMemberRoles(props.member.id, selectedCustom.value)).data, 'Roles actualizados')
}

function applyPerms(): Promise<void> {
  return run(async () => (await api.syncMemberPermissions(props.member.id, directPerms.value)).data, 'Permisos actualizados')
}

function toggleCustom(name: string, checked: boolean): void {
  selectedCustom.value = checked
    ? [...selectedCustom.value, name]
    : selectedCustom.value.filter((n) => n !== name)
}
</script>

<template>
  <div v-if="access" class="panel stack">
    <div class="who">
      <span class="card__avatar">{{ member.name.slice(0, 1).toUpperCase() }}</span>
      <div>
        <h2 style="margin: 0">{{ member.name }}</h2>
        <p class="muted" style="margin: 2px 0 0">{{ member.email }}</p>
      </div>
    </div>

    <div class="block">
      <h3>Rol base</h3>
      <div class="actions">
        <label class="field">
          <span>Nivel de membresía</span>
          <select v-model="baseRole">
            <option value="owner">Propietario</option>
            <option value="admin">Administrador</option>
            <option value="member">Miembro</option>
          </select>
        </label>
        <AppButton :disabled="busy" @click="applyBase">Aplicar</AppButton>
      </div>
    </div>

    <div v-if="customRoles.length" class="block">
      <h3>Roles personalizados</h3>
      <div class="checks">
        <label v-for="name in customRoles" :key="name" class="check">
          <input
            type="checkbox"
            :checked="selectedCustom.includes(name)"
            @change="toggleCustom(name, ($event.target as HTMLInputElement).checked)"
          />
          <span>{{ name }}</span>
        </label>
      </div>
      <div class="actions"><AppButton :disabled="busy" @click="applyRoles">Aplicar roles</AppButton></div>
    </div>

    <div class="block">
      <h3>Permisos directos</h3>
      <PermissionPicker v-model="directPerms" :catalog="catalog" />
      <div class="actions"><AppButton :disabled="busy" @click="applyPerms">Aplicar permisos</AppButton></div>
    </div>

    <div class="block">
      <h3>Permisos efectivos</h3>
      <div class="actions">
        <span v-for="permission in access.effective_permissions" :key="permission" class="chip chip--primary">
          {{ permission }}
        </span>
        <span v-if="access.effective_permissions.length === 0" class="muted">Ninguno</span>
      </div>
    </div>

    <p v-if="saved" class="ok-text">{{ saved }}.</p>
    <p v-if="error" class="error-text">{{ error }}</p>
  </div>
</template>

<style scoped>
.block {
  display: flex;
  flex-direction: column;
  gap: var(--escenia-space-3);
  border-top: 1px solid var(--escenia-color-border);
  padding-top: var(--escenia-space-4);
}
</style>
