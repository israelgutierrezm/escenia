<script setup lang="ts">
withDefaults(
  defineProps<{
    variant?: 'primary' | 'ghost'
    type?: 'button' | 'submit'
    disabled?: boolean
  }>(),
  {
    variant: 'primary',
    type: 'button',
    disabled: false,
  },
)
</script>

<template>
  <button :type="type" :disabled="disabled" :class="['ui-btn', `ui-btn--${variant}`]">
    <slot />
  </button>
</template>

<style scoped>
.ui-btn {
  font: inherit;
  font-weight: 600;
  padding: var(--escenia-space-2) var(--escenia-space-4);
  border-radius: var(--escenia-radius-sm);
  border: 1px solid transparent;
  cursor: pointer;
  transition: opacity 0.15s ease;
}

.ui-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.ui-btn--primary {
  background: var(--escenia-color-primary);
  color: var(--escenia-color-primary-contrast);
}

.ui-btn--ghost {
  background: transparent;
  border-color: var(--escenia-color-border);
  color: var(--escenia-color-text);
}
</style>
