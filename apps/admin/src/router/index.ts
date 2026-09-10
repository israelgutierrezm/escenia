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
      component: () => import('@/layouts/AdminLayout.vue'),
      meta: { auth: true },
      children: [
        {
          path: '',
          name: 'dashboard',
          component: () => import('@/views/DashboardView.vue'),
        },
        {
          path: 'events',
          name: 'events',
          component: () => import('@/views/EventsView.vue'),
        },
        {
          path: 'events/:id',
          name: 'event-detail',
          component: () => import('@/views/EventDetailView.vue'),
        },
        {
          path: 'events/:id/studio',
          name: 'event-studio',
          component: () => import('@/views/EventStudioView.vue'),
        },
        {
          path: 'events/:id/registro',
          name: 'event-registration',
          component: () => import('@/views/EventRegistrationView.vue'),
        },
        {
          path: 'events/:id/analiticas',
          name: 'event-analytics',
          component: () => import('@/views/EventAnalyticsView.vue'),
        },
        {
          path: 'events/:id/comercio',
          name: 'event-commerce',
          component: () => import('@/views/EventCommerceView.vue'),
        },
        {
          path: 'events/:id/engagement',
          name: 'event-engagement',
          component: () => import('@/views/EventEngagementView.vue'),
        },
        {
          path: 'members',
          name: 'members',
          component: () => import('@/views/MembersView.vue'),
        },
        {
          path: 'settings',
          name: 'settings',
          component: () => import('@/views/TenantSettingsView.vue'),
        },
        {
          path: 'system-settings',
          name: 'system-settings',
          component: () => import('@/views/SystemSettingsView.vue'),
          meta: { superAdmin: true },
        },
      ],
    },
  ],
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()

  if (!auth.ready) {
    await auth.fetchUser()
  }

  if (to.meta.auth && !auth.isAuthenticated) {
    return { name: 'login' }
  }

  if (to.meta.guest && auth.isAuthenticated) {
    return { name: 'dashboard' }
  }

  if (to.meta.superAdmin && !auth.isSuperAdmin) {
    return { name: 'dashboard' }
  }

  return true
})
