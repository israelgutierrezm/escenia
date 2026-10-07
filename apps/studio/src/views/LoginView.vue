<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { AppButton } from '@escenia/ui'
import { ApiError, redirectToSso } from '@escenia/api-client'
import type { SsoDiscovery } from '@escenia/types'

import { api } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

// Vuelta del cierre de sesión único (SAML): el IdP confirmó el cierre o no.
const cierreSso = computed(() => route.query.logout)

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

// SSO: el dominio del correo elige la conexión de la organización.
const conexiones = ref<SsoDiscovery[]>([])
const buscandoSso = ref(false)
const ssoError = ref<string | null>(null)

async function continuarConSso(): Promise<void> {
  ssoError.value = null
  conexiones.value = []

  if (email.value.trim() === '') {
    ssoError.value = 'Escribe tu correo de trabajo para encontrar a tu organización.'
    return
  }

  buscandoSso.value = true

  try {
    const encontradas = (await api.ssoDiscover(email.value.trim())).data
    const unica = encontradas.length === 1 ? encontradas[0] : undefined

    if (unica !== undefined) {
      await iniciarSso(unica)
    } else if (encontradas.length === 0) {
      ssoError.value = 'Tu organización no tiene inicio de sesión con SSO para este dominio.'
    } else {
      conexiones.value = encontradas
    }
  } catch (e) {
    ssoError.value = e instanceof ApiError ? e.message : 'No se pudo iniciar sesión con SSO.'
  } finally {
    buscandoSso.value = false
  }
}

async function iniciarSso(conexion: SsoDiscovery): Promise<void> {
  ssoError.value = null

  try {
    await redirectToSso(api, conexion, window.location.origin, (url) => window.location.assign(url))
  } catch (e) {
    ssoError.value = e instanceof ApiError ? e.message : 'No se pudo iniciar sesión con SSO.'
  }
}
</script>

<template>
  <main class="centro">
    <section class="card panel">
      <div class="marca"><span class="marca__punto"></span><strong>escenia</strong> <span class="muted rol">Studio</span></div>
      <h1>Consola de producción</h1>
      <p class="muted">Inicia sesión como productor para dirigir el evento en vivo.</p>

      <p v-if="cierreSso === 'ok'" class="ok-text" role="status">Cerraste sesión también en tu proveedor de identidad.</p>
      <p v-else-if="cierreSso === 'partial'" class="aviso" role="alert">
        Cerraste sesión en Escenia, pero tu proveedor de identidad no confirmó el cierre. Si el equipo es compartido,
        cierra también tu sesión allí.
      </p>

      <form class="campos" @submit.prevent="entrar">
        <label class="field"><span>Correo electrónico</span><input v-model="email" type="email" class="control" autocomplete="username" required /></label>
        <label class="field"><span>Contraseña</span><input v-model="password" type="password" class="control" autocomplete="current-password" required /></label>
        <p v-if="error" class="error-text" role="alert">{{ error }}</p>
        <AppButton type="submit" :disabled="enviando || !email.trim() || !password.trim()">
          {{ enviando ? 'Entrando…' : 'Entrar' }}
        </AppButton>
      </form>

      <div class="sso">
        <p class="separador" aria-hidden="true"><span>o</span></p>
        <AppButton variant="ghost" :disabled="buscandoSso" @click="continuarConSso">
          {{ buscandoSso ? 'Buscando tu organización…' : 'Continuar con SSO' }}
        </AppButton>
        <div v-if="conexiones.length > 1" class="sso-lista" role="group" aria-label="Elige tu organización">
          <AppButton v-for="c in conexiones" :key="c.id" variant="ghost" @click="iniciarSso(c)">{{ c.display_name }}</AppButton>
        </div>
        <p v-if="ssoError" class="error-text" role="alert">{{ ssoError }}</p>
      </div>
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

.aviso {
  margin: 0;
  color: var(--escenia-color-warning);
  font-size: 0.9rem;
}

.campos {
  display: flex;
  flex-direction: column;
  gap: var(--escenia-space-3);
  margin-top: var(--escenia-space-2);
}

.sso,
.sso-lista {
  display: flex;
  flex-direction: column;
  gap: var(--escenia-space-2);
}

.separador {
  display: flex;
  align-items: center;
  gap: 10px;
  margin: var(--escenia-space-2) 0 0;
  font-size: 0.78rem;
  color: var(--escenia-color-text-muted);
}

.separador::before,
.separador::after {
  content: '';
  flex: 1;
  height: 1px;
  background: var(--escenia-color-border);
}
</style>
