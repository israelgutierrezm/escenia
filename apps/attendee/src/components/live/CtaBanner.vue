<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { AppButton } from '@escenia/ui'
import type { Cta } from '@escenia/types'

import { api } from '@/lib/attendeeApi'

const ctas = ref<Cta[]>([])
const descartadas = ref<Set<string>>(new Set())

const visibles = computed(() => ctas.value.filter((c) => c.live && !descartadas.value.has(c.id)))

async function cargar(): Promise<void> {
  try {
    ctas.value = (await api.ctas()).data
  } catch {
    /* silencioso: las CTA son secundarias */
  }
}

async function abrir(c: Cta): Promise<void> {
  try {
    await api.clickCta(c.id)
  } catch {
    /* registrar el clic es best-effort */
  }
  if (c.url) window.open(c.url, '_blank', 'noopener')
}

onMounted(cargar)
</script>

<template>
  <div v-for="c in visibles" :key="c.id" class="cta">
    <div class="cta__text">
      <strong>{{ c.title }}</strong>
      <span v-if="c.body" class="muted"> — {{ c.body }}</span>
    </div>
    <div class="cta__acc">
      <AppButton v-if="c.url" @click="abrir(c)">Ver oferta</AppButton>
      <button type="button" class="cerrar" aria-label="Descartar" @click="descartadas.add(c.id)">✕</button>
    </div>
  </div>
</template>

<style scoped>
.cta {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--escenia-space-3);
  padding: 12px var(--escenia-space-4);
  border: 1px solid color-mix(in srgb, var(--escenia-color-primary) 45%, transparent);
  border-radius: var(--escenia-radius-md);
  background: color-mix(in srgb, var(--escenia-color-primary) 10%, transparent);
  flex-wrap: wrap;
}

.cta__text {
  min-width: 0;
}

.cta__acc {
  display: flex;
  align-items: center;
  gap: var(--escenia-space-2);
  flex-shrink: 0;
}

.cerrar {
  width: 30px;
  height: 30px;
  font: inherit;
  color: var(--escenia-color-text-muted);
  background: transparent;
  border: 1px solid var(--escenia-color-border);
  border-radius: 6px;
  cursor: pointer;
}

.cerrar:hover {
  color: var(--escenia-color-text);
}
</style>
