import { computed, ref, watch, type ComputedRef, type Ref } from 'vue'

/**
 * Paginación en cliente sobre una lista ya filtrada. Devuelve la página actual,
 * los ítems de la página y los contadores para la barra de paginación.
 */
export function usePagination<T>(items: Ref<T[]> | ComputedRef<T[]>, perPage = 8) {
  const page = ref(1)

  const total = computed(() => items.value.length)
  const pageCount = computed(() => Math.max(1, Math.ceil(total.value / perPage)))

  watch([total], () => {
    if (page.value > pageCount.value) page.value = pageCount.value
  })

  const pageItems = computed(() => items.value.slice((page.value - 1) * perPage, page.value * perPage))
  const from = computed(() => (total.value === 0 ? 0 : (page.value - 1) * perPage + 1))
  const to = computed(() => Math.min(page.value * perPage, total.value))

  function go(target: number): void {
    page.value = Math.min(Math.max(1, target), pageCount.value)
  }

  return { page, pageCount, total, pageItems, from, to, go, perPage }
}
