<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { AppButton } from '@escenia/ui'

const router = useRouter()
const codigo = ref('')

function entrar(): void {
  const id = codigo.value.trim()
  if (id !== '') router.push({ name: 'register', params: { eventId: id } })
}
</script>

<template>
  <main class="centro">
    <section class="card panel">
      <div class="marca">
        <span class="marca__punto"></span>
        <strong>escenia</strong>
      </div>
      <h1>Únete a tu evento</h1>
      <p class="muted">Introduce el identificador del evento que te compartieron para registrarte y entrar en vivo.</p>

      <div class="entrar">
        <label class="field">
          <span class="sr-only">Identificador del evento</span>
          <input v-model="codigo" class="control" placeholder="Identificador del evento" @keyup.enter="entrar" />
        </label>
        <AppButton :disabled="!codigo.trim()" @click="entrar">Entrar</AppButton>
      </div>

      <RouterLink :to="{ name: 'verify' }" class="verificar muted">Verificar un certificado ›</RouterLink>
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
  text-align: center;
}

.marca {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  font-size: 1.1rem;
  letter-spacing: 0.02em;
}

.marca__punto {
  width: 12px;
  height: 12px;
  border-radius: 50%;
  background: var(--escenia-gradient-brand);
}

h1 {
  margin: var(--escenia-space-2) 0 0;
  font-size: 1.5rem;
}

.entrar {
  display: flex;
  gap: var(--escenia-space-2);
  margin-top: var(--escenia-space-2);
}

.entrar .field {
  flex: 1;
}

.verificar {
  text-decoration: none;
  font-size: 0.85rem;
  margin-top: var(--escenia-space-2);
}

.verificar:hover {
  color: var(--escenia-color-text);
}
</style>
