<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue'
import * as THREE from 'three'
import WAVES from 'vanta/dist/vanta.waves.min'

import BrandMark from '@/components/BrandMark.vue'

/**
 * Marco de las pantallas de acceso — mismo diseño que Acadion, recoloreado a la
 * marca escenia.
 *
 * Escritorio: FORMULARIO a la izquierda; a la DERECHA un panel completo con el
 * efecto animado de vanta.js «Waves» en tonos navy de escenia.
 * Móvil: solo el formulario.
 */
const FRASES = [
  'Experiencias que unen.',
  'Crea, conecta, transmite, inspira.',
  'Donde las ideas cobran vida.',
  'Cada evento, una experiencia.',
  'Tu evento, de principio a fin.',
]
const frase = FRASES[Math.floor(Math.random() * FRASES.length)]

const fondo = ref<HTMLElement | null>(null)
let efecto: { destroy: () => void; resize: () => void } | null = null
let observador: ResizeObserver | null = null

onMounted(() => {
  if (fondo.value === null) return
  if (!window.matchMedia('(min-width: 1024px)').matches) return

  try {
    efecto = WAVES({
      el: fondo.value,
      THREE,
      mouseControls: true,
      touchControls: true,
      gyroControls: false,
      minHeight: 200,
      minWidth: 200,
      scale: 1,
      scaleMobile: 1,
      color: 0x0e2a4a,
      shininess: 40,
      waveHeight: 15,
      waveSpeed: 0.85,
      zoom: 0.92,
    })
  } catch {
    return // sin WebGL queda el fondo neutro
  }

  const reajustar = (): void => {
    try {
      efecto?.resize()
    } catch {
      /* nada */
    }
  }

  requestAnimationFrame(reajustar)
  setTimeout(reajustar, 300)

  if (typeof ResizeObserver !== 'undefined') {
    observador = new ResizeObserver(reajustar)
    observador.observe(fondo.value)
  }
})

onBeforeUnmount(() => {
  observador?.disconnect()
  try {
    efecto?.destroy()
  } catch {
    /* nada */
  }
})
</script>

<template>
  <div class="wrap">
    <!-- Panel Vanta: solo escritorio (columna derecha). -->
    <div class="vanta">
      <div ref="fondo" class="vanta__bg"></div>
      <div class="vanta__velo"></div>

      <div class="vanta__overlay">
        <svg class="vanta__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 12a2 2 0 1 0 0-.01M6.3 6.3a8 8 0 0 0 0 11.4M17.7 6.3a8 8 0 0 1 0 11.4M3.5 3.5a12 12 0 0 0 0 17M20.5 3.5a12 12 0 0 1 0 17" />
        </svg>
        <h2 class="vanta__frase">{{ frase }}</h2>
        <span class="vanta__linea"></span>
        <p class="vanta__sub">Todo tu evento en un solo lugar.</p>
      </div>
    </div>

    <!-- Panel del formulario -->
    <div class="form-panel">
      <div class="form-inner entra">
        <div class="brand">
          <BrandMark :size="64" />
          <h1 class="brand__name">escenia</h1>
          <slot name="subtitulo" />
        </div>

        <slot />

        <div class="pie">
          <a href="#aviso-de-privacidad">Aviso de privacidad</a>
          <span class="sep">•</span>
          <a href="mailto:soporte@escenia.app">Soporte</a>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.wrap {
  display: flex;
  flex-direction: column;
  min-height: 100vh;
}

@media (min-width: 1024px) {
  .wrap {
    flex-direction: row;
  }
}

/* Panel Vanta (derecha en escritorio) */
.vanta {
  position: relative;
  display: none;
  overflow: hidden;
  background: var(--escenia-color-bg);
}

@media (min-width: 1024px) {
  .vanta {
    display: block;
    order: 2;
    width: 52%;
    height: 100vh;
  }
}

.vanta__bg {
  position: absolute;
  inset: 0;
}

.vanta__velo {
  position: absolute;
  inset: 0;
  background: linear-gradient(115deg, rgba(6, 18, 31, 0.62), rgba(6, 18, 31, 0.18) 55%, transparent);
}

.vanta__overlay {
  position: relative;
  z-index: 1;
  height: 100%;
  display: flex;
  flex-direction: column;
  justify-content: center;
  padding: 0 clamp(48px, 6vw, 88px);
  color: #fff;
}

.vanta__icon {
  width: 46px;
  height: 46px;
  color: rgba(255, 255, 255, 0.5);
  margin-bottom: 14px;
}

.vanta__frase {
  margin: 0;
  font-size: clamp(1.8rem, 3vw, 2.6rem);
  font-weight: 700;
  line-height: 1.15;
  letter-spacing: -0.01em;
}

.vanta__linea {
  display: block;
  margin-top: 20px;
  width: 56px;
  height: 3px;
  border-radius: 3px;
  background: var(--escenia-color-primary);
}

.vanta__sub {
  margin: 22px 0 0;
  max-width: 24rem;
  font-size: 1rem;
  color: rgba(255, 255, 255, 0.82);
}

/* Panel del formulario (izquierda) */
.form-panel {
  order: 2;
  flex: 1;
  display: grid;
  place-items: center;
  padding: var(--escenia-space-8) var(--escenia-space-6);
  background: var(--escenia-color-bg);
}

@media (min-width: 1024px) {
  .form-panel {
    order: 1;
  }
}

.form-inner {
  width: 100%;
  max-width: 360px;
}

.brand {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  margin-bottom: var(--escenia-space-8);
}

.brand__name {
  margin: var(--escenia-space-3) 0 0;
  font-size: 1.7rem;
  font-weight: 700;
  letter-spacing: -0.01em;
}

.pie {
  margin-top: var(--escenia-space-8);
  text-align: center;
  font-size: 0.75rem;
  color: var(--escenia-color-text-muted);
}

.pie a {
  color: var(--escenia-color-text-muted);
  text-decoration: none;
  transition: color 0.15s ease;
}

.pie a:hover {
  color: var(--escenia-color-text);
}

.sep {
  margin: 0 var(--escenia-space-2);
}

@keyframes entrar {
  from {
    opacity: 0;
    transform: translateY(14px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.entra {
  animation: entrar 0.6s cubic-bezier(0.16, 1, 0.3, 1) both;
}

@media (prefers-reduced-motion: reduce) {
  .entra {
    animation: none;
  }
}
</style>
