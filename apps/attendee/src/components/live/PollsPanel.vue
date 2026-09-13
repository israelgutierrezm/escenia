<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { AppButton } from '@escenia/ui'
import { ApiError } from '@escenia/api-client'
import type { Poll } from '@escenia/types'

import { api } from '@/lib/attendeeApi'

const polls = ref<Poll[]>([])
const seleccion = ref<Record<string, string>>({})
const votadas = ref<Set<string>>(new Set())
const error = ref<string | null>(null)
const busy = ref(false)

async function cargar(): Promise<void> {
  try {
    polls.value = (await api.polls()).data
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'No se pudieron cargar las encuestas.'
  }
}

function total(poll: Poll): number {
  return (poll.options ?? []).reduce((s, o) => s + o.votes_count, 0)
}

function pct(votes: number, poll: Poll): number {
  const t = total(poll)
  return t === 0 ? 0 : Math.round((votes / t) * 100)
}

function mostrarResultados(poll: Poll): boolean {
  return poll.status === 'closed' || votadas.value.has(poll.id)
}

async function votar(poll: Poll): Promise<void> {
  const optionId = seleccion.value[poll.id]
  if (optionId === undefined || optionId === '') return
  busy.value = true
  error.value = null
  try {
    await api.votePoll(poll.id, optionId)
    votadas.value.add(poll.id)
    await cargar()
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'No se pudo registrar tu voto.'
  } finally {
    busy.value = false
  }
}

onMounted(cargar)
</script>

<template>
  <div class="stack">
    <p v-if="polls.length === 0" class="panel empty">No hay encuestas activas por ahora.</p>

    <div v-for="poll in polls" :key="poll.id" class="panel stack">
      <div class="cab">
        <strong>{{ poll.question }}</strong>
        <span class="chip" :class="poll.status === 'open' ? 'chip--live' : ''">
          {{ poll.status === 'open' ? 'Abierta' : poll.status === 'closed' ? 'Cerrada' : 'Borrador' }}
        </span>
      </div>

      <template v-if="mostrarResultados(poll)">
        <div v-for="o in poll.options ?? []" :key="o.id" class="res">
          <div class="res__bar" :style="{ width: `${pct(o.votes_count, poll)}%` }"></div>
          <span class="res__label">{{ o.label }}</span>
          <span class="res__pct">{{ pct(o.votes_count, poll) }}%</span>
        </div>
        <p class="muted small">{{ total(poll) }} voto(s)</p>
      </template>

      <template v-else>
        <label v-for="o in poll.options ?? []" :key="o.id" class="opt">
          <input v-model="seleccion[poll.id]" type="radio" :name="`poll-${poll.id}`" :value="o.id" />
          <span>{{ o.label }}</span>
        </label>
        <div class="actions">
          <AppButton :disabled="busy || !seleccion[poll.id]" @click="votar(poll)">Votar</AppButton>
        </div>
      </template>
    </div>

    <p v-if="error" class="error-text" role="alert">{{ error }}</p>
  </div>
</template>

<style scoped>
.cab {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--escenia-space-2);
}

.small {
  font-size: 0.8rem;
  margin: 4px 0 0;
}

.opt {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  border: 1px solid var(--escenia-color-border);
  border-radius: var(--escenia-radius-sm);
  background: rgba(4, 16, 29, 0.3);
  cursor: pointer;
}

.opt input {
  accent-color: var(--escenia-color-primary);
}

.res {
  position: relative;
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  border: 1px solid var(--escenia-color-border);
  border-radius: var(--escenia-radius-sm);
  overflow: hidden;
}

.res__bar {
  position: absolute;
  inset: 0 auto 0 0;
  background: color-mix(in srgb, var(--escenia-color-primary) 20%, transparent);
  transition: width 0.4s ease;
}

.res__label {
  position: relative;
  flex: 1;
}

.res__pct {
  position: relative;
  font-variant-numeric: tabular-nums;
  font-weight: 600;
}
</style>
