import { createRouter, createWebHistory } from 'vue-router'

import { useAttendeeStore } from '@/stores/attendee'

export const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/',
      name: 'home',
      component: () => import('@/views/HomeView.vue'),
    },
    {
      path: '/e/:eventId',
      name: 'register',
      component: () => import('@/views/RegisterView.vue'),
    },
    {
      path: '/e/:eventId/vivo',
      name: 'live',
      component: () => import('@/views/LiveView.vue'),
      meta: { requiresToken: true },
    },
    {
      path: '/verificar/:code?',
      name: 'verify',
      component: () => import('@/views/VerifyCertificateView.vue'),
    },
    {
      path: '/:pathMatch(.*)*',
      redirect: { name: 'home' },
    },
  ],
})

router.beforeEach((to) => {
  const store = useAttendeeStore()
  const eventId = typeof to.params.eventId === 'string' ? to.params.eventId : null

  if (eventId !== null) store.activate(eventId)

  if (to.meta.requiresToken === true && !store.isRegistered) {
    return { name: 'register', params: { eventId } }
  }

  if (to.name === 'register' && store.isRegistered && eventId !== null) {
    return { name: 'live', params: { eventId } }
  }

  return true
})

const TITULOS: Record<string, string> = {
  home: 'Escenia',
  register: 'Registro',
  live: 'En vivo',
  verify: 'Verificar certificado',
}

router.afterEach((to) => {
  const titulo = typeof to.name === 'string' ? TITULOS[to.name] : undefined
  document.title = titulo !== undefined ? `${titulo} · Escenia` : 'Escenia · Evento'
})
