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
  // The last session check could not reach the API (network error or 5xx):
  // the producer is treated as signed out and the login screen says why.
  const unreachable = ref(false)
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

  /**
   * Resolves the session. Never throws: a 401 is a guest, and any other failure
   * leaves the producer signed out with `unreachable` set, so the router still
   * reaches the login screen instead of a blank page.
   */
  async function fetchUser(): Promise<void> {
    try {
      user.value = (await api.me()).data
      unreachable.value = false
      syncDefaultTenant()
    } catch (error) {
      user.value = null
      unreachable.value = !(error instanceof ApiError && error.status === 401)
    } finally {
      ready.value = true
    }
  }

  async function login(email: string, password: string): Promise<void> {
    user.value = (await api.login({ email, password })).data
    unreachable.value = false
    syncDefaultTenant()
  }

  /**
   * Ends the session. Returns where to send the browser when SSO opened it and
   * the IdP session must end too (SAML single logout), else null.
   */
  async function logout(): Promise<string | null> {
    const result = await api.logout(`${window.location.origin}/login`)
    user.value = null
    selectTenant(null)

    return result?.data.sso_logout_url ?? null
  }

  return {
    user,
    ready,
    unreachable,
    activeTenantId,
    isAuthenticated,
    tenants,
    activeTenant,
    selectTenant,
    fetchUser,
    login,
    logout,
  }
})
