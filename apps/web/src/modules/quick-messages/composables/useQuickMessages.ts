import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import {
  createQuickMessage,
  deleteQuickMessage,
  getQuickMessages,
  updateQuickMessage,
} from '../api/quick-messages.api'
import type { QuickMessagePayload } from '../types/quick-message.types'

export function useQuickMessages() {
  return useQuery({
    queryKey: ['quick-messages'],
    queryFn: getQuickMessages,
  })
}

export function useQuickMessageMutations() {
  const queryClient = useQueryClient()
  const invalidate = () => queryClient.invalidateQueries({ queryKey: ['quick-messages'] })

  const createMutation = useMutation({
    mutationFn: (payload: QuickMessagePayload) => createQuickMessage(payload),
    onSuccess: invalidate,
  })

  const updateMutation = useMutation({
    mutationFn: ({ id, payload }: { id: string; payload: QuickMessagePayload }) =>
      updateQuickMessage(id, payload),
    onSuccess: invalidate,
  })

  const deleteMutation = useMutation({
    mutationFn: (id: string) => deleteQuickMessage(id),
    onSuccess: invalidate,
  })

  return {
    createMutation,
    updateMutation,
    deleteMutation,
  }
}
