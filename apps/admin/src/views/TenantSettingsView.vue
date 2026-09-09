<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { ApiError } from '@escenia/api-client'
import type { SettingChange, SettingItem } from '@escenia/types'

import SettingsForm from '@/components/SettingsForm.vue'
import { api } from '@/lib/api'

const items = ref<SettingItem[]>([])
const loading = ref(true)
const saving = ref(false)
const error = ref<string | null>(null)
const saved = ref(false)

function message(e: unknown): string {
  return e instanceof ApiError ? e.message : 'Something went wrong.'
}

async function load(): Promise<void> {
  loading.value = true
  error.value = null
  try {
    items.value = (await api.tenantConfig()).data
  } catch (e) {
    error.value = message(e)
  } finally {
    loading.value = false
  }
}

async function onSave(changes: SettingChange[]): Promise<void> {
  saved.value = false
  error.value = null

  if (changes.length === 0) {
    saved.value = true
    return
  }

  saving.value = true
  try {
    items.value = (await api.updateTenantConfig(changes)).data
    saved.value = true
  } catch (e) {
    error.value = message(e)
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<template>
  <section class="stack">
    <div>
      <h1>Tenant settings</h1>
      <p class="muted">
        Your tenant's integration overrides. Where you leave a value unset, the platform default
        applies.
      </p>
    </div>

    <p v-if="loading" class="muted">Loading…</p>
    <SettingsForm v-else :items="items" :saving="saving" @save="onSave">
      <template #status>
        <span v-if="saved" class="ok-text">Saved.</span>
        <span v-if="error" class="error-text">{{ error }}</span>
      </template>
    </SettingsForm>
  </section>
</template>
