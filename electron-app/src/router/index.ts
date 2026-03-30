import { createRouter, createWebHashHistory } from 'vue-router'

export const router = createRouter({
  history: createWebHashHistory(),
  routes: [
    {
      path: '/',
      redirect: '/dashboard'
    },
    {
      path: '/dashboard',
      component: () => import('@/pages/Dashboard.vue'),
      meta: { title: 'Dashboard' }
    },
    {
      path: '/sessions',
      component: () => import('@/pages/Sessions.vue'),
      meta: { title: 'Sessions' }
    },
    {
      path: '/sessions/:tool/:id',
      component: () => import('@/pages/SessionDetail.vue'),
      meta: { title: 'Session Detail' }
    },
    {
      path: '/migrate',
      component: () => import('@/pages/Migrate.vue'),
      meta: { title: 'Migration Wizard' }
    },
    {
      path: '/sync',
      component: () => import('@/pages/Sync.vue'),
      meta: { title: 'Sync' }
    },
    {
      path: '/doctor',
      component: () => import('@/pages/Doctor.vue'),
      meta: { title: 'Doctor' }
    },
    {
      path: '/settings',
      component: () => import('@/pages/Settings.vue'),
      meta: { title: 'Settings' }
    }
  ]
})
