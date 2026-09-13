<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { ApiError } from '@escenia/api-client'
import type { Workspace } from '@escenia/types'

import { useAuthStore } from '@/stores/auth'
import { api } from '@/lib/api'

const auth = useAuthStore()

const workspaces = ref<Workspace[]>([])
const loading = ref(true)
const error = ref<string | null>(null)

const accessLevel = computed(() => {
  if (auth.isSuperAdmin) return 'Plataforma'
  if (auth.currentRole === 'owner') return 'Propietario'
  if (auth.currentRole === 'admin') return 'Administrador'
  return 'Miembro'
})

const roleLabel = computed(() => {
  const map: Record<string, string> = { owner: 'Propietario', admin: 'Administrador', member: 'Miembro' }
  return auth.currentRole ? (map[auth.currentRole] ?? auth.currentRole) : '—'
})

onMounted(async () => {
  try {
    if (auth.activeTenant) {
      workspaces.value = (await api.workspaces()).data
    }
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'No se pudieron cargar los espacios.'
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <section class="stack">
    <div class="page-head">
      <h1>Hola, <span class="brand-text">{{ auth.user?.name }}</span></h1>
      <p>Este es tu panel de <strong>{{ auth.activeTenant?.name ?? 'Escenia' }}</strong>.</p>
    </div>

    <div class="stat-grid">
      <div class="stat">
        <div class="stat__value">{{ auth.activeTenant?.name ?? '—' }}</div>
        <div class="stat__label">Organización</div>
      </div>
      <div class="stat">
        <div class="stat__value">{{ roleLabel }}</div>
        <div class="stat__label">Tu rol</div>
      </div>
      <div class="stat">
        <div class="stat__value">{{ loading ? '…' : workspaces.length }}</div>
        <div class="stat__label">Espacios de trabajo</div>
      </div>
      <div class="stat">
        <div class="stat__value">{{ accessLevel }}</div>
        <div class="stat__label">Nivel de acceso</div>
      </div>
    </div>

    <div class="panel">
      <h2>Espacios de trabajo</h2>
      <p v-if="loading" class="muted">Cargando…</p>
      <p v-else-if="error" role="alert" class="error-text">{{ error }}</p>
      <p v-else-if="workspaces.length === 0" class="muted">Aún no hay espacios de trabajo.</p>
      <ul v-else class="ws-list">
        <li v-for="workspace in workspaces" :key="workspace.id">
          <span class="ws-dot"></span>
          <strong>{{ workspace.name }}</strong>
          <span class="muted">/{{ workspace.slug }}</span>
        </li>
      </ul>
    </div>

    <div v-if="!auth.canManageMembers" class="panel note">
      <h2>Acceso de visualización</h2>
      <p class="muted">
        Tu rol actual es de <strong>{{ roleLabel.toLowerCase() }}</strong>. Puedes consultar tu
        organización y espacios de trabajo. Si necesitas gestionar miembros, roles o la
        configuración, pídele a un administrador que amplíe tus permisos.
      </p>
    </div>
  </section>
</template>

<style scoped>
.ws-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: var(--escenia-space-2);
}

.ws-list li {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px var(--escenia-space-3);
  border: 1px solid var(--escenia-color-border);
  border-radius: var(--escenia-radius-sm);
  background: rgba(4, 16, 29, 0.35);
}

.ws-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: var(--escenia-color-primary);
}

.note {
  border-color: color-mix(in srgb, var(--escenia-color-primary) 30%, transparent);
}
</style>
