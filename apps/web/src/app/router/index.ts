import { createRouter, createWebHistory } from 'vue-router'

import { pinia } from '@/app/plugins/pinia'
import { useAuthStore } from '@/modules/auth/stores/auth.store'

import { routes } from './routes'

const router = createRouter({
  history: createWebHistory(),
  routes,
})

router.beforeEach(async (to) => {
  const authStore = useAuthStore(pinia)

  if (!authStore.isReady) {
    await authStore.hydrateSession()
  }

  if (to.meta.requiresAuth && !authStore.isAuthenticated) {
    return { name: 'login' }
  }

  if (to.name === 'superadmin.portal' || to.path.includes('superadmin') || to.path.includes('saas-billing') || to.path.includes('master-control')) {
    if (authStore.user?.email !== 'admin@crm.local') {
      return { path: '/app/conversations' }
    }
  }

  const requiredPermission = typeof to.meta.permission === 'string'
    ? to.meta.permission
    : null

  if (
    to.meta.requiresAuth
    && requiredPermission
    && !authStore.hasPermission(requiredPermission)
  ) {
    return { name: 'dashboard' }
  }

  if (to.name === 'login' && authStore.isAuthenticated) {
    return { name: 'dashboard' }
  }

  return true
})

export default router
