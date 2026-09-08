import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import { ApiError } from '@escenia/api-client'
import type { Tenant, User } from '@escenia/types'

import { api } from '@/lib/api'

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const ready = ref(false)

  const isAuthenticated = computed(() => user.value !== null)
  const tenants = computed<Tenant[]>(() => user.value?.tenants ?? [])

  async function fetchUser(): Promise<void> {
    try {
      const response = await api.me()
      user.value = response.data
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
  }

  async function logout(): Promise<void> {
    await api.logout()
    user.value = null
  }

  return { user, ready, isAuthenticated, tenants, fetchUser, login, logout }
})
