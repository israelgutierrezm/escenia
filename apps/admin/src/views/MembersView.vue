<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { AppButton } from '@escenia/ui'
import { ApiError } from '@escenia/api-client'
import type { MemberSummary, PermissionGroup, RoleModel } from '@escenia/types'

import MemberAccessEditor from '@/components/MemberAccessEditor.vue'
import RoleManager from '@/components/RoleManager.vue'
import { api } from '@/lib/api'

const members = ref<MemberSummary[]>([])
const roles = ref<RoleModel[]>([])
const catalog = ref<PermissionGroup[]>([])
const selected = ref<MemberSummary | null>(null)
const loading = ref(true)
const error = ref<string | null>(null)

const customRoleNames = computed(() => roles.value.filter((role) => !role.is_system).map((role) => role.name))

function message(e: unknown): string {
  return e instanceof ApiError ? e.message : 'Something went wrong.'
}

async function loadAll(): Promise<void> {
  loading.value = true
  error.value = null
  try {
    const [memberList, roleList, permissionCatalog] = await Promise.all([
      api.members(),
      api.roles(),
      api.permissionCatalog(),
    ])
    members.value = memberList.data
    roles.value = roleList.data
    catalog.value = permissionCatalog.data
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
    <div>
      <h1>Members &amp; roles</h1>
      <p class="muted">Manage who belongs to this tenant and what they can do.</p>
    </div>

    <p v-if="loading" class="muted">Loading…</p>
    <p v-else-if="error" class="error-text">{{ error }}</p>

    <template v-else>
      <div class="panel">
        <h2>Members</h2>
        <table class="admin-table">
          <thead>
            <tr><th>Name</th><th>Email</th><th>Role</th><th></th></tr>
          </thead>
          <tbody>
            <tr v-for="member in members" :key="member.id">
              <td>{{ member.name }}</td>
              <td class="muted">{{ member.email }}</td>
              <td><span class="chip">{{ member.membership_role }}</span></td>
              <td class="actions">
                <AppButton variant="ghost" @click="selected = member">Manage</AppButton>
              </td>
            </tr>
          </tbody>
        </table>
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
