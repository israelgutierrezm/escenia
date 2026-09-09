<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  page: number
  pageCount: number
  total: number
  from: number
  to: number
}>()

const emit = defineEmits<{ go: [number] }>()

// Ventana de páginas alrededor de la actual (para no listar cientos).
const pages = computed(() => {
  const out: number[] = []
  const start = Math.max(1, props.page - 2)
  const end = Math.min(props.pageCount, props.page + 2)
  for (let p = start; p <= end; p++) out.push(p)
  return out
})

const first = computed(() => pages.value[0] ?? 1)
const last = computed(() => pages.value[pages.value.length - 1] ?? props.pageCount)
</script>

<template>
  <nav v-if="pageCount > 1" class="pager" aria-label="Paginación">
    <span class="muted">{{ from }}–{{ to }} de {{ total }}</span>

    <div class="pager__pages">
      <button type="button" :disabled="page <= 1" @click="emit('go', page - 1)">‹</button>
      <button v-if="first > 1" type="button" @click="emit('go', 1)">1</button>
      <span v-if="first > 2" class="muted">…</span>
      <button
        v-for="p in pages"
        :key="p"
        type="button"
        :class="{ 'is-active': p === page }"
        @click="emit('go', p)"
      >
        {{ p }}
      </button>
      <span v-if="last < pageCount - 1" class="muted">…</span>
      <button v-if="last < pageCount" type="button" @click="emit('go', pageCount)">
        {{ pageCount }}
      </button>
      <button type="button" :disabled="page >= pageCount" @click="emit('go', page + 1)">›</button>
    </div>
  </nav>
</template>
