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
