<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { AppButton } from '@escenia/ui'
import { ApiError } from '@escenia/api-client'

import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const auth = useAuthStore()

const email = ref('')
const password = ref('')
const error = ref<string | null>(null)
const enviando = ref(false)

async function entrar(): Promise<void> {
  error.value = null
  enviando.value = true
  try {
    await auth.login(email.value, password.value)
    await router.push({ name: 'events' })
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'No se pudo iniciar sesión.'
  } finally {
    enviando.value = false
  }
}
</script>

<template>
  <main class="centro">
    <section class="card panel">
      <div class="marca"><span class="marca__punto"></span><strong>escenia</strong> <span class="muted rol">Studio</span></div>
      <h1>Consola de producción</h1>
      <p class="muted">Inicia sesión como productor para dirigir el evento en vivo.</p>

      <form class="campos" @submit.prevent="entrar">
        <label class="field"><span>Correo electrónico</span><input v-model="email" type="email" class="control" autocomplete="username" required /></label>
        <label class="field"><span>Contraseña</span><input v-model="password" type="password" class="control" autocomplete="current-password" required /></label>
        <p v-if="error" class="error-text" role="alert">{{ error }}</p>
        <AppButton type="submit" :disabled="enviando || !email.trim() || !password.trim()">
          {{ enviando ? 'Entrando…' : 'Entrar' }}
        </AppButton>
      </form>
    </section>
  </main>
</template>

<style scoped>
.centro {
  min-height: 100vh;
  display: grid;
  place-items: center;
  padding: var(--escenia-space-5);
}

.card {
  width: 100%;
  max-width: 420px;
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
