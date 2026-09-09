<script setup lang="ts">
import { useRouter } from 'vue-router'
import { AppButton } from '@escenia/ui'

import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const auth = useAuthStore()

function onTenantChange(event: Event): void {
  auth.selectTenant((event.target as HTMLSelectElement).value || null)
}

async function onLogout(): Promise<void> {
  await auth.logout()
  await router.push({ name: 'login' })
}
</script>

<template>
  <div class="layout">
    <header class="bar">
      <div class="brand">
        <strong>Escenia</strong>
        <span class="muted">Admin</span>
      </div>

      <div class="bar-right">
        <label v-if="auth.tenants.length" class="tenant">
          <span class="sr-only">Tenant</span>
          <select :value="auth.activeTenantId ?? ''" @change="onTenantChange">
            <option v-for="tenant in auth.tenants" :key="tenant.id" :value="tenant.id">
              {{ tenant.name }} · {{ tenant.role }}
            </option>
          </select>
        </label>
        <span class="muted user">{{ auth.user?.email }}</span>
        <AppButton variant="ghost" @click="onLogout">Sign out</AppButton>
      </div>
    </header>

    <div class="body">
      <nav class="nav">
        <RouterLink :to="{ name: 'dashboard' }" class="nav-link">Overview</RouterLink>
        <RouterLink v-if="auth.canManageMembers" :to="{ name: 'members' }" class="nav-link">
          Members &amp; roles
        </RouterLink>
        <RouterLink v-if="auth.canManageTenant" :to="{ name: 'settings' }" class="nav-link">
          Settings
        </RouterLink>
        <RouterLink v-if="auth.isSuperAdmin" :to="{ name: 'system-settings' }" class="nav-link">
          System settings
        </RouterLink>
      </nav>

      <main class="content">
        <RouterView :key="auth.activeTenantId ?? 'none'" />
      </main>
    </div>
  </div>
</template>

<style scoped>
.layout {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}

.bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--escenia-space-4);
  padding: var(--escenia-space-3) var(--escenia-space-6);
  border-bottom: 1px solid var(--escenia-color-border);
  background: var(--escenia-color-surface);
}

.brand {
  display: flex;
  align-items: baseline;
  gap: var(--escenia-space-2);
}

.bar-right {
  display: flex;
  align-items: center;
  gap: var(--escenia-space-3);
}

.tenant select {
  font: inherit;
  padding: var(--escenia-space-1) var(--escenia-space-2);
  background: var(--escenia-color-bg);
  color: var(--escenia-color-text);
  border: 1px solid var(--escenia-color-border);
  border-radius: var(--escenia-radius-sm);
}

.user {
  font-size: 0.875rem;
}

.body {
  display: flex;
  flex: 1;
  align-items: flex-start;
}

.nav {
  display: flex;
  flex-direction: column;
  gap: var(--escenia-space-1);
  padding: var(--escenia-space-4);
  width: 200px;
  border-right: 1px solid var(--escenia-color-border);
  min-height: calc(100vh - 57px);
}

.nav-link {
  padding: var(--escenia-space-2) var(--escenia-space-3);
  border-radius: var(--escenia-radius-sm);
  color: var(--escenia-color-text-muted);
  text-decoration: none;
  font-size: 0.9rem;
}

.nav-link:hover {
  background: var(--escenia-color-surface-muted);
  color: var(--escenia-color-text);
}

.nav-link.router-link-exact-active {
  background: var(--escenia-color-surface-muted);
  color: var(--escenia-color-text);
}

.content {
  flex: 1;
  padding: var(--escenia-space-6);
  max-width: 960px;
}

.muted {
  color: var(--escenia-color-text-muted);
}

.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
}
</style>
