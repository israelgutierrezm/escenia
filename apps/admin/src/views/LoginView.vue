<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ApiError, redirectToSso } from '@escenia/api-client'
import type { SsoDiscovery } from '@escenia/types'

import { api } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'
import AuthWaves from '@/components/AuthWaves.vue'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

// Vuelta del cierre de sesión único (SAML): el IdP confirmó el cierre o no.
const cierreSso = computed(() => route.query.logout)

const email = ref('')
const password = ref('')
const verClave = ref(false)
const error = ref<string | null>(null)
const enviando = ref(false)

async function onSubmit(): Promise<void> {
  error.value = null
  enviando.value = true

  try {
    await auth.login(email.value, password.value)
    await router.push({ name: 'dashboard' })
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
  <AuthWaves>
    <template #subtitulo>
      <p class="sub muted">Panel de administración</p>
    </template>

    <p v-if="cierreSso === 'ok'" class="ok-text cierre" role="status">
      Cerraste sesión también en tu proveedor de identidad.
    </p>
    <p v-else-if="cierreSso === 'partial'" class="aviso cierre" role="alert">
      Cerraste sesión en Escenia, pero tu proveedor de identidad no confirmó el cierre. Si el equipo es compartido,
      cierra también tu sesión allí.
    </p>

    <form class="fields" @submit.prevent="onSubmit">
      <div class="campo">
        <input
          id="email"
          v-model="email"
          type="email"
          autocomplete="username"
          required
          placeholder=" "
          class="entrada"
        />
        <label for="email" class="etiqueta">Correo electrónico</label>
      </div>

      <div class="campo">
        <input
          id="password"
          v-model="password"
          :type="verClave ? 'text' : 'password'"
          autocomplete="current-password"
          required
          placeholder=" "
          class="entrada"
        />
        <label for="password" class="etiqueta">Contraseña</label>
        <button
          type="button"
          class="ojo"
          :aria-label="verClave ? 'Ocultar contraseña' : 'Ver contraseña'"
          @click="verClave = !verClave"
        >
          <svg v-if="!verClave" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.04 12.32a1 1 0 0 1 0-.64C3.42 7.51 7.36 4.5 12 4.5s8.57 3.01 9.96 7.18a1 1 0 0 1 0 .64C20.58 16.49 16.64 19.5 12 19.5s-8.57-3.01-9.96-7.18Z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
          </svg>
          <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.22A10.48 10.48 0 0 0 1.93 12c1.3 4.34 5.31 7.5 10.07 7.5 1 0 1.95-.14 2.86-.4M6.23 6.23A10.45 10.45 0 0 1 12 4.5c4.76 0 8.77 3.16 10.07 7.5a10.52 10.52 0 0 1-4.3 5.77M6.23 6.23 3 3m3.23 3.23 3.65 3.65m7.89 7.89L21 21m-3.23-3.23-3.65-3.65" />
          </svg>
        </button>
      </div>

      <p v-if="error" class="error-text" role="alert">{{ error }}</p>

      <button type="submit" :disabled="enviando" class="entrar grupo">
        <span>{{ enviando ? 'Entrando…' : 'Iniciar sesión' }}</span>
        <span v-if="!enviando" class="flechas" aria-hidden="true">
          <svg v-for="n in 3" :key="n" class="chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6">
            <path stroke-linecap="round" stroke-linejoin="round" d="m9 6 6 6-6 6" />
          </svg>
        </span>
      </button>
    </form>

    <div class="sso">
      <p class="separador" aria-hidden="true"><span>o</span></p>
      <button type="button" class="sso-btn" :disabled="buscandoSso" @click="continuarConSso">
        {{ buscandoSso ? 'Buscando tu organización…' : 'Continuar con SSO' }}
      </button>
      <ul v-if="conexiones.length > 1" class="sso-lista" aria-label="Elige tu organización">
        <li v-for="c in conexiones" :key="c.id">
          <button type="button" class="sso-btn" @click="iniciarSso(c)">{{ c.display_name }}</button>
        </li>
      </ul>
      <p v-if="ssoError" class="error-text" role="alert">{{ ssoError }}</p>
    </div>
  </AuthWaves>
</template>

<style scoped>
.sub {
  margin: 6px 0 0;
  font-size: 0.85rem;
}

.cierre {
  margin: 0 0 var(--escenia-space-4);
}

.aviso {
  color: var(--escenia-color-warning);
  font-size: 0.875rem;
}

.fields {
  display: flex;
  flex-direction: column;
  gap: var(--escenia-space-4);
}

.campo {
  position: relative;
}

.entrada {
  width: 100%;
  border: 1px solid var(--escenia-color-border);
  border-radius: var(--escenia-radius-sm);
  background: rgba(4, 16, 29, 0.55);
  padding: 1.15rem 0.85rem 0.5rem;
  font: inherit;
  font-size: 0.95rem;
  color: var(--escenia-color-text);
  transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.entrada:focus {
  outline: none;
  border-color: var(--escenia-color-primary);
  box-shadow: var(--escenia-ring);
}

.etiqueta {
  position: absolute;
  left: 0.9rem;
  top: 0.85rem;
  color: var(--escenia-color-text-muted);
  font-size: 0.95rem;
  pointer-events: none;
  transform-origin: left top;
  transition: all 0.18s ease;
}

.entrada:focus + .etiqueta,
.entrada:not(:placeholder-shown) + .etiqueta {
  top: 0.34rem;
  font-size: 0.68rem;
  color: var(--escenia-color-primary);
  font-weight: 600;
}

.ojo {
  position: absolute;
  right: 0.55rem;
  top: 0.7rem;
  padding: 4px;
  background: transparent;
  border: 0;
  color: var(--escenia-color-text-muted);
  cursor: pointer;
  transition: color 0.2s ease;
}

.ojo svg {
  width: 20px;
  height: 20px;
}

.ojo:hover {
  color: var(--escenia-color-primary);
}

.entrar {
  display: flex;
  width: 100%;
  align-items: center;
  justify-content: center;
  gap: 10px;
  padding: 0.7rem 1rem;
  font: inherit;
  font-weight: 600;
  color: #ffffff;
  background: var(--escenia-color-primary);
  border: 0;
  border-radius: var(--escenia-radius-sm);
  cursor: pointer;
  transition: filter 0.16s ease;
}

.entrar:hover:not(:disabled) {
  filter: brightness(1.08);
}

.entrar:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.flechas {
  position: relative;
  display: inline-flex;
  width: 1.1rem;
  height: 1.1rem;
}

.chev {
  position: absolute;
  inset: 0;
  width: 1.1rem;
  height: 1.1rem;
  opacity: 0;
}

.chev:first-child {
  opacity: 1;
}

.grupo:hover:not(:disabled) .chev {
  animation: fluir 0.9s ease-in-out infinite;
}

.grupo:hover:not(:disabled) .chev:nth-child(2) {
  animation-delay: 0.2s;
}

.grupo:hover:not(:disabled) .chev:nth-child(3) {
  animation-delay: 0.4s;
}

@keyframes fluir {
  0% {
    opacity: 0;
    transform: translateX(-6px);
  }
  35% {
    opacity: 1;
  }
  100% {
    opacity: 0;
    transform: translateX(9px);
  }
}

@media (prefers-reduced-motion: reduce) {
  .grupo:hover:not(:disabled) .chev {
    animation: none;
  }
}

.sso {
  display: flex;
  flex-direction: column;
  gap: var(--escenia-space-3);
  margin-top: var(--escenia-space-4);
}

.separador {
  display: flex;
  align-items: center;
  gap: 10px;
  margin: 0;
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

.sso-btn {
  width: 100%;
  padding: 0.65rem 1rem;
  font: inherit;
  font-weight: 600;
  color: var(--escenia-color-text);
  background: transparent;
  border: 1px solid var(--escenia-color-border-strong);
  border-radius: var(--escenia-radius-sm);
  cursor: pointer;
  transition: border-color 0.16s ease, color 0.16s ease;
}

.sso-btn:hover:not(:disabled) {
  border-color: var(--escenia-color-primary);
  color: var(--escenia-color-primary);
}

.sso-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.sso-lista {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: var(--escenia-space-2);
}
</style>
