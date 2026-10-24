import { defineComponent } from 'vue'
import { mount } from '@vue/test-utils'

/**
 * Runs a composable inside a real (render-less) component so lifecycle hooks such as
 * onBeforeUnmount register and fire on `unmount()` exactly as they do in the app.
 */
export function withSetup<T>(composable: () => T): { result: T; unmount: () => void } {
  let result!: T
  const wrapper = mount(
    defineComponent({
      setup() {
        result = composable()
        return () => null
      },
    }),
  )
  return { result, unmount: () => wrapper.unmount() }
}
