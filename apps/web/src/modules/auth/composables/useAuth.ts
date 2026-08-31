import { storeToRefs } from 'pinia'

import { useAuthStore } from '../stores/auth.store'

export function useAuth() {
  const authStore = useAuthStore()
  const { user, organizations, isReady, isLoading, errorMessage } =
    storeToRefs(authStore)

  return {
    authStore,
    user,
    organizations,
    isReady,
    isLoading,
    errorMessage,
    isAuthenticated: authStore.isAuthenticated,
    currentOrganization: authStore.currentOrganization,
  }
}
