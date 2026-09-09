<script setup lang="ts">
import { computed, reactive, watch } from 'vue'
import { AppButton } from '@escenia/ui'
import type { SettingChange, SettingItem } from '@escenia/types'

const props = defineProps<{
  items: SettingItem[]
  saving: boolean
}>()

const emit = defineEmits<{ save: [SettingChange[]] }>()

const draft = reactive<Record<string, string | boolean>>({})

function originalString(item: SettingItem): string {
  if (item.value == null) return ''
  return item.type === 'json' ? JSON.stringify(item.value, null, 2) : String(item.value)
}

function seed(): void {
  for (const key of Object.keys(draft)) delete draft[key]
  for (const item of props.items) {
    if (item.type === 'bool') draft[item.key] = item.value === true
    else if (item.type === 'secret') draft[item.key] = ''
    else draft[item.key] = originalString(item)
  }
}

watch(() => props.items, seed, { immediate: true })

function set(key: string, value: string | boolean): void {
  draft[key] = value
}

function asString(value: string | boolean | undefined): string {
  return typeof value === 'string' ? value : ''
}

const groups = computed(() => {
  const map = new Map<string, SettingItem[]>()
  for (const item of props.items) {
    const list = map.get(item.group) ?? []
    list.push(item)
    map.set(item.group, list)
  }
  return Array.from(map, ([group, items]) => ({ group, items }))
})

function onSave(): void {
  const changes: SettingChange[] = []

  for (const item of props.items) {
    const value = draft[item.key]

    if (item.type === 'secret') {
      if (typeof value === 'string' && value !== '') changes.push({ key: item.key, value })
      continue
    }

    if (item.type === 'bool') {
      if (Boolean(value) !== (item.value === true)) changes.push({ key: item.key, value: Boolean(value) })
      continue
    }

    if (String(value) === originalString(item)) continue

    if (item.type === 'int') changes.push({ key: item.key, value: Number(value) })
    else if (item.type === 'json') {
      try {
        changes.push({ key: item.key, value: JSON.parse(String(value)) })
      } catch {
        // Skip invalid JSON; the field keeps its edited text for the user to fix.
      }
    } else changes.push({ key: item.key, value: String(value) })
  }

  emit('save', changes)
}
</script>

<template>
  <form class="stack" @submit.prevent="onSave">
    <div v-for="group in groups" :key="group.group" class="panel">
      <h2>{{ group.group }}</h2>

      <div class="fields">
        <label v-for="item in group.items" :key="item.key" class="field">
          <span>
            {{ item.label }}
            <em v-if="!item.is_set" class="muted">· por defecto</em>
          </span>

          <select
            v-if="item.type === 'select'"
            :value="asString(draft[item.key])"
            @change="set(item.key, ($event.target as HTMLSelectElement).value)"
          >
            <option v-for="option in item.options" :key="option" :value="option">{{ option }}</option>
          </select>

          <span v-else-if="item.type === 'bool'" class="bool">
            <input
              type="checkbox"
              :checked="draft[item.key] === true"
              @change="set(item.key, ($event.target as HTMLInputElement).checked)"
            />
            <span class="muted">Activado</span>
          </span>

          <input
            v-else-if="item.type === 'int'"
            type="number"
            :value="asString(draft[item.key])"
            @input="set(item.key, ($event.target as HTMLInputElement).value)"
          />

          <input
            v-else-if="item.type === 'secret'"
            type="password"
            autocomplete="off"
            :value="asString(draft[item.key])"
            :placeholder="item.is_set ? '•••••••• (definido — deja en blanco para conservar)' : 'sin definir'"
            @input="set(item.key, ($event.target as HTMLInputElement).value)"
          />

          <textarea
            v-else-if="item.type === 'json'"
            rows="4"
            :value="asString(draft[item.key])"
            @input="set(item.key, ($event.target as HTMLTextAreaElement).value)"
          ></textarea>

          <input
            v-else
            type="text"
            :value="asString(draft[item.key])"
            @input="set(item.key, ($event.target as HTMLInputElement).value)"
          />

          <small v-if="item.help" class="muted">{{ item.help }}</small>
        </label>
      </div>
    </div>

    <div class="actions">
      <AppButton type="submit" :disabled="saving">
        {{ saving ? 'Guardando…' : 'Guardar cambios' }}
      </AppButton>
      <slot name="status" />
    </div>
  </form>
</template>

<style scoped>
.fields {
  display: flex;
  flex-direction: column;
  gap: var(--escenia-space-3);
}

.bool {
  display: flex;
  align-items: center;
  gap: var(--escenia-space-2);
}
</style>
