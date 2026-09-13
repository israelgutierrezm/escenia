<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue'
import { AppButton } from '@escenia/ui'
import { ApiError } from '@escenia/api-client'
import type { ChatMessage } from '@escenia/types'

import { api } from '@/lib/attendeeApi'

const mensajes = ref<ChatMessage[]>([])
const nuevo = ref('')
const error = ref<string | null>(null)
const enviando = ref(false)
let timer: ReturnType<typeof setInterval> | null = null

async function cargar(): Promise<void> {
  try {
    mensajes.value = (await api.chat()).data
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'No se pudo cargar el chat.'
  }
}

async function enviar(): Promise<void> {
  if (nuevo.value.trim() === '') return
  enviando.value = true
  error.value = null
  try {
    await api.postChat(nuevo.value)
    nuevo.value = ''
    await cargar()
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'No se pudo enviar.'
  } finally {
    enviando.value = false
  }
}

onMounted(() => {
  cargar()
  timer = setInterval(cargar, 12000)
})
onUnmounted(() => {
  if (timer !== null) clearInterval(timer)
})
</script>

<template>
  <div class="stack">
    <div class="stage panel">
      <span class="stage__live"><span class="dot"></span> En vivo</span>
      <div class="stage__center">
        <div class="stage__play" aria-hidden="true">▶</div>
        <p>La transmisión del evento aparecerá aquí.</p>
      </div>
    </div>

    <div class="panel chat">
      <h2>Chat</h2>
      <div class="chat__box">
        <p v-if="mensajes.length === 0" class="empty">Aún no hay mensajes. ¡Saluda!</p>
        <div v-for="m in mensajes" :key="m.id" class="msg">
          <strong :class="{ host: m.is_host }">
            {{ m.author_name }}<span v-if="m.is_host" class="chip chip--primary tag">Anfitrión</span>
          </strong>
          <span>{{ m.body }}</span>
        </div>
      </div>
      <div class="chat__send">
        <input v-model="nuevo" class="control grow" placeholder="Escribe un mensaje…" @keyup.enter="enviar" />
        <AppButton :disabled="enviando || !nuevo.trim()" @click="enviar">Enviar</AppButton>
      </div>
      <p v-if="error" class="error-text" role="alert">{{ error }}</p>
    </div>
  </div>
</template>

<style scoped>
.grow {
  flex: 1;
}

.stage {
  position: relative;
  aspect-ratio: 16 / 9;
  display: grid;
  place-items: center;
  background: radial-gradient(120% 120% at 50% 0%, rgba(46, 166, 255, 0.14), rgba(4, 16, 29, 0.6));
  border-color: var(--escenia-color-border-strong);
  overflow: hidden;
}

.stage__live {
  position: absolute;
  top: var(--escenia-space-3);
  left: var(--escenia-space-3);
  display: inline-flex;
  align-items: center;
  gap: 7px;
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: var(--escenia-color-accent);
}

.stage__live .dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: var(--escenia-color-accent);
  animation: latido 1.6s ease-in-out infinite;
}

.stage__center {
  text-align: center;
  color: var(--escenia-color-text-muted);
}

.stage__play {
  width: 64px;
  height: 64px;
  margin: 0 auto var(--escenia-space-3);
  display: grid;
  place-items: center;
  border-radius: 50%;
  font-size: 1.4rem;
  color: #fff;
  background: color-mix(in srgb, var(--escenia-color-primary) 30%, transparent);
  border: 1px solid color-mix(in srgb, var(--escenia-color-primary) 55%, transparent);
}

@keyframes latido {
  0%,
  100% {
    opacity: 1;
  }
  50% {
    opacity: 0.35;
  }
}

.chat__box {
  display: flex;
  flex-direction: column;
  gap: var(--escenia-space-2);
  max-height: 300px;
  overflow-y: auto;
  margin-bottom: var(--escenia-space-3);
}

.msg {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: 8px 10px;
  border-radius: var(--escenia-radius-sm);
  background: rgba(4, 16, 29, 0.4);
}

.msg strong {
  font-size: 0.88rem;
}

.msg strong.host {
  color: var(--escenia-color-primary);
}

.tag {
  margin-left: 8px;
}

.chat__send {
  display: flex;
  gap: var(--escenia-space-2);
}
</style>
