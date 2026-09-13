<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { AppButton } from '@escenia/ui'
import { ApiError } from '@escenia/api-client'
import type { Question } from '@escenia/types'

import { api } from '@/lib/attendeeApi'

const preguntas = ref<Question[]>([])
const nueva = ref('')
const error = ref<string | null>(null)
const busy = ref(false)

const ordenadas = computed(() => [...preguntas.value].sort((a, b) => b.votes_count - a.votes_count))

async function cargar(): Promise<void> {
  try {
    preguntas.value = (await api.questions()).data
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'No se pudieron cargar las preguntas.'
  }
}

async function preguntar(): Promise<void> {
  if (nueva.value.trim() === '') return
  busy.value = true
  error.value = null
  try {
    await api.askQuestion(nueva.value)
    nueva.value = ''
    await cargar()
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'No se pudo enviar.'
  } finally {
    busy.value = false
  }
}

async function votar(id: string): Promise<void> {
  busy.value = true
  error.value = null
  try {
    await api.voteQuestion(id)
    await cargar()
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'No se pudo votar.'
  } finally {
    busy.value = false
  }
}

onMounted(cargar)
</script>

<template>
  <div class="panel stack">
    <h2>Preguntas y respuestas</h2>
    <div class="add">
      <input v-model="nueva" class="control grow" placeholder="Haz una pregunta…" @keyup.enter="preguntar" />
      <AppButton :disabled="busy || !nueva.trim()" @click="preguntar">Preguntar</AppButton>
    </div>

    <p v-if="preguntas.length === 0" class="empty">Sé el primero en preguntar.</p>
    <ul v-else class="lista">
      <li v-for="q in ordenadas" :key="q.id">
        <button type="button" class="voto" :disabled="busy" :aria-label="`Votar: ${q.body}`" @click="votar(q.id)">
          <span aria-hidden="true">▲</span>
          <span class="voto__n">{{ q.votes_count }}</span>
        </button>
        <div class="qa">
          <p class="qa__body">{{ q.body }}</p>
          <p class="qa__meta muted">
            {{ q.author_name }}
            <span class="chip" :class="q.status === 'answered' ? 'chip--live' : ''">
              {{ q.status === 'answered' ? 'Respondida' : 'Abierta' }}
            </span>
          </p>
          <p v-if="q.answer" class="qa__answer"><strong>Respuesta:</strong> {{ q.answer }}</p>
        </div>
      </li>
    </ul>

    <p v-if="error" class="error-text" role="alert">{{ error }}</p>
  </div>
</template>

<style scoped>
.grow {
  flex: 1;
}

.add {
  display: flex;
  gap: var(--escenia-space-2);
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
  gap: var(--escenia-space-3);
  padding: var(--escenia-space-3);
  border: 1px solid var(--escenia-color-border);
  border-radius: var(--escenia-radius-sm);
  background: rgba(4, 16, 29, 0.35);
}

.voto {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
  min-width: 44px;
  padding: 6px 4px;
  font: inherit;
  color: var(--escenia-color-text-muted);
  background: transparent;
  border: 1px solid var(--escenia-color-border);
  border-radius: var(--escenia-radius-sm);
  cursor: pointer;
  transition: all 0.14s ease;
}

.voto:hover:not(:disabled) {
  color: var(--escenia-color-primary);
  border-color: color-mix(in srgb, var(--escenia-color-primary) 45%, transparent);
}

.voto__n {
  font-weight: 700;
  color: var(--escenia-color-text);
}

.qa {
  flex: 1;
}

.qa__body {
  margin: 0 0 4px;
}

.qa__meta {
  margin: 0;
  font-size: 0.8rem;
  display: flex;
  align-items: center;
  gap: 8px;
}

.qa__answer {
  margin: var(--escenia-space-2) 0 0;
  padding: var(--escenia-space-2) var(--escenia-space-3);
  border-radius: var(--escenia-radius-sm);
  background: color-mix(in srgb, var(--escenia-color-primary) 8%, transparent);
  font-size: 0.9rem;
}
</style>
