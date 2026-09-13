<script setup lang="ts">
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { AppButton } from '@escenia/ui'
import { ApiError } from '@escenia/api-client'

import { useSpeakerStore } from '@/stores/speaker'

const route = useRoute()
const router = useRouter()
const store = useSpeakerStore()
const token = route.params.token as string

const nombre = ref('')
const error = ref<string | null>(null)
const uniendo = ref(false)

async function unirme(): Promise<void> {
  if (nombre.value.trim() === '') return
  uniendo.value = true
  error.value = null
  try {
    await store.join(token, nombre.value)
    await router.push({ name: 'room', params: { token } })
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'No se pudo unir con este código.'
  } finally {
    uniendo.value = false
  }
}
</script>

<template>
  <main class="centro">
    <section class="card panel">
      <div class="marca"><span class="marca__punto"></span><strong>escenia</strong> <span class="muted rol">Ponente</span></div>
      <h1>Únete a la sala</h1>
      <p class="muted">Escribe tu nombre tal como quieres que aparezca en la transmisión.</p>

      <form class="campos" @submit.prevent="unirme">
        <label class="field">
          <span>Tu nombre</span>
          <input v-model="nombre" class="control" autocomplete="name" required placeholder="Tu nombre" />
        </label>

        <p v-if="error" class="error-text" role="alert">{{ error }}</p>

        <AppButton type="submit" :disabled="uniendo || !nombre.trim()">
          {{ uniendo ? 'Uniéndote…' : 'Entrar a la sala' }}
        </AppButton>
      </form>
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
  max-width: 440px;
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

.rol {
  font-size: 0.72rem;
  letter-spacing: 0.14em;
  text-transform: uppercase;
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
</style>
