<script setup lang="ts">
import { ref, useSlots } from 'vue'

import ViewToggle from '@/components/ViewToggle.vue'

const props = withDefaults(
  defineProps<{
    placeholder?: string
    viewKey?: string
    filterCount?: number
    withView?: boolean
  }>(),
  { placeholder: 'Buscar…', filterCount: 0, withView: true },
)

const search = defineModel<string>('search', { default: '' })
const view = defineModel<'lista' | 'cuadricula'>('view', { default: 'lista' })

const slots = useSlots()
const abierto = ref(props.filterCount > 0)
</script>

<template>
  <div class="panel toolbar">
    <div class="toolbar__row">
      <button
        v-if="slots.filters"
        type="button"
        class="icon-btn"
        :class="{ 'is-active': abierto || filterCount > 0 }"
        @click="abierto = !abierto"
      >
        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" d="M6 12h12M3 6h18M9 18h6" />
        </svg>
        Filtros
        <span v-if="filterCount > 0" class="count">{{ filterCount }}</span>
      </button>

      <input v-model="search" type="search" class="control toolbar__search" :placeholder="placeholder" />

      <div class="toolbar__spacer"></div>

      <slot name="actions" />

      <ViewToggle v-if="withView" v-model="view" :storage-key="viewKey" />
    </div>

    <div v-if="abierto && slots.filters" class="toolbar__filters">
      <slot name="filters" />
    </div>
  </div>
</template>
