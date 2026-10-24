import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'

// jsdom has no WebGL: only the renderer is faked, three's maths and geometry are real.
const renderer = vi.hoisted(() => ({
  fail: false,
  instances: [] as Array<{
    render: ReturnType<typeof vi.fn>
    dispose: ReturnType<typeof vi.fn>
    forceContextLoss: ReturnType<typeof vi.fn>
    outputColorSpace: string
  }>,
}))

vi.mock('three', async (original) => {
  const three = await original<typeof import('three')>()

  class FakeRenderer {
    outputColorSpace = ''
    readonly render = vi.fn()
    readonly setSize = vi.fn()
    readonly setPixelRatio = vi.fn()
    readonly dispose = vi.fn()
    readonly forceContextLoss = vi.fn()

    constructor() {
      if (renderer.fail) throw new Error('Error creating WebGL context.')
      renderer.instances.push(this)
    }
  }

  return { ...three, WebGLRenderer: FakeRenderer }
})

import WavesBackground from '@/components/WavesBackground.vue'

let reducedMotion = false
const requestFrame = vi.fn<(callback: FrameRequestCallback) => number>(() => 7)
const cancelFrame = vi.fn()

beforeEach(() => {
  renderer.fail = false
  renderer.instances.length = 0
  reducedMotion = false
  requestFrame.mockClear()
  cancelFrame.mockClear()
  vi.stubGlobal('requestAnimationFrame', requestFrame)
  vi.stubGlobal('cancelAnimationFrame', cancelFrame)
  vi.stubGlobal(
    'ResizeObserver',
    class {
      observe(): void {}
      disconnect(): void {}
    },
  )
  vi.stubGlobal('matchMedia', (query: string) => ({
    matches: query.includes('reduced-motion') && reducedMotion,
    addEventListener: vi.fn(),
    removeEventListener: vi.fn(),
  }))
})

afterEach(() => {
  vi.unstubAllGlobals()
})

describe('WavesBackground', () => {
  it('animates the waves with the r134 look (linear colours) and frees the GPU on unmount', () => {
    const wrapper = mount(WavesBackground)
    const instance = renderer.instances[0]

    expect(instance?.outputColorSpace).toBe('srgb-linear')
    expect(requestFrame).toHaveBeenCalledTimes(1)
    expect(instance?.render).toHaveBeenCalled()

    wrapper.unmount()

    expect(cancelFrame).toHaveBeenCalledWith(7)
    expect(instance?.dispose).toHaveBeenCalled()
    expect(instance?.forceContextLoss).toHaveBeenCalled()
  })

  it('draws a still frame and never animates when motion is reduced', () => {
    reducedMotion = true

    const wrapper = mount(WavesBackground)

    expect(renderer.instances[0]?.render).toHaveBeenCalled()
    expect(requestFrame).not.toHaveBeenCalled()

    wrapper.unmount()
  })

  it('leaves the plain panel when WebGL is unavailable', () => {
    renderer.fail = true

    const wrapper = mount(WavesBackground)

    expect(renderer.instances).toHaveLength(0)
    expect(requestFrame).not.toHaveBeenCalled()
    expect(() => wrapper.unmount()).not.toThrow()
  })
})
