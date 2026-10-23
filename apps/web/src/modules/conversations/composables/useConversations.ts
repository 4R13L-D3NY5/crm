import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { useAuthStore } from '@/modules/auth/stores/auth.store'
import type { MaybeRefOrGetter } from 'vue'
import { computed, toValue } from 'vue'

import {
  acceptTicket,
  assignConversation,
  closeTicket,
  reopenTicket,
  releaseTicket,
  createConversation,
  createInternalMessage,
  createWhatsAppMessage,
  sendInstagramMessage,
  sendFacebookMessage,
  generateReplySuggestion,
  getConversation,
  getConversationCompanies,
  getConversationContacts,
  getConversations,
  getConversationUsers,
  getQueues,
  getQuickMessages,
  retryWhatsAppMessage,
  transferTicket,
  updateConversationStatus,
} from '../api/conversations.api'
import type {
  AssignmentPayload,
  CloseTicketPayload,
  Conversation,
  ConversationMessage,
  ConversationFilters,
  CreateConversationPayload,
  InternalMessagePayload,
  StatusPayload,
  TransferTicketPayload,
  WhatsAppMessagePayload,
} from '../types/conversation.types'

export function useConversations(filters: MaybeRefOrGetter<ConversationFilters>) {
  return useQuery({
    queryKey: computed(() => ['conversations', toValue(filters)]),
    queryFn: () => getConversations(toValue(filters)),
    refetchInterval: 3000,
    refetchIntervalInBackground: true,
    staleTime: 0,
  })
}

export function useConversation(conversationId: MaybeRefOrGetter<string>) {
  return useQuery({
    queryKey: computed(() => ['conversation', toValue(conversationId)]),
    queryFn: () => getConversation(toValue(conversationId)),
    enabled: computed(() => Boolean(toValue(conversationId))),
    refetchInterval: 2500,
    refetchIntervalInBackground: true,
    staleTime: 0,
  })
}

export function useQueuesQuery() {
  return useQuery({
    queryKey: ['queues'],
    queryFn: getQueues,
  })
}

export function useQuickMessagesQuery() {
  return useQuery({
    queryKey: ['quick-messages'],
    queryFn: getQuickMessages,
  })
}

export function useConversationFormOptions() {
  const contactsQuery = useQuery({
    queryKey: ['conversations-form', 'contacts'],
    queryFn: getConversationContacts,
  })

  const companiesQuery = useQuery({
    queryKey: ['conversations-form', 'companies'],
    queryFn: getConversationCompanies,
  })

  const usersQuery = useQuery({
    queryKey: ['conversations-form', 'users'],
    queryFn: getConversationUsers,
  })

  return {
    contactsQuery,
    companiesQuery,
    usersQuery,
  }
}

