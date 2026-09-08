<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { AppButton } from '@escenia/ui'
import type { Workspace } from '@escenia/types'

import { useAuthStore } from '@/stores/auth'
import { api, setActiveTenant } from '@/lib/api'

const router = useRouter()
const auth = useAuthStore()

const workspaces = ref<Workspace[]>([])
const loading = ref(true)

onMounted(async () => {
  const tenant = auth.tenants[0]

  if (tenant) {
    setActiveTenant(tenant.id)
    const response = await api.workspaces()
    workspaces.value = response.data
  }

  loading.value = false
})

async function onLogout(): Promise<void> {
  await auth.logout()
  await router.push({ name: 'login' })
}
</script>

<template>
  <main class="shell">
    <header class="bar">
      <div>
        <h1>Escenia</h1>
        <p class="muted">{{ auth.user?.name }} · {{ auth.user?.email }}</p>
      </div>
      <AppButton variant="ghost" @click="onLogout">Sign out</AppButton>
    </header>

    <section class="panel">
      <h2>Tenants</h2>
      <ul>
        <li v-for="tenant in auth.tenants" :key="tenant.id">
          {{ tenant.name }} <span class="muted">({{ tenant.role }})</span>
        </li>
      </ul>
    </section>

    <section class="panel">
      <h2>Workspaces</h2>
      <p v-if="loading" class="muted">Loading…</p>
      <ul v-else>
        <li v-for="workspace in workspaces" :key="workspace.id">
          {{ workspace.name }} <span class="muted">/{{ workspace.slug }}</span>
        </li>
      </ul>
    </section>
  </main>
</template>

<style scoped>
.shell {
  max-width: 720px;
  margin: 0 auto;
  padding: var(--escenia-space-6);
  display: flex;
  flex-direction: column;
  gap: var(--escenia-space-6);
}

.bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.panel {
  background: var(--escenia-color-surface);
  border: 1px solid var(--escenia-color-border);
  border-radius: var(--escenia-radius-md);
  padding: var(--escenia-space-4);
}

.panel ul {
  margin: 0;
  padding-left: var(--escenia-space-4);
}

.muted {
  color: var(--escenia-color-text-muted);
}
</style>
