<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { AppButton } from '@escenia/ui'
import { ApiError } from '@escenia/api-client'
import type { Attribution, RegistrationForm } from '@escenia/types'

import { api } from '@/lib/attendeeApi'
import { useAttendeeStore } from '@/stores/attendee'

const route = useRoute()
const router = useRouter()
const store = useAttendeeStore()
const eventId = route.params.eventId as string

const loading = ref(true)
const error = ref<string | null>(null)
const enviando = ref(false)

const eventTitle = ref('')
const form = ref<RegistrationForm | null>(null)
const nombre = ref('')
const correo = ref('')
const textos = ref<Record<string, string>>({})
const marcas = ref<Record<string, boolean>>({})

function attribution(): Attribution {
  const params = new URLSearchParams(window.location.search)
  const attr: Attribution = { landing_path: window.location.pathname }
  if (document.referrer) attr.referrer = document.referrer
  for (const key of ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'] as const) {
    const v = params.get(key)
    if (v) attr[key] = v
  }
  return attr
}

function message(e: unknown): string {
  return e instanceof ApiError ? e.message : 'Algo salió mal.'
}

async function load(): Promise<void> {
  loading.value = true
  error.value = null
  try {
    const res = (await api.registration(eventId)).data
    eventTitle.value = res.event.title
    form.value = res.form
    for (const f of res.form.fields) {
      if (f.type === 'checkbox') marcas.value[f.key] = false
      else textos.value[f.key] = ''
    }
  } catch (e) {
    error.value = message(e)
  } finally {
    loading.value = false
  }
}

async function registrar(): Promise<void> {
  if (nombre.value.trim() === '' || correo.value.trim() === '') return
  enviando.value = true
  error.value = null
  try {
    const res = (await api.register(eventId, {
      name: nombre.value,
      email: correo.value,
      answers: { ...textos.value, ...marcas.value },
      attribution: attribution(),
    })).data
    store.setSession(eventId, { token: res.token, attendee: res.attendee })
    await router.push({ name: 'live', params: { eventId } })
  } catch (e) {
    error.value = message(e)
  } finally {
    enviando.value = false
  }
}

onMounted(load)
</script>

<template>
  <main class="centro">
    <section class="card panel">
      <p v-if="loading" class="muted">Cargando…</p>

      <template v-else-if="form">
        <div class="marca"><span class="marca__punto"></span><strong>escenia</strong></div>
        <h1>{{ eventTitle }}</h1>

        <template v-if="form.is_open">
          <p class="muted">Regístrate para entrar al evento en vivo.</p>

          <form class="campos" @submit.prevent="registrar">
            <label class="field">
              <span>Nombre</span>
              <input v-model="nombre" class="control" autocomplete="name" required placeholder="Tu nombre" />
            </label>
            <label class="field">
              <span>Correo electrónico</span>
              <input v-model="correo" type="email" class="control" autocomplete="email" required placeholder="tu@correo.com" />
            </label>

            <template v-for="f in form.fields" :key="f.key">
              <label v-if="f.type === 'checkbox'" class="check">
                <input v-model="marcas[f.key]" type="checkbox" />
                <span>{{ f.label }}</span>
              </label>
              <label v-else class="field">
                <span>{{ f.label }}<em v-if="f.required" aria-hidden="true"> *</em></span>
                <textarea v-if="f.type === 'textarea'" v-model="textos[f.key]" class="control" rows="3"></textarea>
                <select v-else-if="f.type === 'select'" v-model="textos[f.key]" class="control">
                  <option value="">Selecciona…</option>
                  <option v-for="opt in f.options ?? []" :key="opt" :value="opt">{{ opt }}</option>
                </select>
                <input v-else v-model="textos[f.key]" :type="f.type === 'email' ? 'email' : 'text'" class="control" />
              </label>
            </template>

            <p v-if="error" class="error-text" role="alert">{{ error }}</p>

            <AppButton type="submit" :disabled="enviando || !nombre.trim() || !correo.trim()">
              {{ enviando ? 'Entrando…' : 'Registrarme y entrar' }}
            </AppButton>
          </form>
        </template>

        <p v-else class="muted cerrado">El registro para este evento está cerrado.</p>
      </template>

      <p v-else class="error-text" role="alert">{{ error }}</p>
    </section>
  </main>
</template>

<style scoped>
.centro {
  min-height: 100dvh;
  display: grid;
  place-items: center;
  padding: var(--escenia-space-5);
}

.card {
  width: 100%;
  max-width: 460px;
  display: flex;
  flex-direction: column;
  gap: var(--escenia-space-3);
}

.marca {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  font-size: 1rem;
}

.marca__punto {
  width: 11px;
  height: 11px;
  border-radius: 50%;
  background: var(--escenia-gradient-brand);
}

h1 {
  margin: 0;
  font-size: 1.4rem;
}

.campos {
  display: flex;
  flex-direction: column;
  gap: var(--escenia-space-3);
  margin-top: var(--escenia-space-2);
}

.check {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 0.9rem;
}

.check input {
  accent-color: var(--escenia-color-accent);
}

.field em {
  color: var(--escenia-color-primary);
  font-style: normal;
}

.cerrado {
  text-align: center;
  padding: var(--escenia-space-4) 0;
}
</style>
