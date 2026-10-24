import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { nextTick } from 'vue'
import { flushPromises, mount } from '@vue/test-utils'
import { ApiError } from '@escenia/api-client'
import type { ChatMessage } from '@escenia/types'

// Handlers the panel binds through useEventChannel, so a spec can push events.
const realtime = vi.hoisted(() => ({ handlers: new Map<string, (payload: unknown) => void>() }))

vi.mock('@/lib/attendeeApi', () => ({ api: { chat: vi.fn(), postChat: vi.fn() } }))
vi.mock('@/composables/useRealtime', () => ({
  useEventChannel: vi.fn(
    (_eventId: string, handlers: Record<string, (payload: unknown) => void>) => {
      for (const [name, handler] of Object.entries(handlers)) realtime.handlers.set(name, handler)
    },
  ),
}))

import { api } from '@/lib/attendeeApi'
import { useEventChannel } from '@/composables/useRealtime'
import StagePanel from '@/components/live/StagePanel.vue'

function message(id: string): ChatMessage {
  return { id, author_name: 'Ada', is_host: false, body: `mensaje ${id}`, created_at: null }
}

describe('StagePanel chat', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    realtime.handlers.clear()
    vi.mocked(api.chat).mockResolvedValue({ data: [] })
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('loads on mount and polls every 12 s until unmounted', async () => {
    vi.useFakeTimers()
    const wrapper = mount(StagePanel, { props: { eventId: 'evt_1' } })
    expect(api.chat).toHaveBeenCalledTimes(1)

    await vi.advanceTimersByTimeAsync(11_999)
    expect(api.chat).toHaveBeenCalledTimes(1)
    await vi.advanceTimersByTimeAsync(1)
    expect(api.chat).toHaveBeenCalledTimes(2)
    await vi.advanceTimersByTimeAsync(24_000)
    expect(api.chat).toHaveBeenCalledTimes(4)

    wrapper.unmount()
    await vi.advanceTimersByTimeAsync(60_000)
    expect(api.chat).toHaveBeenCalledTimes(4)
  })

  it('appends pushed chat.posted messages once, even when redelivered', async () => {
    vi.mocked(api.chat).mockResolvedValue({ data: [message('m1')] })
    const wrapper = mount(StagePanel, { props: { eventId: 'evt_1' } })
    await flushPromises()
    expect(useEventChannel).toHaveBeenCalledWith(
      'evt_1',
      expect.objectContaining({ 'chat.posted': expect.any(Function) }),
    )

    const push = realtime.handlers.get('chat.posted')
    if (push === undefined) throw new Error('chat.posted is not bound')
    push(message('m1')) // already loaded by the first fetch
    push(message('m2'))
    push(message('m2')) // redelivered
    await nextTick()

    expect(wrapper.findAll('.msg')).toHaveLength(2)
    expect(wrapper.text()).toContain('mensaje m2')
    wrapper.unmount()
  })

  it('shows the API error message when the chat cannot load', async () => {
    vi.mocked(api.chat).mockRejectedValue(
      new ApiError(429, 'Too Many Attempts.', 'too_many_requests'),
    )
    const wrapper = mount(StagePanel, { props: { eventId: 'evt_1' } })
    await flushPromises()

    expect(wrapper.get('[role="alert"]').text()).toBe('Too Many Attempts.')
    wrapper.unmount()
  })
})
