<script setup lang="ts">
import { useRouter } from 'vue-router'
import { AppButton } from '@escenia/ui'

import { useAuthStore } from '@/stores/auth'
import BrandMark from '@/components/BrandMark.vue'

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
  <div class="layout brand-bg">
    <header class="bar">
      <div class="brand">
        <BrandMark :size="30" />
        <strong>escenia</strong>
        <span class="tag muted">Admin</span>
      </div>

      <div class="bar-right">
        <label v-if="auth.tenants.length" class="tenant">
          <span class="sr-only">Organización</span>
          <select :value="auth.activeTenantId ?? ''" @change="onTenantChange">
            <option v-for="tenant in auth.tenants" :key="tenant.id" :value="tenant.id">
              {{ tenant.name }} · {{ tenant.role }}
            </option>
          </select>
        </label>
        <span class="muted user">{{ auth.user?.email }}</span>
        <AppButton variant="ghost" @click="onLogout">Salir</AppButton>
      </div>
    </header>

    <div class="body">
      <nav class="nav">
        <RouterLink :to="{ name: 'dashboard' }" class="nav-link">
          <span class="dot"></span> Resumen
        </RouterLink>
        <RouterLink :to="{ name: 'events' }" class="nav-link">
          <span class="dot"></span> Eventos
        </RouterLink>
        <RouterLink v-if="auth.canManageMembers" :to="{ name: 'members' }" class="nav-link">
          <span class="dot"></span> Miembros y roles
        </RouterLink>
        <RouterLink v-if="auth.canManageTenant" :to="{ name: 'settings' }" class="nav-link">
          <span class="dot"></span> Configuración
        </RouterLink>
        <RouterLink v-if="auth.isSuperAdmin" :to="{ name: 'system-settings' }" class="nav-link">
          <span class="dot"></span> Sistema
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
  background: rgba(6, 18, 31, 0.7);
  backdrop-filter: blur(14px);
  position: sticky;
  top: 0;
  z-index: 10;
}

.brand {
  display: flex;
  align-items: center;
  gap: 10px;
}

.brand strong {
  font-size: 1.05rem;
  font-weight: 700;
}

.tag {
  font-size: 0.7rem;
  letter-spacing: 0.16em;
  text-transform: uppercase;
}

.bar-right {
  display: flex;
  align-items: center;
  gap: var(--escenia-space-3);
}

.tenant select {
  font: inherit;
  padding: 7px var(--escenia-space-3);
  background: rgba(4, 16, 29, 0.6);
  color: var(--escenia-color-text);
  border: 1px solid var(--escenia-color-border);
  border-radius: var(--escenia-radius-sm);
}

.user {
  font-size: 0.85rem;
}

.body {
  display: flex;
  flex: 1;
  align-items: flex-start;
}

.nav {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: var(--escenia-space-5) var(--escenia-space-4);
  width: 220px;
  min-height: calc(100vh - 61px);
  position: sticky;
  top: 61px;
}

.nav-link {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px var(--escenia-space-3);
  border-radius: var(--escenia-radius-sm);
  color: var(--escenia-color-text-muted);
  text-decoration: none;
  font-size: 0.9rem;
  font-weight: 500;
  transition: all 0.14s ease;
}

.nav-link .dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: var(--escenia-color-border-strong);
  transition: all 0.14s ease;
}

.nav-link:hover {
  color: var(--escenia-color-text);
  background: rgba(255, 255, 255, 0.03);
}

.nav-link.router-link-exact-active {
  color: var(--escenia-color-text);
  background: color-mix(in srgb, var(--escenia-color-primary) 10%, transparent);
  box-shadow: inset 0 0 0 1px var(--escenia-color-border);
}

.nav-link.router-link-exact-active .dot {
  background: var(--escenia-color-primary);
}

.content {
  flex: 1;
  padding: var(--escenia-space-8) var(--escenia-space-8) var(--escenia-space-10);
  max-width: 1080px;
}

@media (max-width: 720px) {
  .body {
    flex-direction: column;
  }
  .nav {
    flex-direction: row;
    flex-wrap: wrap;
    width: 100%;
    min-height: 0;
    position: static;
  }
  .content {
    padding: var(--escenia-space-5);
  }
}
</style>
