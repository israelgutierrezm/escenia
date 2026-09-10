<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { ApiError } from '@escenia/api-client'
import type { AnalyticsSummary, AttendanceTimeline, AttributionReport, EngagementReport } from '@escenia/types'

import { api } from '@/lib/api'

const route = useRoute()
const id = route.params.id as string

const summary = ref<AnalyticsSummary | null>(null)
const attendance = ref<AttendanceTimeline | null>(null)
const engagement = ref<EngagementReport | null>(null)
const attribution = ref<AttributionReport | null>(null)
const loading = ref(true)
const error = ref<string | null>(null)

const maxConcurrent = computed(() =>
  Math.max(1, ...(attendance.value?.points ?? []).map((p) => p.concurrent)),
)

const engagementCards = computed(() => {
  const t = engagement.value?.totals
  if (!t) return []
  return [
    { label: 'Mensajes de chat', value: t.chat_messages },
    { label: 'Preguntas', value: t.questions_asked },
    { label: 'Votos a preguntas', value: t.question_votes },
    { label: 'Votos en encuestas', value: t.poll_votes },
    { label: 'Descargas de recursos', value: t.resource_downloads },
  ]
})

function pct(rate: number): string {
  return `${Math.round(rate * 100)}%`
}

function hora(iso: string): string {
  return new Date(iso).toLocaleTimeString('es', { hour: '2-digit', minute: '2-digit' })
}

async function load(): Promise<void> {
  loading.value = true
  error.value = null
  try {
    const [s, a, e, at] = await Promise.all([
      api.analyticsSummary(id),
      api.analyticsAttendance(id),
      api.analyticsEngagement(id),
      api.analyticsAttribution(id),
    ])
    summary.value = s.data
    attendance.value = a.data
    engagement.value = e.data
    attribution.value = at.data
  } catch (err) {
    error.value = err instanceof ApiError ? err.message : 'No se pudieron cargar las analíticas.'
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <section class="stack">
    <RouterLink :to="{ name: 'event-detail', params: { id } }" class="back muted">‹ Volver al evento</RouterLink>

    <div class="page-head">
      <h1>Analíticas</h1>
      <p>Registro, asistencia y engagement del evento.</p>
    </div>

    <p v-if="loading" class="muted">Cargando…</p>
    <p v-else-if="error" class="error-text">{{ error }}</p>

    <template v-else-if="summary">
      <div class="stat-grid">
        <div class="stat">
          <div class="stat__value">{{ summary.registrations }}</div>
          <div class="stat__label">Registros</div>
        </div>
        <div class="stat">
          <div class="stat__value">{{ summary.attended_attendees }}</div>
          <div class="stat__label">Asistentes</div>
        </div>
        <div class="stat">
          <div class="stat__value">{{ pct(summary.attendance_rate) }}</div>
          <div class="stat__label">Tasa de asistencia</div>
        </div>
        <div class="stat">
          <div class="stat__value">{{ summary.peak_concurrent }}</div>
          <div class="stat__label">Pico de concurrencia</div>
        </div>
        <div class="stat">
          <div class="stat__value">{{ summary.avg_watch_minutes }}<small> min</small></div>
          <div class="stat__label">Watch-time medio</div>
        </div>
      </div>

      <div class="panel">
        <h2>Asistencia en el tiempo</h2>
        <p v-if="(attendance?.points.length ?? 0) === 0" class="empty">Sin datos de asistencia todavía.</p>
        <div v-else class="chart">
          <div
            v-for="(p, i) in attendance!.points"
            :key="i"
            class="bar"
            :style="{ height: `${(p.concurrent / maxConcurrent) * 100}%` }"
            :title="`${hora(p.t)} · ${p.concurrent} concurrentes`"
          ></div>
        </div>
        <div v-if="(attendance?.points.length ?? 0) > 0" class="chart-axis muted">
          <span>{{ hora(attendance!.points[0]!.t) }}</span>
          <span>Pico: {{ maxConcurrent }}</span>
          <span>{{ hora(attendance!.points[attendance!.points.length - 1]!.t) }}</span>
        </div>
      </div>

      <div class="panel">
        <h2>Engagement</h2>
        <div class="mini-grid">
          <div v-for="c in engagementCards" :key="c.label" class="mini">
            <div class="mini__value">{{ c.value }}</div>
            <div class="muted">{{ c.label }}</div>
          </div>
        </div>
      </div>

      <div class="panel">
        <h2>Atribución</h2>
        <p v-if="(attribution?.sources.length ?? 0) === 0" class="empty">Sin datos de atribución.</p>
        <div v-else class="table-wrap">
          <table class="admin-table">
            <thead>
              <tr><th>Fuente</th><th>Registros</th><th>Asistieron</th></tr>
            </thead>
            <tbody>
              <tr v-for="src in attribution!.sources" :key="src.source">
                <td><strong>{{ src.source }}</strong></td>
                <td class="muted">{{ src.registrations }}</td>
                <td class="muted">{{ src.attended }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>
  </section>
</template>

<style scoped>
.back {
  text-decoration: none;
  font-size: 0.85rem;
}

.back:hover {
  color: var(--escenia-color-text);
}

.stat__value small {
  font-size: 0.9rem;
  color: var(--escenia-color-text-muted);
}

.chart {
  display: flex;
  align-items: flex-end;
  gap: 3px;
  height: 160px;
  padding-top: var(--escenia-space-2);
}

.bar {
  flex: 1;
  min-width: 3px;
  min-height: 2px;
  border-radius: 3px 3px 0 0;
  background: var(--escenia-color-primary);
  opacity: 0.85;
  transition: opacity 0.15s ease;
}

.bar:hover {
  opacity: 1;
}

.chart-axis {
  display: flex;
  justify-content: space-between;
  font-size: 0.75rem;
  margin-top: var(--escenia-space-2);
}

.mini-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
  gap: var(--escenia-space-3);
}

.mini {
  border: 1px solid var(--escenia-color-border);
  border-radius: var(--escenia-radius-md);
  padding: var(--escenia-space-4);
  background: rgba(4, 16, 29, 0.35);
}

.mini__value {
  font-size: 1.6rem;
  font-weight: 700;
}
</style>
