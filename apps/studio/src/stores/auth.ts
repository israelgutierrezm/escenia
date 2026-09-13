import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import { ApiError } from '@escenia/api-client'
import type { Tenant, User } from '@escenia/types'

import { api, setActiveTenant } from '@/lib/api'

/**
 * Producer auth for the studio console. Sanctum SPA cookie session (shared with
 * the rest of the platform on localhost). The active tenant defaults to the
 * producer's first membership — the console is single-tenant per session.
 */
export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const ready = ref(false)
  const activeTenantId = ref<string | null>(null)

  const isAuthenticated = computed(() => user.value !== null)
  const tenants = computed<Tenant[]>(() => user.value?.tenants ?? [])
  const activeTenant = computed<Tenant | null>(
    () => tenants.value.find((t) => t.id === activeTenantId.value) ?? null,
  )

  function selectTenant(id: string | null): void {
    activeTenantId.value = id
    setActiveTenant(id)
  }

  function syncDefaultTenant(): void {
    const stillValid = activeTenantId.value !== null && tenants.value.some((t) => t.id === activeTenantId.value)
    if (!stillValid) selectTenant(tenants.value[0]?.id ?? null)
  }

  async function fetchUser(): Promise<void> {
    try {
      user.value = (await api.me()).data
      syncDefaultTenant()
    } catch (error) {
      if (error instanceof ApiError && error.status === 401) user.value = null
      else throw error
    } finally {
      ready.value = true
    }
  }

  async function login(email: string, password: string): Promise<void> {
    user.value = (await api.login({ email, password })).data
    syncDefaultTenant()
  }

  async function logout(): Promise<void> {
    await api.logout()
    user.value = null
    selectTenant(null)
  }

  return { user, ready, activeTenantId, isAuthenticated, tenants, activeTenant, selectTenant, fetchUser, login, logout }
})
