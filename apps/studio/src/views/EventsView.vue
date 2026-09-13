<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { AppButton } from '@escenia/ui'
import { ApiError } from '@escenia/api-client'
import type { EventModel, EventStatus } from '@escenia/types'

import { api } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'
import { useRouter } from 'vue-router'

const auth = useAuthStore()
const router = useRouter()

const eventos = ref<EventModel[]>([])
const loading = ref(true)
const error = ref<string | null>(null)

const estado: Record<EventStatus, { label: string; clase: string }> = {
  draft: { label: 'Borrador', clase: '' },
  scheduled: { label: 'Programado', clase: 'chip--primary' },
  live: { label: 'En vivo', clase: 'chip--live' },
  ended: { label: 'Finalizado', clase: '' },
  archived: { label: 'Archivado', clase: '' },
  canceled: { label: 'Cancelado', clase: 'chip--danger' },
}

async function load(): Promise<void> {
  loading.value = true
  try {
    eventos.value = (await api.events()).data
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'No se pudieron cargar los eventos.'
  } finally {
    loading.value = false
  }
}

async function salir(): Promise<void> {
  await auth.logout()
  await router.push({ name: 'login' })
}

onMounted(load)
</script>

<template>
  <div class="wrap">
    <header class="bar">
      <div class="marca"><span class="marca__punto"></span><strong>escenia</strong> <span class="muted rol">Studio</span></div>
      <div class="bar__right">
        <span class="muted email">{{ auth.user?.email }}</span>
        <AppButton variant="ghost" @click="salir">Salir</AppButton>
      </div>
    </header>

    <main class="contenido">
      <h1>Elige un evento para dirigir</h1>
      <p v-if="loading" class="muted">Cargando…</p>
      <p v-else-if="error" class="error-text" role="alert">{{ error }}</p>
      <p v-else-if="eventos.length === 0" class="panel empty">No hay eventos en esta organización.</p>

      <ul v-else class="lista">
        <li v-for="e in eventos" :key="e.id">
          <RouterLink :to="{ name: 'console', params: { eventId: e.id } }" class="evento panel">
            <div>
              <strong>{{ e.title }}</strong>
              <p v-if="e.description" class="muted small">{{ e.description }}</p>
            </div>
            <span class="chip" :class="estado[e.status].clase">{{ estado[e.status].label }}</span>
          </RouterLink>
        </li>
      </ul>
    </main>
  </div>
</template>

<style scoped>
.bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: var(--escenia-space-3) var(--escenia-space-5);
  border-bottom: 1px solid var(--escenia-color-border);
  background: rgba(6, 18, 31, 0.7);
  backdrop-filter: blur(14px);
}

.marca {
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.marca__punto {
  width: 11px;
  height: 11px;
  border-radius: 50%;
  background: var(--escenia-gradient-brand);
}

.rol {
  font-size: 0.7rem;
  letter-spacing: 0.14em;
  text-transform: uppercase;
}

.bar__right {
  display: flex;
  align-items: center;
  gap: var(--escenia-space-3);
}

.email {
  font-size: 0.85rem;
}

.contenido {
  max-width: 720px;
  margin: 0 auto;
  padding: var(--escenia-space-8) var(--escenia-space-5);
}

h1 {
  font-size: 1.5rem;
  margin: 0 0 var(--escenia-space-5);
}

.lista {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: var(--escenia-space-3);
}

.small {
  font-size: 0.82rem;
  margin: 4px 0 0;
}

.evento {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--escenia-space-3);
  text-decoration: none;
  color: var(--escenia-color-text);
  transition: border-color 0.15s ease, transform 0.15s ease;
}

.evento:hover {
  border-color: var(--escenia-color-border-strong);
  transform: translateY(-2px);
}
</style>
