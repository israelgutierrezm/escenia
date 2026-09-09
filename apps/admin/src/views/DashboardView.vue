<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { ApiError } from '@escenia/api-client'
import type { Workspace } from '@escenia/types'

import { useAuthStore } from '@/stores/auth'
import { api } from '@/lib/api'

const auth = useAuthStore()

const workspaces = ref<Workspace[]>([])
const loading = ref(true)
const error = ref<string | null>(null)

onMounted(async () => {
  try {
    if (auth.activeTenant) {
      workspaces.value = (await api.workspaces()).data
    }
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'Unable to load workspaces.'
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <section class="stack">
    <h1>Overview</h1>

    <div class="panel">
      <h2>Tenants</h2>
      <ul>
        <li v-for="tenant in auth.tenants" :key="tenant.id">
          {{ tenant.name }} <span class="muted">({{ tenant.role }})</span>
        </li>
      </ul>
    </div>

    <div class="panel">
      <h2>Workspaces</h2>
      <p v-if="loading" class="muted">Loading…</p>
      <p v-else-if="error" class="error-text">{{ error }}</p>
      <ul v-else>
        <li v-for="workspace in workspaces" :key="workspace.id">
          {{ workspace.name }} <span class="muted">/{{ workspace.slug }}</span>
        </li>
      </ul>
    </div>
  </section>
</template>
