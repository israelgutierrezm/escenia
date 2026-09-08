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
const submitting = ref(false)

async function onSubmit(): Promise<void> {
  error.value = null
  submitting.value = true

  try {
    await auth.login(email.value, password.value)
    await router.push({ name: 'dashboard' })
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'Unable to sign in.'
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <main class="auth">
    <form class="card" @submit.prevent="onSubmit">
      <h1>Escenia Admin</h1>

      <label>
        <span>Email</span>
        <input v-model="email" type="email" autocomplete="username" required />
      </label>

      <label>
        <span>Password</span>
        <input v-model="password" type="password" autocomplete="current-password" required />
      </label>

      <p v-if="error" class="error" role="alert">{{ error }}</p>

      <AppButton type="submit" :disabled="submitting">
        {{ submitting ? 'Signing in…' : 'Sign in' }}
      </AppButton>
    </form>
  </main>
</template>

<style scoped>
.auth {
  display: grid;
  place-items: center;
  min-height: 100vh;
  padding: var(--escenia-space-4);
}

.card {
  display: flex;
  flex-direction: column;
  gap: var(--escenia-space-3);
  width: 100%;
  max-width: 360px;
  padding: var(--escenia-space-6);
  background: var(--escenia-color-surface);
  border: 1px solid var(--escenia-color-border);
  border-radius: var(--escenia-radius-md);
}

label {
  display: flex;
  flex-direction: column;
  gap: var(--escenia-space-1);
  font-size: 0.875rem;
  color: var(--escenia-color-text-muted);
}

input {
  padding: var(--escenia-space-2) var(--escenia-space-3);
  background: var(--escenia-color-bg);
  border: 1px solid var(--escenia-color-border);
  border-radius: var(--escenia-radius-sm);
  color: var(--escenia-color-text);
}

.error {
  margin: 0;
  color: var(--escenia-color-danger);
  font-size: 0.875rem;
}
</style>
