import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import { ApiError } from '@escenia/api-client'
import type { Tenant, TenantRole, User } from '@escenia/types'

import { api, setActiveTenant } from '@/lib/api'

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const ready = ref(false)
  const activeTenantId = ref<string | null>(null)

  const isAuthenticated = computed(() => user.value !== null)
  const tenants = computed<Tenant[]>(() => user.value?.tenants ?? [])
  const isSuperAdmin = computed(() => user.value?.is_super_admin === true)

  const activeTenant = computed<Tenant | null>(
    () => tenants.value.find((tenant) => tenant.id === activeTenantId.value) ?? null,
  )
  const currentRole = computed<TenantRole | null>(() => activeTenant.value?.role ?? null)
  const canManageMembers = computed(
    () => currentRole.value === 'owner' || currentRole.value === 'admin',
  )
  const canManageTenant = computed(() => currentRole.value === 'owner')

  function selectTenant(id: string | null): void {
    activeTenantId.value = id
    setActiveTenant(id)
  }

  function syncDefaultTenant(): void {
    const stillValid = activeTenantId.value !== null && tenants.value.some((t) => t.id === activeTenantId.value)

    if (!stillValid) {
      selectTenant(tenants.value[0]?.id ?? null)
    }
  }

  async function fetchUser(): Promise<void> {
    try {
      const response = await api.me()
      user.value = response.data
      syncDefaultTenant()
    } catch (error) {
      if (error instanceof ApiError && error.status === 401) {
        user.value = null
      } else {
        throw error
      }
    } finally {
      ready.value = true
    }
  }

  async function login(email: string, password: string): Promise<void> {
    const response = await api.login({ email, password })
    user.value = response.data
    syncDefaultTenant()
  }

  async function logout(): Promise<void> {
    await api.logout()
    user.value = null
    selectTenant(null)
  }

  return {
    user,
    ready,
    activeTenantId,
    isAuthenticated,
    tenants,
    isSuperAdmin,
    activeTenant,
    currentRole,
    canManageMembers,
    canManageTenant,
    selectTenant,
    fetchUser,
    login,
    logout,
  }
})
