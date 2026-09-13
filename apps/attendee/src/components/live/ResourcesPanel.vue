<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { AppButton } from '@escenia/ui'
import { ApiError } from '@escenia/api-client'
import type { EventResource } from '@escenia/types'

import { api } from '@/lib/attendeeApi'

const recursos = ref<EventResource[]>([])
const error = ref<string | null>(null)
const busy = ref(false)

async function cargar(): Promise<void> {
  try {
    recursos.value = (await api.resources()).data
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'No se pudieron cargar los recursos.'
  }
}

async function descargar(r: EventResource): Promise<void> {
  busy.value = true
  error.value = null
  try {
    const actualizado = (await api.downloadResource(r.id)).data
    recursos.value = recursos.value.map((x) => (x.id === r.id ? actualizado : x))
    if (r.url) window.open(r.url, '_blank', 'noopener')
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'No se pudo descargar.'
  } finally {
    busy.value = false
  }
}

onMounted(cargar)
</script>

<template>
  <div class="panel stack">
    <h2>Recursos</h2>
    <p v-if="recursos.length === 0" class="empty">Aún no hay recursos disponibles.</p>
    <ul v-else class="lista">
      <li v-for="r in recursos" :key="r.id">
        <div class="info">
          <strong>{{ r.title }}</strong>
          <p class="muted small">{{ r.downloads_count }} descargas</p>
        </div>
        <AppButton :disabled="busy" @click="descargar(r)">Descargar ↓</AppButton>
      </li>
    </ul>
    <p v-if="error" class="error-text" role="alert">{{ error }}</p>
  </div>
</template>

<style scoped>
.small {
  font-size: 0.8rem;
  margin: 4px 0 0;
}

.lista {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: var(--escenia-space-2);
}

.lista li {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--escenia-space-3);
  padding: var(--escenia-space-3);
  border: 1px solid var(--escenia-color-border);
  border-radius: var(--escenia-radius-sm);
  background: rgba(4, 16, 29, 0.35);
}
</style>
