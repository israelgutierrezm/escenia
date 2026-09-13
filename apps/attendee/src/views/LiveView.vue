<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { AppButton } from '@escenia/ui'

import TabList from '@/components/TabList.vue'
import CtaBanner from '@/components/live/CtaBanner.vue'
import StagePanel from '@/components/live/StagePanel.vue'
import QaPanel from '@/components/live/QaPanel.vue'
import PollsPanel from '@/components/live/PollsPanel.vue'
import AgendaPanel from '@/components/live/AgendaPanel.vue'
import ExpoPanel from '@/components/live/ExpoPanel.vue'
import ResourcesPanel from '@/components/live/ResourcesPanel.vue'
import AssessmentPanel from '@/components/live/AssessmentPanel.vue'
import RankingPanel from '@/components/live/RankingPanel.vue'
import { api } from '@/lib/attendeeApi'
import { useAttendeeStore } from '@/stores/attendee'

const route = useRoute()
const router = useRouter()
const store = useAttendeeStore()
const eventId = route.params.eventId as string

type Tab = 'envivo' | 'preguntas' | 'encuestas' | 'agenda' | 'expo' | 'recursos' | 'evaluacion' | 'ranking'
const tab = ref<Tab>('envivo')
const tabs: [Tab, string][] = [
  ['envivo', 'En vivo'],
  ['preguntas', 'Preguntas'],
  ['encuestas', 'Encuestas'],
  ['agenda', 'Agenda'],
  ['expo', 'Expo'],
  ['recursos', 'Recursos'],
  ['evaluacion', 'Evaluación'],
  ['ranking', 'Ranking'],
]

const eventTitle = ref('Evento en vivo')
const puntos = ref(0)

async function cargarCabecera(): Promise<void> {
  try {
    eventTitle.value = (await api.registration(eventId)).data.event.title
  } catch {
    /* el título es decorativo */
  }
  await refrescarPuntos()
}

async function refrescarPuntos(): Promise<void> {
  try {
    puntos.value = (await api.gamification()).data.points
  } catch {
    /* silencioso */
  }
}

async function salir(): Promise<void> {
  try {
    await api.leave()
  } catch {
    /* best effort */
  }
  await router.push({ name: 'home' })
}

watch(tab, () => {
  void refrescarPuntos()
})

onMounted(() => {
  void cargarCabecera()
  void api.join().catch(() => {})
})

onBeforeUnmount(() => {
  void api.leave().catch(() => {})
})
</script>

<template>
  <div class="live">
    <a href="#contenido" class="skip-link">Saltar al contenido</a>

    <header class="bar">
      <div class="bar__title">
        <span class="live-dot" aria-hidden="true"></span>
        <strong>{{ eventTitle }}</strong>
      </div>
      <div class="bar__right">
        <span class="chip chip--primary puntos" :title="`${puntos} puntos`">{{ puntos }} pts</span>
        <span class="muted nombre">{{ store.attendee?.name }}</span>
        <AppButton variant="ghost" @click="salir">Salir</AppButton>
      </div>
    </header>

    <main id="contenido" class="contenido stack">
      <CtaBanner />

      <TabList v-model="tab" :tabs="tabs" label="Secciones del evento" base="live" />

      <StagePanel v-if="tab === 'envivo'" />
      <QaPanel v-else-if="tab === 'preguntas'" />
      <PollsPanel v-else-if="tab === 'encuestas'" />
      <AgendaPanel v-else-if="tab === 'agenda'" />
      <ExpoPanel v-else-if="tab === 'expo'" />
      <ResourcesPanel v-else-if="tab === 'recursos'" />
      <AssessmentPanel v-else-if="tab === 'evaluacion'" />
      <RankingPanel v-else />
    </main>
  </div>
</template>

<style scoped>
.live {
  min-height: 100dvh;
  display: flex;
  flex-direction: column;
}

.bar {
  position: sticky;
  top: 0;
  z-index: 10;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--escenia-space-3);
  padding: var(--escenia-space-3) var(--escenia-space-4);
  border-bottom: 1px solid var(--escenia-color-border);
  background: rgba(6, 18, 31, 0.72);
  backdrop-filter: blur(14px);
}

.bar__title {
  display: flex;
  align-items: center;
  gap: 10px;
  min-width: 0;
}

.bar__title strong {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.live-dot {
  width: 9px;
  height: 9px;
  flex-shrink: 0;
  border-radius: 50%;
  background: var(--escenia-color-accent);
  box-shadow: 0 0 0 4px color-mix(in srgb, var(--escenia-color-accent) 20%, transparent);
}

.bar__right {
  display: flex;
  align-items: center;
  gap: var(--escenia-space-3);
  flex-shrink: 0;
}

.nombre {
  font-size: 0.85rem;
}

.contenido {
  flex: 1;
  width: 100%;
  max-width: 860px;
  margin: 0 auto;
  padding: var(--escenia-space-5) var(--escenia-space-4) var(--escenia-space-8);
}

@media (max-width: 560px) {
  .nombre {
    display: none;
  }
}
</style>
