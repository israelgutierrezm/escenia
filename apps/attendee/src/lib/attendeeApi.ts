import { createAttendeeClient } from '@escenia/api-client'

let token: string | null = null

/** The attendee join token is sent as X-Attendee-Token on every live request. */
export function setAttendeeToken(value: string | null): void {
  token = value
}

/** The current join token, for surfaces that need it directly (e.g. Echo presence auth). */
export function getAttendeeToken(): string | null {
  return token
}

export const api = createAttendeeClient({
  baseUrl: import.meta.env.VITE_API_URL ?? '',
  token: () => token,
})
