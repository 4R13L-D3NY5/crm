import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'

import {
  getWhatsAppAccount,
  getWhatsAppEvents,
  updateWhatsAppAccount,
} from '../api/whatsapp.api'
import type { WhatsAppAccountPayload } from '../types/whatsapp.types'

export function useWhatsAppAccount() {
  return useQuery({
    queryKey: ['whatsapp-account'],
    queryFn: getWhatsAppAccount,
  })
}

export function useWhatsAppEvents() {
  return useQuery({
    queryKey: ['whatsapp-events'],
    queryFn: () => getWhatsAppEvents(),
    refetchInterval: 15000,
  })
}

export function useWhatsAppMutations() {
  const queryClient = useQueryClient()

  const saveAccountMutation = useMutation({
    mutationFn: (payload: WhatsAppAccountPayload) => updateWhatsAppAccount(payload),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['whatsapp-account'] })
      await queryClient.invalidateQueries({ queryKey: ['whatsapp-events'] })
    },
  })

  return {
    saveAccountMutation,
  }
}
