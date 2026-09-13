<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { AppButton } from '@escenia/ui'
import { ApiError } from '@escenia/api-client'
import type { Assessment, AssessmentSubmission } from '@escenia/types'

import { api } from '@/lib/attendeeApi'

const assessment = ref<Assessment | null>(null)
const respuestas = ref<Record<string, string[]>>({})
const resultado = ref<AssessmentSubmission | null>(null)
const loading = ref(true)
const error = ref<string | null>(null)
const enviando = ref(false)

async function cargar(): Promise<void> {
  loading.value = true
  try {
    assessment.value = (await api.assessment()).data
    if (assessment.value) {
      for (const q of assessment.value.questions) respuestas.value[q.id] = []
    }
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'No se pudo cargar la evaluación.'
  } finally {
    loading.value = false
  }
}

function elegido(qid: string, key: string): boolean {
  return (respuestas.value[qid] ?? []).includes(key)
}

function elegirUnica(qid: string, key: string): void {
  respuestas.value[qid] = [key]
}

function alternarMultiple(qid: string, key: string): void {
  const actual = respuestas.value[qid] ?? []
  respuestas.value[qid] = actual.includes(key) ? actual.filter((k) => k !== key) : [...actual, key]
}

async function enviar(): Promise<void> {
  if (assessment.value === null) return
  enviando.value = true
  error.value = null
  try {
    const answers: Record<string, string | string[]> = {}
    for (const q of assessment.value.questions) {
      const seleccion = respuestas.value[q.id] ?? []
      answers[q.id] = q.type === 'multiple_choice' ? seleccion : (seleccion[0] ?? '')
    }
    resultado.value = (await api.submitAssessment(answers)).data
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'No se pudo enviar la evaluación.'
  } finally {
    enviando.value = false
  }
}

onMounted(cargar)
</script>

<template>
  <div class="panel stack">
    <h2>Evaluación</h2>

    <p v-if="loading" class="muted">Cargando…</p>
    <p v-else-if="assessment === null" class="empty">Este evento no tiene evaluación.</p>

    <template v-else>
      <div v-if="resultado" class="resultado" :class="resultado.passed ? 'ok' : 'no'" role="status">
        <p class="res-estado">{{ resultado.passed ? '✓ ¡Aprobaste!' : 'No alcanzaste el puntaje' }}</p>
        <p class="res-score">{{ resultado.score }}%</p>
        <p class="muted small">Puntaje para aprobar: {{ assessment.passing_score }}%</p>
      </div>

      <template v-else>
        <p class="muted">{{ assessment.title }} · aprobar con {{ assessment.passing_score }}%</p>

        <div v-for="(q, qi) in assessment.questions" :key="q.id" class="pregunta">
          <p class="pregunta__prompt"><strong>{{ qi + 1 }}.</strong> {{ q.prompt }}</p>
          <p v-if="q.type === 'multiple_choice'" class="muted small">Selecciona todas las que apliquen.</p>
          <label v-for="o in q.options" :key="o.key" class="opt">
            <input
              v-if="q.type === 'multiple_choice'"
              type="checkbox"
              :checked="elegido(q.id, o.key)"
              @change="alternarMultiple(q.id, o.key)"
            />
            <input
              v-else
              type="radio"
              :name="`q-${q.id}`"
              :checked="elegido(q.id, o.key)"
              @change="elegirUnica(q.id, o.key)"
            />
            <span>{{ o.label }}</span>
          </label>
        </div>

        <p v-if="error" class="error-text" role="alert">{{ error }}</p>
        <div class="actions">
          <AppButton :disabled="enviando" @click="enviar">Enviar respuestas</AppButton>
        </div>
      </template>
    </template>
  </div>
</template>

<style scoped>
.small {
  font-size: 0.8rem;
  margin: 2px 0 0;
}

.pregunta {
  border-top: 1px solid var(--escenia-color-border);
  padding-top: var(--escenia-space-3);
  display: flex;
  flex-direction: column;
  gap: var(--escenia-space-2);
}

.pregunta__prompt {
  margin: 0;
}

.opt {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 9px 12px;
  border: 1px solid var(--escenia-color-border);
  border-radius: var(--escenia-radius-sm);
  background: rgba(4, 16, 29, 0.3);
  cursor: pointer;
  font-size: 0.9rem;
}

.opt input {
  accent-color: var(--escenia-color-primary);
}

.resultado {
  text-align: center;
  padding: var(--escenia-space-5);
  border: 1px solid var(--escenia-color-border);
  border-radius: var(--escenia-radius-md);
  background: rgba(4, 16, 29, 0.4);
}

.resultado.ok {
  border-color: color-mix(in srgb, var(--escenia-color-accent) 50%, transparent);
}

.resultado.no {
  border-color: color-mix(in srgb, var(--escenia-color-danger) 45%, transparent);
}

.res-estado {
  margin: 0;
  font-weight: 700;
  color: var(--escenia-color-accent);
}

.resultado.no .res-estado {
  color: var(--escenia-color-danger);
}

.res-score {
  margin: var(--escenia-space-2) 0 0;
  font-size: 2.4rem;
  font-weight: 800;
  letter-spacing: -0.02em;
}
</style>
