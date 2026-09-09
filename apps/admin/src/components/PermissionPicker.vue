<script setup lang="ts">
import type { PermissionGroup } from '@escenia/types'

const props = defineProps<{
  catalog: PermissionGroup[]
  modelValue: string[]
}>()

const emit = defineEmits<{ 'update:modelValue': [string[]] }>()

function isChecked(value: string): boolean {
  return props.modelValue.includes(value)
}

function toggle(value: string, checked: boolean): void {
  const next = checked
    ? [...props.modelValue, value]
    : props.modelValue.filter((v) => v !== value)
  emit('update:modelValue', next)
}
</script>

<template>
  <div class="picker">
    <fieldset v-for="group in catalog" :key="group.group" class="group">
      <legend class="muted">{{ group.group }}</legend>
      <div class="checks">
        <label v-for="permission in group.permissions" :key="permission.value" class="check">
          <input
            type="checkbox"
            :checked="isChecked(permission.value)"
            @change="toggle(permission.value, ($event.target as HTMLInputElement).checked)"
          />
          <span>{{ permission.label }}</span>
        </label>
      </div>
    </fieldset>
  </div>
</template>

<style scoped>
.picker {
  display: flex;
  flex-direction: column;
  gap: var(--escenia-space-3);
}

.group {
  border: 1px solid var(--escenia-color-border);
  border-radius: var(--escenia-radius-sm);
  padding: var(--escenia-space-2) var(--escenia-space-3);
  margin: 0;
}

.group legend {
  padding: 0 var(--escenia-space-1);
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}
</style>
