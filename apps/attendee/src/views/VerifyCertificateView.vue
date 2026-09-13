<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { AppButton } from '@escenia/ui'
import { ApiError } from '@escenia/api-client'
import type { CertificateVerification } from '@escenia/types'

import { api } from '@/lib/attendeeApi'

const route = useRoute()
const codigo = ref(typeof route.params.code === 'string' ? route.params.code : '')
const resultado = ref<CertificateVerification | null>(null)
const buscado = ref(false)
const cargando = ref(false)
const error = ref<string | null>(null)

function fecha(iso: string | undefined): string {
  if (iso === undefined) return '—'
  return new Date(iso).toLocaleDateString('es', { day: 'numeric', month: 'long', year: 'numeric' })
}

async function verificar(): Promise<void> {
  if (codigo.value.trim() === '') return
  cargando.value = true
  error.value = null
  resultado.value = null
  try {
    resultado.value = (await api.verifyCertificate(codigo.value.trim())).data
    buscado.value = true
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'No se pudo verificar.'
  } finally {
    cargando.value = false
  }
}

onMounted(() => {
  if (codigo.value.trim() !== '') verificar()
})
</script>

<template>
  <main class="centro">
    <section class="card panel">
      <div class="marca"><span class="marca__punto"></span><strong>escenia</strong></div>
      <h1>Verificar certificado</h1>
      <p class="muted">Introduce el código del certificado para comprobar su autenticidad.</p>

      <div class="buscar">
        <label class="field">
          <span class="sr-only">Código del certificado</span>
          <input v-model="codigo" class="control" placeholder="Código del certificado" @keyup.enter="verificar" />
        </label>
        <AppButton :disabled="cargando || !codigo.trim()" @click="verificar">Verificar</AppButton>
      </div>

      <p v-if="error" class="error-text" role="alert">{{ error }}</p>

      <div v-if="resultado" class="resultado" :class="resultado.valid ? 'is-valid' : 'is-invalid'" role="status">
        <template v-if="resultado.valid">
          <p class="estado">✓ Certificado válido</p>
          <dl>
            <div><dt>Otorgado a</dt><dd>{{ resultado.recipient_name }}</dd></div>
            <div><dt>Evento</dt><dd>{{ resultado.event_title ?? '—' }}</dd></div>
            <div><dt>Fecha de emisión</dt><dd>{{ fecha(resultado.issued_at) }}</dd></div>
            <div><dt>Código</dt><dd class="mono">{{ resultado.code }}</dd></div>
          </dl>
        </template>
        <p v-else class="estado invalido">No se encontró ningún certificado con ese código.</p>
      </div>

      <RouterLink :to="{ name: 'home' }" class="volver muted">‹ Inicio</RouterLink>
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

.buscar {
  display: flex;
  gap: var(--escenia-space-2);
}

.buscar .field {
  flex: 1;
}

.resultado {
  border: 1px solid var(--escenia-color-border);
  border-radius: var(--escenia-radius-md);
  padding: var(--escenia-space-4);
  background: rgba(4, 16, 29, 0.4);
}

.resultado.is-valid {
  border-color: color-mix(in srgb, var(--escenia-color-accent) 50%, transparent);
}

.estado {
  margin: 0 0 var(--escenia-space-3);
  font-weight: 700;
  color: var(--escenia-color-accent);
}

.estado.invalido {
  margin: 0;
  color: var(--escenia-color-danger);
}

dl {
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: var(--escenia-space-2);
}

dl div {
  display: flex;
  justify-content: space-between;
  gap: var(--escenia-space-3);
  font-size: 0.9rem;
}

dt {
  color: var(--escenia-color-text-muted);
}

dd {
  margin: 0;
  text-align: right;
  font-weight: 600;
}

.mono {
  font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
  font-size: 0.82rem;
}

.volver {
  text-decoration: none;
  font-size: 0.85rem;
}
</style>
