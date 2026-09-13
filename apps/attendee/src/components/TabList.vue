<script setup lang="ts" generic="K extends string">
import { nextTick } from 'vue'

const props = defineProps<{
  tabs: [K, string][]
  label: string
  base: string
  badges?: Partial<Record<K, number | undefined>>
}>()

const model = defineModel<K>({ required: true })

function seleccionar(key: K): void {
  model.value = key
}

function enfocar(key: K): void {
  nextTick(() => {
    document.getElementById(`${props.base}-tab-${key}`)?.focus()
  })
}

function onKeydown(e: KeyboardEvent): void {
  const keys = props.tabs.map(([k]) => k)
  const i = keys.indexOf(model.value)
  if (i === -1) return

  let n = i
  switch (e.key) {
    case 'ArrowRight':
    case 'ArrowDown':
      n = (i + 1) % keys.length
      break
    case 'ArrowLeft':
    case 'ArrowUp':
      n = (i - 1 + keys.length) % keys.length
      break
    case 'Home':
      n = 0
      break
    case 'End':
      n = keys.length - 1
      break
    default:
      return
  }

  e.preventDefault()
  const siguiente = keys[n]
  if (siguiente !== undefined) {
    model.value = siguiente
    enfocar(siguiente)
  }
}
</script>

<template>
  <div class="tabs" role="tablist" :aria-label="label" @keydown="onKeydown">
    <button
      v-for="[key, etiqueta] in tabs"
      :id="`${base}-tab-${key}`"
      :key="key"
      type="button"
      role="tab"
      class="tab"
      :class="{ 'is-active': model === key }"
      :aria-selected="model === key"
      :tabindex="model === key ? 0 : -1"
      @click="seleccionar(key)"
    >
      {{ etiqueta }}
      <span v-if="badges && badges[key]" class="count">{{ badges[key] }}</span>
    </button>
  </div>
</template>

<style scoped>
.tabs {
  display: flex;
  gap: var(--escenia-space-2);
  flex-wrap: wrap;
}

.tab {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 9px 16px;
  font: inherit;
  font-weight: 600;
  font-size: 0.85rem;
  color: var(--escenia-color-text-muted);
  background: transparent;
  border: 1px solid var(--escenia-color-border);
  border-radius: var(--escenia-radius-sm);
  cursor: pointer;
  transition: all 0.14s ease;
}

.tab:hover {
  color: var(--escenia-color-text);
}

.tab.is-active {
  color: #fff;
  background: var(--escenia-color-primary);
  border-color: transparent;
}

.tab .count {
  background: rgba(255, 255, 255, 0.25);
  border-radius: 999px;
  padding: 0 7px;
  font-size: 0.7rem;
}
</style>
