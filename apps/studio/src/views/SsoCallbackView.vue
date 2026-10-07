<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ApiError, finishSsoLogin } from '@escenia/api-client'

import { api } from '@/lib/api'
import { useAuthStore } from '@/stores/auth'

// Vuelta del inicio de sesión con SSO (misma lógica que el admin, compartida en
// @escenia/api-client): OIDC canjea el código, SAML ya abrió la sesión en el ACS.
const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const error = ref<string | null>(null)

onMounted(async () => {
  const conexion = typeof route.params.connection === 'string' ? route.params.connection : ''

  try {
    await finishSsoLogin(api, conexion, route.query)
    await auth.fetchUser()

    if (!auth.isAuthenticated) {
      throw new Error('sin-sesion')
    }

    await router.replace({ name: 'events' })
  } catch (e) {
    error.value =
      e instanceof ApiError && e.status !== 401
        ? e.message
        : 'No se pudo iniciar sesión con SSO. El intento expiró o no es válido; vuelve a intentarlo.'
  }
})
</script>

<template>
  <main class="centro">
    <section class="card panel">
      <div class="marca"><span class="marca__punto"></span><strong>escenia</strong> <span class="muted rol">Studio</span></div>
      <template v-if="error">
        <p class="error-text" role="alert">{{ error }}</p>
        <RouterLink :to="{ name: 'login' }" class="volver">Volver al inicio de sesión</RouterLink>
      </template>
      <p v-else class="muted" role="status">Completando el inicio de sesión…</p>
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

.volver {
  color: var(--escenia-color-primary);
  font-weight: 600;
  text-decoration: none;
}

.volver:hover {
  text-decoration: underline;
}
</style>
