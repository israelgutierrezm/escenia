<script setup lang="ts">
withDefaults(
  defineProps<{
    variant?: 'primary' | 'ghost' | 'danger'
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
  font-size: 0.9rem;
  padding: var(--escenia-space-2) var(--escenia-space-4);
  border-radius: var(--escenia-radius-sm);
  border: 1px solid transparent;
  cursor: pointer;
  transition:
    transform 0.14s ease,
    filter 0.14s ease,
    box-shadow 0.14s ease,
    background 0.14s ease;
}

.ui-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.ui-btn--primary {
  background: var(--escenia-color-primary);
  color: #ffffff;
}

.ui-btn--primary:hover:not(:disabled) {
  filter: brightness(1.08);
}

.ui-btn--ghost {
  background: transparent;
  border-color: var(--escenia-color-border);
  color: var(--escenia-color-text);
}

.ui-btn--ghost:hover:not(:disabled) {
  border-color: var(--escenia-color-border-strong);
  background: rgba(255, 255, 255, 0.03);
}

.ui-btn--danger {
  background: transparent;
  border-color: color-mix(in srgb, var(--escenia-color-danger) 45%, transparent);
  color: var(--escenia-color-danger);
}

.ui-btn--danger:hover:not(:disabled) {
  background: color-mix(in srgb, var(--escenia-color-danger) 12%, transparent);
}
</style>
