import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia, type Pinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'
import { flushPromises, mount, type DOMWrapper, type VueWrapper } from '@vue/test-utils'
import type { StudioParticipant } from '@escenia/types'

import { useSpeakerStore } from '@/stores/speaker'
import RoomView from '@/views/RoomView.vue'

import { FakeRoom, Track } from './fakes/livekit'

vi.mock('livekit-client', () => import('./fakes/livekit'))

const participant: StudioParticipant = {
  id: 'par_1',
  identity: 'guest-par_1',
  name: 'Grace',
  role: 'speaker',
  stage: 'green_room',
  device_checked: false,
  joined_at: null,
  left_at: null,
}

let pinia: Pinia

/** The green-room preview: getUserMedia answers with tracks the toggles mute. */
function stubCamera(): void {
  const tracks = [
    { kind: 'video', enabled: true, stop: vi.fn() },
    { kind: 'audio', enabled: true, stop: vi.fn() },
  ]
  const stream = {
    getVideoTracks: () => tracks.filter((t) => t.kind === 'video'),
    getAudioTracks: () => tracks.filter((t) => t.kind === 'audio'),
    getTracks: () => tracks,
  }
  Object.defineProperty(navigator, 'mediaDevices', {
    configurable: true,
    value: { getUserMedia: vi.fn(() => Promise.resolve(stream)) },
  })
}

async function mountRoom(): Promise<VueWrapper> {
  useSpeakerStore().session = {
    linkToken: 'lnk_1',
    participant,
    room: 'evt-room',
    access: {
      token: 'lk.jwt',
      url: 'wss://media.escenia.io',
      identity: 'guest-par_1',
      room: 'evt-room',
      expires_at: '2026-10-24T18:00:00Z',
    },
  }
  const empty = { render: () => null }
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'home', component: empty },
      { path: '/g/:token/sala', name: 'room', component: empty },
    ],
  })
  await router.push('/g/lnk_1/sala')
  const wrapper = mount(RoomView, { global: { plugins: [pinia, router] } })
  await flushPromises() // the green-room preview is on
  return wrapper
}

function button(wrapper: VueWrapper, text: string): DOMWrapper<HTMLButtonElement> {
  const found = wrapper.findAll('button').find((b) => b.text().startsWith(text))
  if (found === undefined) throw new Error(`no "${text}" button`)
  return found
}

async function goLive(wrapper: VueWrapper): Promise<void> {
  await button(wrapper, 'Conectar al vídeo en directo').trigger('click')
  await flushPromises()
}

describe('speaker RoomView going live', () => {
  beforeEach(() => {
    pinia = createPinia()
    setActivePinia(pinia)
    FakeRoom.reset()
    stubCamera()
  })

  it('keeps the microphone muted in the green room off the air', async () => {
    const wrapper = await mountRoom()
    await button(wrapper, 'Micrófono').trigger('click')

    await goLive(wrapper)

    const speaker = FakeRoom.last().localParticipant
    expect(wrapper.text()).toContain('En directo')
    expect([...speaker.onAir]).toEqual([Track.Source.Camera])
    expect(button(wrapper, 'Micrófono').attributes('aria-pressed')).toBe('false')
    expect(button(wrapper, 'Cámara').attributes('aria-pressed')).toBe('true')

    // Unmuting is the speaker's own, explicit action.
    await button(wrapper, 'Micrófono').trigger('click')
    await flushPromises()
    expect(speaker.onAir.has(Track.Source.Microphone)).toBe(true)
  })

  it('keeps the camera turned off in the green room off the air', async () => {
    const wrapper = await mountRoom()
    await button(wrapper, 'Cámara').trigger('click')

    await goLive(wrapper)

    const speaker = FakeRoom.last().localParticipant
    expect([...speaker.onAir]).toEqual([Track.Source.Microphone])
    expect(speaker.published.has(Track.Source.Camera)).toBe(false)
    expect(button(wrapper, 'Cámara').attributes('aria-pressed')).toBe('false')
  })

  it('publishes both devices when the speaker left them on', async () => {
    const wrapper = await mountRoom()

    await goLive(wrapper)

    const speaker = FakeRoom.last().localParticipant
    expect([...speaker.onAir]).toEqual([Track.Source.Camera, Track.Source.Microphone])
    expect(speaker.cameraTrack.attach).toHaveBeenCalledWith(wrapper.get('video').element)
  })
})
