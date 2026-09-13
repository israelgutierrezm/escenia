<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { AppButton } from '@escenia/ui'

const router = useRouter()
const codigo = ref('')

function continuar(): void {
  const token = codigo.value.trim()
  if (token !== '') router.push({ name: 'join', params: { token } })
}
</script>

<template>
  <main class="centro">
    <section class="card panel">
      <div class="marca"><span class="marca__punto"></span><strong>escenia</strong> <span class="muted rol">Ponente</span></div>
      <h1>Únete como ponente</h1>
      <p class="muted">Introduce el código de invitación que te compartió el organizador del evento.</p>

      <div class="entrar">
        <label class="field">
          <span class="sr-only">Código de invitación</span>
          <input v-model="codigo" class="control" placeholder="Código de invitación" @keyup.enter="continuar" />
        </label>
        <AppButton :disabled="!codigo.trim()" @click="continuar">Continuar</AppButton>
      </div>
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
  font-size: 1.05rem;
}

.marca__punto {
  width: 12px;
  height: 12px;
  border-radius: 50%;
  background: var(--escenia-gradient-brand);
}

.rol {
  font-size: 0.72rem;
  letter-spacing: 0.14em;
  text-transform: uppercase;
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
</style>
