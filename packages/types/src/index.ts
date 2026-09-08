/**
 * Shared API contract types for Escenia. These mirror the backend API Resources
 * (`app/Http/Resources`) and are the single source of truth for the SPA apps.
 * Public identifiers are always the opaque ULID string — never the internal id.
 */

export interface ApiResource<T> {
  data: T
}

export interface ApiCollection<T> {
  data: T[]
  links?: PaginationLinks
  meta?: PaginationMeta
}

export interface PaginationLinks {
  first: string | null
  last: string | null
  prev: string | null
  next: string | null
}

export interface PaginationMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

export interface ApiErrorPayload {
  message: string
  error_code: string
  request_id: string
  errors?: Record<string, string[]>
}

export type TenantStatus = 'active' | 'suspended'
export type TenantRole = 'owner' | 'admin' | 'member'
export type MembershipStatus = 'invited' | 'active' | 'suspended'

export interface Tenant {
  id: string
  name: string
  slug: string
  status: TenantStatus
  role?: TenantRole | null
  membership_status?: MembershipStatus | null
  created_at: string | null
}

export interface User {
  id: string
  name: string
  email: string
  timezone: string | null
  locale: string | null
  tenants?: Tenant[]
  created_at: string | null
}

export interface Workspace {
  id: string
  name: string
  slug: string
  status: string
  created_at: string | null
}
