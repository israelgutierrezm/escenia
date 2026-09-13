import { createApiClient } from '@escenia/api-client'

/**
 * The guest-join endpoint is public — the link token is the credential — so no
 * tenant/cookie auth is needed. We reuse the host client purely for that call.
 */
export const api = createApiClient({
  baseUrl: import.meta.env.VITE_API_URL ?? '',
  tenantId: () => null,
})
