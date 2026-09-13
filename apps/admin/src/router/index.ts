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
          path: 'events/:id/contenido',
          name: 'event-content',
          component: () => import('@/views/EventContentView.vue'),
        },
        {
          path: 'events/:id/educacion',
          name: 'event-education',
          component: () => import('@/views/EventEducationView.vue'),
        },
        {
          path: 'events/:id/agenda',
          name: 'event-agenda',
          component: () => import('@/views/EventEnterpriseView.vue'),
        },
        {
          path: 'events/:id/ia',
          name: 'event-ai',
          component: () => import('@/views/EventAiView.vue'),
        },
        {
          path: 'members',
          name: 'members',
          component: () => import('@/views/MembersView.vue'),
        },
        {
          path: 'automations',
          name: 'automations',
          component: () => import('@/views/AutomationsView.vue'),
        },
        {
          path: 'settings',
          name: 'settings',
          component: () => import('@/views/TenantSettingsView.vue'),
        },
        {
          path: 'payment-accounts',
          name: 'payment-accounts',
          component: () => import('@/views/PaymentAccountsView.vue'),
        },
        {
          path: 'enterprise',
          name: 'enterprise',
          component: () => import('@/views/EnterpriseView.vue'),
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

// Título de documento por ruta: orienta a usuarios de lector de pantalla y
// distingue las entradas del historial del navegador.
const TITULOS: Record<string, string> = {
  login: 'Iniciar sesión',
  dashboard: 'Resumen',
  events: 'Eventos',
  'event-detail': 'Evento',
  'event-studio': 'Studio',
  'event-registration': 'Registro',
  'event-analytics': 'Analíticas',
  'event-commerce': 'Comercio',
  'event-engagement': 'Engagement',
  'event-content': 'Contenido',
  'event-education': 'Educación',
  'event-agenda': 'Agenda y expo',
  'event-ai': 'IA',
  members: 'Miembros y roles',
  automations: 'Automatizaciones',
  settings: 'Configuración',
  'payment-accounts': 'Cuentas de pago',
  enterprise: 'Enterprise',
  'system-settings': 'Sistema',
}

router.afterEach((to) => {
  const titulo = typeof to.name === 'string' ? TITULOS[to.name] : undefined
  document.title = titulo !== undefined ? `${titulo} · Escenia` : 'Escenia · Admin'
})