export function useConversationMutations(conversationId: MaybeRefOrGetter<string>) {
  const queryClient = useQueryClient()
  const authStore = useAuthStore()

  const invalidate = async () => {
    await queryClient.invalidateQueries({ queryKey: ['conversations'] })

    const currentId = toValue(conversationId)

    if (currentId) {
      await queryClient.invalidateQueries({ queryKey: ['conversation', currentId] })
    }
  }

  const createOptimisticMessage = (
    convId: string,
    body: string,
    isInternal: boolean = false,
    mediaType?: string | null,
    mediaUrl?: string | null,
  ): ConversationMessage => {
    const nowIso = new Date().toISOString()
    return {
      id: `temp_${Date.now()}_${Math.random().toString(36).substring(2, 8)}`,
      conversation_id: convId,
      direction: isInternal ? 'internal' : 'outbound',
      is_internal: isInternal,
      message_type: mediaType || 'text',
      message_status: 'pending',
      delivery_status: 'pending',
      body,
      media_url: mediaUrl,
      media_type: (mediaType as any) || (mediaUrl ? 'image' : null),
      sent_at: nowIso,
      created_at: nowIso,
      user: authStore.user ? {
        id: authStore.user.id,
        name: authStore.user.name,
        email: authStore.user.email,
      } : null,
    }
  }

  const applyOptimisticMessage = (convId: string, optimisticMsg: ConversationMessage) => {
    queryClient.setQueryData<Conversation>(['conversation', convId], (old) => {
      if (!old) return old
      return {
        ...old,
        last_message_at: optimisticMsg.sent_at,
        messages: [...(old.messages || []), optimisticMsg],
      }
    })
  }

  const replaceOptimisticMessage = (convId: string, tempId: string, realMsg: ConversationMessage) => {
    queryClient.setQueryData<Conversation>(['conversation', convId], (old) => {
      if (!old) return old
      const exists = (old.messages || []).some((m) => m.id === realMsg.id)
      let messages = (old.messages || []).map((m) => (m.id === tempId ? realMsg : m))
      if (exists) {
        messages = messages.filter((m) => m.id !== tempId)
      }
      return {
        ...old,
        last_message_at: realMsg.sent_at || old.last_message_at,
        messages,
      }
    })
    queryClient.invalidateQueries({ queryKey: ['conversations'] })
  }

  const rollbackOptimisticMessage = (convId: string, tempId: string) => {
    queryClient.setQueryData<Conversation>(['conversation', convId], (old) => {
      if (!old) return old
      return {
        ...old,
        messages: (old.messages || []).filter((m) => m.id !== tempId),
      }
    })
  }

  const createMutation = useMutation({
    mutationFn: (payload: CreateConversationPayload) => createConversation(payload),
    onSuccess: invalidate,
  })

  const acceptMutation = useMutation({
    mutationFn: (id: string) => acceptTicket(id),
    onSuccess: invalidate,
  })

  const transferMutation = useMutation({
    mutationFn: ({ id, payload }: { id: string; payload: TransferTicketPayload }) =>
      transferTicket(id, payload),
    onSuccess: invalidate,
  })

  const closeMutation = useMutation({
    mutationFn: ({ id, payload }: { id: string; payload?: CloseTicketPayload }) =>
      closeTicket(id, payload),
    onSuccess: invalidate,
  })

  const reopenMutation = useMutation({
    mutationFn: (id: string) => reopenTicket(id),
    onSuccess: invalidate,
  })

  const releaseMutation = useMutation({
    mutationFn: ({ id, payload }: { id: string; payload?: { reason?: string } }) =>
      releaseTicket(id, payload),
    onSuccess: invalidate,
  })

  const messageMutation = useMutation({
    mutationFn: ({
      id,
      payload,
    }: {
      id: string
      payload: InternalMessagePayload
    }) => createInternalMessage(id, payload),
    onMutate: async ({ id, payload }) => {
      await queryClient.cancelQueries({ queryKey: ['conversation', id] })
      const optimisticMsg = createOptimisticMessage(id, payload.body, true, 'text', null)
      applyOptimisticMessage(id, optimisticMsg)
      return { tempId: optimisticMsg.id, id }
    },
    onSuccess: (_data, variables, context) => {
      if (context?.tempId) {
        queryClient.setQueryData<Conversation>(['conversation', variables.id], (old) => {
          if (!old) return old
          return {
            ...old,
            messages: (old.messages || []).map((m) =>
              m.id === context.tempId ? { ...m, delivery_status: 'delivered', message_status: 'delivered' } : m
            ),
          }
        })
      }
      queryClient.invalidateQueries({ queryKey: ['conversations'] })
    },
    onError: (_err, variables, context) => {
      if (context?.tempId) {
        rollbackOptimisticMessage(variables.id, context.tempId)
      }
    },
  })

  const whatsappMessageMutation = useMutation({
    mutationFn: ({
      id,
      payload,
    }: {
      id: string
      payload: WhatsAppMessagePayload | FormData
    }) => createWhatsAppMessage(id, payload),
    onMutate: async ({ id, payload }) => {
      await queryClient.cancelQueries({ queryKey: ['conversation', id] })
      const bodyText = payload instanceof FormData ? (payload.get('body') as string) : (payload.body || '')
      const mediaType = payload instanceof FormData ? (payload.get('media_type') as string) : (payload.media_type || 'text')
      const file = payload instanceof FormData ? (payload.get('file') as File | null) : null
      const mediaUrl = file ? URL.createObjectURL(file) : null

      const optimisticMsg = createOptimisticMessage(id, bodyText, false, mediaType, mediaUrl)
      applyOptimisticMessage(id, optimisticMsg)

      return { tempId: optimisticMsg.id, id }
    },
    onSuccess: (realMsg, variables, context) => {
      if (context?.tempId) {
        replaceOptimisticMessage(variables.id, context.tempId, realMsg)
      } else {
        invalidate()
      }
    },
    onError: (_err, variables, context) => {
      if (context?.tempId) {
        rollbackOptimisticMessage(variables.id, context.tempId)
      }
    },
  })

  const instagramMessageMutation = useMutation({
    mutationFn: ({
      id,
      payload,
    }: {
      id: string
      payload: { body: string }
    }) => sendInstagramMessage(id, payload),
    onMutate: async ({ id, payload }) => {
      await queryClient.cancelQueries({ queryKey: ['conversation', id] })
      const optimisticMsg = createOptimisticMessage(id, payload.body, false, 'text', null)
      applyOptimisticMessage(id, optimisticMsg)
      return { tempId: optimisticMsg.id, id }
    },
    onSuccess: (realMsg, variables, context) => {
      if (context?.tempId) {
        replaceOptimisticMessage(variables.id, context.tempId, realMsg)
      } else {
        invalidate()
      }
    },
    onError: (_err, variables, context) => {
      if (context?.tempId) {
        rollbackOptimisticMessage(variables.id, context.tempId)
      }
    },
  })

  const facebookMessageMutation = useMutation({
    mutationFn: ({
      id,
      payload,
    }: {
      id: string
      payload: { body: string }
    }) => sendFacebookMessage(id, payload),
    onMutate: async ({ id, payload }) => {
      await queryClient.cancelQueries({ queryKey: ['conversation', id] })
      const optimisticMsg = createOptimisticMessage(id, payload.body, false, 'text', null)
      applyOptimisticMessage(id, optimisticMsg)
      return { tempId: optimisticMsg.id, id }
    },
    onSuccess: (realMsg, variables, context) => {
      if (context?.tempId) {
        replaceOptimisticMessage(variables.id, context.tempId, realMsg)
      } else {
        invalidate()
      }
    },
    onError: (_err, variables, context) => {
      if (context?.tempId) {
        rollbackOptimisticMessage(variables.id, context.tempId)
      }
    },
  })

  const retryWhatsAppMutation = useMutation({
    mutationFn: ({
      conversationId,
      messageId,
    }: {
      conversationId: string
      messageId: string
    }) => retryWhatsAppMessage(conversationId, messageId),
    onSuccess: invalidate,
  })

  const aiSuggestionMutation = useMutation({
    mutationFn: (conversationId: string) => generateReplySuggestion(conversationId),
  })

  const assignMutation = useMutation({
    mutationFn: ({
      id,
      payload,
    }: {
      id: string
      payload: AssignmentPayload
    }) => assignConversation(id, payload),
    onSuccess: invalidate,
  })

  const statusMutation = useMutation({
    mutationFn: ({
      id,
      payload,
    }: {
      id: string
      payload: StatusPayload
    }) => updateConversationStatus(id, payload),
    onSuccess: invalidate,
  })

  return {
    createMutation,
    acceptMutation,
    transferMutation,
    releaseMutation,
    closeMutation,
    reopenMutation,
    messageMutation,
    whatsappMessageMutation,
    instagramMessageMutation,
    facebookMessageMutation,
    retryWhatsAppMutation,
    aiSuggestionMutation,
    assignMutation,
    statusMutation,
  }
}
