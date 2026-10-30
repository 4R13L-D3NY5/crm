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

  const requiresAuth = to.matched.some((record) => Boolean(record.meta?.requiresAuth))
  const requiresAdmin = to.matched.some((record) => Boolean(record.meta?.requiresAdmin))
  const requiresSuperAdmin = to.matched.some((record) => Boolean(record.meta?.requiresSuperAdmin))

  if (requiresAuth && !authStore.isAuthenticated) {
    return { name: 'login' }
  }

  if (
    to.name === 'superadmin.portal'
    || to.path.includes('superadmin')
    || to.path.includes('saas-billing')
    || to.path.includes('master-control')
    || requiresSuperAdmin
  ) {
    if (!authStore.isSuperAdmin) {
      return { path: '/app/conversations' }
    }
  }

  if (requiresAdmin && !authStore.isAdmin) {
    return { path: '/app/conversations' }
  }

  const matchedPermissions = to.matched
    .map((record) => record.meta?.permission)
    .filter((perm): perm is string => typeof perm === 'string')

  for (const perm of matchedPermissions) {
    if (requiresAuth && !authStore.hasPermission(perm)) {
      return { path: '/app/conversations' }
    }
  }

  if (to.name === 'login' && authStore.isAuthenticated) {
    return { name: 'dashboard' }
  }

  return true
})

export default router
