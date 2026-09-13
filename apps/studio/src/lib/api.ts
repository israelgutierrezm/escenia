import { createApiClient } from '@escenia/api-client'

let activeTenantId: string | null = null

export function setActiveTenant(id: string | null): void {
  activeTenantId = id
}

export const api = createApiClient({
  baseUrl: import.meta.env.VITE_API_URL ?? '',
  tenantId: () => activeTenantId,
})
