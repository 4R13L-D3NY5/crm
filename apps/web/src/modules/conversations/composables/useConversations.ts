import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import type { MaybeRefOrGetter } from 'vue'
import { computed, toValue } from 'vue'

import {
  acceptTicket,
  assignConversation,
  closeTicket,
  createConversation,
  createInternalMessage,
  createWhatsAppMessage,
  sendInstagramMessage,
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
    refetchInterval: 4000,
  })
}

export function useConversation(conversationId: MaybeRefOrGetter<string>) {
  return useQuery({
    queryKey: computed(() => ['conversation', toValue(conversationId)]),
    queryFn: () => getConversation(toValue(conversationId)),
    enabled: computed(() => Boolean(toValue(conversationId))),
    refetchInterval: 3000,
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

  const invalidate = async () => {
    await queryClient.invalidateQueries({ queryKey: ['conversations'] })

    const currentId = toValue(conversationId)

    if (currentId) {
      await queryClient.invalidateQueries({ queryKey: ['conversation', currentId] })
    }
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

  const messageMutation = useMutation({
    mutationFn: ({
      id,
      payload,
    }: {
      id: string
      payload: InternalMessagePayload
    }) => createInternalMessage(id, payload),
    onSuccess: invalidate,
  })

  const whatsappMessageMutation = useMutation({
    mutationFn: ({
      id,
      payload,
    }: {
      id: string
      payload: WhatsAppMessagePayload
    }) => createWhatsAppMessage(id, payload),
    onSuccess: invalidate,
  })

  const instagramMessageMutation = useMutation({
    mutationFn: ({
      id,
      payload,
    }: {
      id: string
      payload: { body: string }
    }) => sendInstagramMessage(id, payload),
    onSuccess: invalidate,
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
    closeMutation,
    messageMutation,
    whatsappMessageMutation,
    instagramMessageMutation,
    retryWhatsAppMutation,
    aiSuggestionMutation,
    assignMutation,
    statusMutation,
  }
}
