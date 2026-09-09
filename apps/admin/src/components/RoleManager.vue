<script setup lang="ts">
import { ref } from 'vue'
import { AppButton } from '@escenia/ui'
import { ApiError } from '@escenia/api-client'
import type { PermissionGroup, RoleModel } from '@escenia/types'

import PermissionPicker from '@/components/PermissionPicker.vue'
import { api } from '@/lib/api'

defineProps<{ roles: RoleModel[]; catalog: PermissionGroup[] }>()
const emit = defineEmits<{ changed: [] }>()

const newName = ref('')
const newPerms = ref<string[]>([])
const editing = ref<string | null>(null)
const editPerms = ref<string[]>([])
const busy = ref(false)
const error = ref<string | null>(null)

function message(e: unknown): string {
  return e instanceof ApiError ? e.message : 'Something went wrong.'
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
  <div class="panel stack">
    <h2>Roles</h2>

    <table class="admin-table">
      <thead>
        <tr><th>Role</th><th>Type</th><th>Permissions</th><th></th></tr>
      </thead>
      <tbody>
        <tr v-for="role in roles" :key="role.name">
          <td>{{ role.name }}</td>
          <td><span class="chip">{{ role.is_system ? 'system' : 'custom' }}</span></td>
          <td class="muted">{{ role.permissions.length }}</td>
          <td class="actions">
            <template v-if="!role.is_system">
              <AppButton variant="ghost" :disabled="busy" @click="startEdit(role)">Edit</AppButton>
              <AppButton variant="ghost" :disabled="busy" @click="remove(role)">Delete</AppButton>
            </template>
          </td>
        </tr>
      </tbody>
    </table>

    <div v-if="editing" class="editor">
      <h3>Editing “{{ editing }}”</h3>
      <PermissionPicker v-model="editPerms" :catalog="catalog" />
      <div class="actions">
        <AppButton :disabled="busy" @click="saveEdit">Save role</AppButton>
        <AppButton variant="ghost" :disabled="busy" @click="cancelEdit">Cancel</AppButton>
      </div>
    </div>

    <div class="editor">
      <h3>New custom role</h3>
      <label class="field">
        <span>Name (letters, digits, - and _)</span>
        <input v-model="newName" type="text" placeholder="e.g. Editor" />
      </label>
      <PermissionPicker v-model="newPerms" :catalog="catalog" />
      <div class="actions">
        <AppButton :disabled="busy || newName.length === 0" @click="create">Create role</AppButton>
      </div>
    </div>

    <p v-if="error" class="error-text">{{ error }}</p>
  </div>
</template>

<style scoped>
.editor {
  display: flex;
  flex-direction: column;
  gap: var(--escenia-space-3);
  border-top: 1px solid var(--escenia-color-border);
  padding-top: var(--escenia-space-3);
}

.editor h3 {
  margin: 0;
  font-size: 0.95rem;
}
</style>
