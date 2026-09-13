import { createAttendeeClient } from '@escenia/api-client'

let token: string | null = null

/** The attendee join token is sent as X-Attendee-Token on every live request. */
export function setAttendeeToken(value: string | null): void {
  token = value
}

export const api = createAttendeeClient({
  baseUrl: import.meta.env.VITE_API_URL ?? '',
  token: () => token,
})
