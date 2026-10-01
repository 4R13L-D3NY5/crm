import { onMounted, onUnmounted } from 'vue'
import { useQueryClient } from '@tanstack/vue-query'
import { useAuthStore } from '@/modules/auth/stores/auth.store'

export function useTicketRealtime(selectedTicketId?: () => string | null) {
  const queryClient = useQueryClient()
  const authStore = useAuthStore()

  let pollingInterval: number | null = null

  onMounted(() => {
    type EchoChannel = { listen: (event: string, callback: () => void) => EchoChannel }
    const echo = (window as unknown as { Echo?: { channel: (name: string) => EchoChannel } }).Echo

    const orgId = authStore.user?.current_organization?.id

    if (echo && orgId) {
      echo.channel(`organizations.${orgId}`)
        .listen('.ticket.created', () => {
          queryClient.invalidateQueries({ queryKey: ['conversations'] })
        })
        .listen('.ticket.updated', () => {
          queryClient.invalidateQueries({ queryKey: ['conversations'] })
          const currentId = selectedTicketId?.()
          if (currentId) {
            queryClient.invalidateQueries({ queryKey: ['conversation', currentId] })
          }
        })
        .listen('.message.created', () => {
          queryClient.invalidateQueries({ queryKey: ['conversations'] })
          const currentId = selectedTicketId?.()
          if (currentId) {
            queryClient.invalidateQueries({ queryKey: ['conversation', currentId] })
          }
        })
    } else {
      // Fallback: Smart polling suave cada 8 segundos si no hay servidor WebSocket activo en local
      pollingInterval = window.setInterval(() => {
        queryClient.invalidateQueries({ queryKey: ['conversations'] })
        const currentId = selectedTicketId?.()
        if (currentId) {
          queryClient.invalidateQueries({ queryKey: ['conversation', currentId] })
        }
      }, 8000)
    }
  })

  onUnmounted(() => {
    if (pollingInterval) {
      clearInterval(pollingInterval)
    }
  })
}
