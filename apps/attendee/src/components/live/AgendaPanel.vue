<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { AppButton } from '@escenia/ui'
import { ApiError } from '@escenia/api-client'
import type { AgendaSession } from '@escenia/types'

import { api } from '@/lib/attendeeApi'

const sesiones = ref<AgendaSession[]>([])
const mias = ref<Set<string>>(new Set())
const error = ref<string | null>(null)
const busy = ref(false)

const inscrito = computed(() => (id: string) => mias.value.has(id))

async function cargar(): Promise<void> {
  try {
    const [todas, propias] = await Promise.all([api.agenda(), api.myAgenda()])
    sesiones.value = todas.data
    mias.value = new Set(propias.data.map((s) => s.id))
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'No se pudo cargar la agenda.'
  }
}

function hora(iso: string | null): string {
  if (iso === null) return ''
  return new Date(iso).toLocaleTimeString('es', { hour: '2-digit', minute: '2-digit' })
}

async function alternar(s: AgendaSession): Promise<void> {
  busy.value = true
  error.value = null
  try {
    if (mias.value.has(s.id)) await api.unregisterFromSession(s.id)
    else await api.registerForSession(s.id)
    await cargar()
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'No se pudo actualizar tu inscripción.'
  } finally {
    busy.value = false
  }
}

onMounted(cargar)
</script>

<template>
  <div class="panel stack">
    <h2>Agenda</h2>
    <p v-if="sesiones.length === 0" class="empty">Aún no hay sesiones en la agenda.</p>

    <ul v-else class="lista">
      <li v-for="s in sesiones" :key="s.id">
        <div class="info">
          <strong>{{ s.title }}</strong>
          <p class="muted small">
            <template v-if="s.starts_at">{{ hora(s.starts_at) }}<template v-if="s.ends_at">–{{ hora(s.ends_at) }}</template> · </template>
            <template v-if="s.room">{{ s.room }} · </template>
            <template v-if="s.capacity">{{ s.registered_count }}/{{ s.capacity }} inscritos</template>
            <template v-else>{{ s.registered_count }} inscritos</template>
          </p>
        </div>
        <AppButton
          :variant="inscrito(s.id) ? 'ghost' : 'primary'"
          :disabled="busy || (!inscrito(s.id) && !s.has_capacity)"
          @click="alternar(s)"
        >
          {{ inscrito(s.id) ? 'Cancelar' : !s.has_capacity ? 'Lleno' : 'Inscribirme' }}
        </AppButton>
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

.info {
  min-width: 0;
}
</style>
