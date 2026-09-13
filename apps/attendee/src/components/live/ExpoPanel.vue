<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { AppButton } from '@escenia/ui'
import { ApiError } from '@escenia/api-client'
import type { Booth, Sponsor, SponsorTier } from '@escenia/types'

import { api } from '@/lib/attendeeApi'

const sponsors = ref<Sponsor[]>([])
const booths = ref<Booth[]>([])
const visitados = ref<Set<string>>(new Set())
const error = ref<string | null>(null)
const busy = ref(false)

const tierLabel: Record<SponsorTier, string> = {
  platinum: 'Platino',
  gold: 'Oro',
  silver: 'Plata',
  bronze: 'Bronce',
  community: 'Comunidad',
}

async function cargar(): Promise<void> {
  try {
    const [sp, b] = await Promise.all([api.expoSponsors(), api.expoBooths()])
    sponsors.value = sp.data
    booths.value = b.data
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'No se pudo cargar la expo.'
  }
}

async function visitar(b: Booth): Promise<void> {
  busy.value = true
  error.value = null
  try {
    await api.visitBooth(b.id)
    visitados.value.add(b.id)
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'No se pudo registrar la visita.'
  } finally {
    busy.value = false
  }
}

onMounted(cargar)
</script>

<template>
  <div class="stack">
    <div v-if="sponsors.length" class="panel stack">
      <h2>Patrocinadores</h2>
      <div class="sponsors">
        <a
          v-for="s in sponsors"
          :key="s.id"
          class="sponsor"
          :href="s.website_url ?? undefined"
          :target="s.website_url ? '_blank' : undefined"
          rel="noopener"
        >
          <img v-if="s.logo_url" :src="s.logo_url" :alt="s.name" class="sponsor__logo" />
          <span class="sponsor__name">{{ s.name }}</span>
          <span class="chip">{{ tierLabel[s.tier] }}</span>
        </a>
      </div>
    </div>

    <div class="panel stack">
      <h2>Stands</h2>
      <p v-if="booths.length === 0" class="empty">Aún no hay stands en la expo.</p>
      <ul v-else class="lista">
        <li v-for="b in booths" :key="b.id">
          <div class="info">
            <strong>{{ b.name }}</strong>
            <span v-if="b.sponsor" class="muted small"> · {{ b.sponsor.name }}</span>
            <p v-if="b.description" class="muted small">{{ b.description }}</p>
          </div>
          <div class="acc">
            <a v-if="b.url" :href="b.url" target="_blank" rel="noopener" class="chip">Sitio ↗</a>
            <AppButton
              :variant="visitados.has(b.id) ? 'ghost' : 'primary'"
              :disabled="busy || visitados.has(b.id)"
              @click="visitar(b)"
            >
              {{ visitados.has(b.id) ? 'Visitado ✓' : 'Visitar' }}
            </AppButton>
          </div>
        </li>
      </ul>
    </div>

    <p v-if="error" class="error-text" role="alert">{{ error }}</p>
  </div>
</template>

<style scoped>
.small {
  font-size: 0.8rem;
  margin: 2px 0 0;
}

.sponsors {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
  gap: var(--escenia-space-2);
}

.sponsor {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  border: 1px solid var(--escenia-color-border);
  border-radius: var(--escenia-radius-sm);
  background: rgba(4, 16, 29, 0.3);
  text-decoration: none;
  color: var(--escenia-color-text);
}

.sponsor__logo {
  width: 32px;
  height: 32px;
  object-fit: contain;
  border-radius: 6px;
  background: #fff;
  padding: 2px;
}

.sponsor__name {
  flex: 1;
  font-weight: 600;
  font-size: 0.9rem;
}

.lista {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: var(--escenia-space-2);
}

.lista li {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--escenia-space-3);
  padding: var(--escenia-space-3);
  border: 1px solid var(--escenia-color-border);
  border-radius: var(--escenia-radius-sm);
  background: rgba(4, 16, 29, 0.35);
}

.info {
  min-width: 0;
}

.acc {
  display: flex;
  align-items: center;
  gap: var(--escenia-space-2);
  flex-shrink: 0;
}

.acc .chip {
  text-decoration: none;
}
</style>
