<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { ApiError } from '@escenia/api-client'
import type { AttendeeGamification } from '@escenia/types'

import { api } from '@/lib/attendeeApi'
import { useAttendeeStore } from '@/stores/attendee'

const store = useAttendeeStore()
const datos = ref<AttendeeGamification | null>(null)
const error = ref<string | null>(null)

async function cargar(): Promise<void> {
  try {
    datos.value = (await api.gamification()).data
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'No se pudo cargar el ranking.'
  }
}

onMounted(cargar)
</script>

<template>
  <div class="stack">
    <div class="panel puntos">
      <div>
        <p class="muted small">Tus puntos</p>
        <p class="puntos__n">{{ datos?.points ?? 0 }}</p>
      </div>
      <p class="muted small">Gana puntos asistiendo a sesiones y visitando stands.</p>
    </div>

    <div class="panel stack">
      <h2>Ranking de participación</h2>
      <p v-if="(datos?.leaderboard.length ?? 0) === 0" class="empty">Aún no hay puntos registrados.</p>
      <ol v-else class="ranking">
        <li
          v-for="(row, i) in datos?.leaderboard ?? []"
          :key="row.attendee"
          :class="{ yo: row.attendee === store.attendee?.id }"
        >
          <span class="pos" :class="{ top: i < 3 }">{{ i + 1 }}</span>
          <span class="nombre">{{ row.name || 'Asistente' }}<span v-if="row.attendee === store.attendee?.id" class="chip chip--primary tu">Tú</span></span>
          <span class="pts">{{ row.points }} pts</span>
        </li>
      </ol>
    </div>

    <p v-if="error" class="error-text" role="alert">{{ error }}</p>
  </div>
</template>

<style scoped>
.small {
  font-size: 0.8rem;
  margin: 0;
}

.puntos {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--escenia-space-4);
  flex-wrap: wrap;
}

.puntos__n {
  margin: 2px 0 0;
  font-size: 2.4rem;
  font-weight: 800;
  color: var(--escenia-color-primary);
  letter-spacing: -0.02em;
}

.ranking {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: var(--escenia-space-2);
}

.ranking li {
  display: flex;
  align-items: center;
  gap: var(--escenia-space-3);
  padding: 10px 12px;
  border: 1px solid var(--escenia-color-border);
  border-radius: var(--escenia-radius-sm);
  background: rgba(4, 16, 29, 0.35);
}

.ranking li.yo {
  border-color: color-mix(in srgb, var(--escenia-color-primary) 55%, transparent);
  background: color-mix(in srgb, var(--escenia-color-primary) 8%, transparent);
}

.pos {
  display: grid;
  place-items: center;
  width: 28px;
  height: 28px;
  flex-shrink: 0;
  border-radius: 50%;
  background: var(--escenia-color-border);
  color: var(--escenia-color-text-muted);
  font-weight: 700;
  font-size: 0.82rem;
}

.pos.top {
  background: color-mix(in srgb, var(--escenia-color-accent) 80%, transparent);
  color: #04101d;
}

.nombre {
  flex: 1;
}

.tu {
  margin-left: 8px;
}

.pts {
  font-variant-numeric: tabular-nums;
  color: var(--escenia-color-primary);
  font-weight: 600;
}
</style>
