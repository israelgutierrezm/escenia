import { createRouter, createWebHistory } from 'vue-router'

import { useSpeakerStore } from '@/stores/speaker'

export const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/',
      name: 'home',
      component: () => import('@/views/HomeView.vue'),
    },
    {
      path: '/g/:token',
      name: 'join',
      component: () => import('@/views/JoinView.vue'),
    },
    {
      path: '/g/:token/sala',
      name: 'room',
      component: () => import('@/views/RoomView.vue'),
      meta: { requiresSession: true },
    },
    {
      path: '/:pathMatch(.*)*',
      redirect: { name: 'home' },
    },
  ],
})

router.beforeEach((to) => {
  const store = useSpeakerStore()
  const token = typeof to.params.token === 'string' ? to.params.token : null

  if (token !== null) store.activate(token)

  if (to.meta.requiresSession === true && !store.isJoined) {
    return { name: 'join', params: { token } }
  }

  if (to.name === 'join' && store.isJoined && token !== null) {
    return { name: 'room', params: { token } }
  }

  return true
})

const TITULOS: Record<string, string> = {
  home: 'Ponente',
  join: 'Unirme',
  room: 'Sala',
}

router.afterEach((to) => {
  const titulo = typeof to.name === 'string' ? TITULOS[to.name] : undefined
  document.title = titulo !== undefined ? `${titulo} · Escenia` : 'Escenia · Ponente'
})
