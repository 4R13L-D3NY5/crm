import { computed } from 'vue'

import { useAuthStore } from '../stores/auth.store'

export function useCan(permission: string) {
  const authStore = useAuthStore()

  return computed(() => authStore.hasPermission(permission))
}
