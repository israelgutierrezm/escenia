<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ApiError, finishSsoLogin } from '@escenia/api-client'

import { api } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'
import AuthWaves from '@/components/AuthWaves.vue'

// Vuelta del inicio de sesión con SSO. OIDC llega con `code` + `state` y la SPA
// canjea el código (la cookie de enlace viaja sola); SAML ya inició la sesión en
// el ACS y llega con `status=ok`. Cualquier `error` del IdP o del ACS se muestra.
const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const error = ref<string | null>(null)

function texto(valor: unknown): string {
  return typeof valor === 'string' ? valor : ''
}

onMounted(async () => {
  try {
    await finishSsoLogin(api, texto(route.params.connection), route.query)
    await auth.fetchUser()

    if (!auth.isAuthenticated) {
      throw new Error('sin-sesion')
    }

    await router.replace({ name: 'dashboard' })
  } catch (e) {
    error.value =
      e instanceof ApiError && e.status !== 401
        ? e.message
        : 'No se pudo iniciar sesión con SSO. El intento expiró o no es válido; vuelve a intentarlo.'
  }
})
</script>

<template>
  <AuthWaves>
    <template #subtitulo>
      <p class="sub muted">Inicio de sesión con SSO</p>
    </template>

    <div class="estado">
      <template v-if="error">
        <p class="error-text" role="alert">{{ error }}</p>
        <RouterLink :to="{ name: 'login' }" class="volver">Volver al inicio de sesión</RouterLink>
      </template>
      <p v-else class="muted" role="status">
        <span class="spinner" aria-hidden="true"></span>
        Completando el inicio de sesión…
      </p>
    </div>
  </AuthWaves>
</template>

<style scoped>
.sub {
  margin: 6px 0 0;
  font-size: 0.85rem;
}

.estado {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--escenia-space-3);
  text-align: center;
  padding: var(--escenia-space-4) 0;
}

.estado p {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  margin: 0;
}

.volver {
  color: var(--escenia-color-primary);
  font-weight: 600;
  text-decoration: none;
}

.volver:hover {
  text-decoration: underline;
}

.spinner {
  width: 16px;
  height: 16px;
  border-radius: 50%;
  border: 2px solid var(--escenia-color-border);
  border-top-color: var(--escenia-color-primary);
  animation: girar 0.8s linear infinite;
}

@keyframes girar {
  to {
    transform: rotate(360deg);
  }
}

@media (prefers-reduced-motion: reduce) {
  .spinner {
    animation: none;
  }
}
</style>
