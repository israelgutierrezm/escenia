import { createRouter, createWebHistory } from 'vue-router'

import { useAuthStore } from '@/stores/auth'

export const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: () => import('@/views/LoginView.vue'),
      meta: { guest: true },
    },
    {
      path: '/',
      name: 'events',
      component: () => import('@/views/EventsView.vue'),
      meta: { auth: true },
    },
    {
      path: '/studio/:eventId',
      name: 'console',
      component: () => import('@/views/ConsoleView.vue'),
      meta: { auth: true },
    },
    {
      path: '/:pathMatch(.*)*',
      redirect: { name: 'events' },
    },
  ],
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()
  if (!auth.ready) await auth.fetchUser()

  if (to.meta.auth === true && !auth.isAuthenticated) return { name: 'login' }
  if (to.meta.guest === true && auth.isAuthenticated) return { name: 'events' }
  return true
})

const TITULOS: Record<string, string> = {
  login: 'Entrar',
  events: 'Eventos',
  console: 'Consola',
}

router.afterEach((to) => {
  const titulo = typeof to.name === 'string' ? TITULOS[to.name] : undefined
  document.title = titulo !== undefined ? `${titulo} · Escenia Studio` : 'Escenia · Studio'
})
