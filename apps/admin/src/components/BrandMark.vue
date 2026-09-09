<script setup lang="ts">
withDefaults(defineProps<{ size?: number }>(), { size: 40 })
</script>

<template>
  <svg
    :width="size"
    :height="size"
    viewBox="0 0 48 48"
    fill="none"
    xmlns="http://www.w3.org/2000/svg"
    role="img"
    aria-label="Escenia"
  >
    <defs>
      <linearGradient id="escenia-mark" x1="8" y1="8" x2="40" y2="40" gradientUnits="userSpaceOnUse">
        <stop stop-color="#3aa8ff" />
        <stop offset="0.55" stop-color="#22c6b2" />
        <stop offset="1" stop-color="#17e0a0" />
      </linearGradient>
    </defs>
    <path
      d="M34.5 13C20 10.2 14.5 18.4 21.8 22.6c7.3 4.2 3.4 12.2-8.8 10.6"
      stroke="url(#escenia-mark)"
      stroke-width="7"
      stroke-linecap="round"
    />
    <circle cx="35.8" cy="30.4" r="4.4" fill="#17e0a0" />
  </svg>
</template>
