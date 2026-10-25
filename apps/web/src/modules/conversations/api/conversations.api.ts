import { http } from '@/shared/api/http'

import type { Company } from '@/modules/companies/types/company.types'
import type { Contact } from '@/modules/contacts/types/contact.types'

import type {
  AiReplySuggestionRun,
  AssignmentPayload,
  CloseTicketPayload,
  Conversation,
  ConversationFilters,
  ConversationMessage,
  CreateConversationPayload,
  InternalMessagePayload,
  PaginatedConversations,
  QuickMessage,
  QueueSummary,
  StatusPayload,
  TransferTicketPayload,
  UserOption,
  WhatsAppMessagePayload,
} from '../types/conversation.types'

export async function getConversations(
  params: ConversationFilters,
): Promise<PaginatedConversations> {
  const response = await http.get<PaginatedConversations>('/conversations', { params })

  return response.data
}

export async function getConversation(id: string): Promise<Conversation> {
  const response = await http.get<{ data: Conversation }>(`/conversations/${id}`)

  return response.data.data
}

export async function createConversation(
  payload: CreateConversationPayload,
): Promise<Conversation> {
  const response = await http.post<{ data: Conversation }>('/conversations', payload)

  return response.data.data
}

// Acciones de Ticket estilo Whaticket
export async function acceptTicket(conversationId: string): Promise<Conversation> {
  const response = await http.post<{ data: Conversation }>(`/conversations/${conversationId}/accept`)
  return response.data.data
}

export async function transferTicket(
  conversationId: string,
  payload: TransferTicketPayload,
): Promise<Conversation> {
  const response = await http.post<{ data: Conversation }>(`/conversations/${conversationId}/transfer`, payload)
  return response.data.data
}

export async function releaseTicket(
  conversationId: string,
  payload: { reason?: string } = {},
): Promise<Conversation> {
  const response = await http.post<{ data: Conversation }>(`/conversations/${conversationId}/release`, payload)
  return response.data.data
}

export async function closeTicket(
  conversationId: string,
  payload: CloseTicketPayload = {},
): Promise<Conversation> {
  const response = await http.post<{ data: Conversation }>(`/conversations/${conversationId}/close`, payload)
  return response.data.data
}

export async function reopenTicket(conversationId: string): Promise<Conversation> {
  const response = await http.post<{ data: Conversation }>(`/conversations/${conversationId}/reopen`)
  return response.data.data
}

export async function markConversationAsRead(
  conversationId: string,
): Promise<{ success: boolean; unread_count: number }> {
  const response = await http.post<{ success: boolean; unread_count: number }>(
    `/conversations/${conversationId}/read`,
  )
  return response.data
}

// Mensajes
export async function createInternalMessage(
  conversationId: string,
  payload: InternalMessagePayload,
): Promise<void> {
  await http.post(`/conversations/${conversationId}/messages/internal`, payload)
}

export async function createWhatsAppMessage(
  conversationId: string,
  payload: WhatsAppMessagePayload | FormData,
): Promise<ConversationMessage> {
  const isFormData = payload instanceof FormData
  const response = await http.post<{ data: ConversationMessage }>(
    `/conversations/${conversationId}/messages/whatsapp`,
    payload,
    isFormData
      ? {
          headers: {
            'Content-Type': 'multipart/form-data',
          },
        }
      : undefined,
  )

  return response.data.data
}

export async function sendInstagramMessage(
  conversationId: string,
  payload: { body: string },
): Promise<ConversationMessage> {
  const response = await http.post<{ data: ConversationMessage }>(
    `/social/instagram/conversations/${conversationId}/messages`,
    payload,
  )
  return response.data.data
}

export async function sendFacebookMessage(
  conversationId: string,
  payload: { body: string },
): Promise<ConversationMessage> {
  const response = await http.post<{ data: ConversationMessage }>(
    `/social/facebook/conversations/${conversationId}/messages`,
    payload,
  )
  return response.data.data
}

export async function retryWhatsAppMessage(
  conversationId: string,
  messageId: string,
): Promise<void> {
  await http.post(`/conversations/${conversationId}/messages/${messageId}/whatsapp-retry`, {})
}

export async function editMessage(
  conversationId: string,
  messageId: string,
  body: string,
): Promise<ConversationMessage> {
  const response = await http.put<{ data: ConversationMessage }>(
    `/conversations/${conversationId}/messages/${messageId}`,
    { body },
  )
  return response.data.data
}

// Hentle-AI Copilot Endpoints
export async function getHentleAiSuggestion(
  conversationId: string,
  instruction?: string,
): Promise<{ suggestion: string; confidence: number; source: string }> {
  const response = await http.post<{ data: { suggestion: string; confidence: number; source: string } }>(
    `/conversations/${conversationId}/ai/copilot/suggest`,
    { instruction },
  )
  return response.data.data
}

export async function getHentleAiSummary(
  conversationId: string,
): Promise<{ summary: string; key_points: string[] }> {
  const response = await http.get<{ data: { summary: string; key_points: string[] } }>(
    `/conversations/${conversationId}/ai/copilot/summary`,
  )
  return response.data.data
}

export async function generateReplySuggestion(
  conversationId: string,
): Promise<AiReplySuggestionRun> {
  const response = await http.post<{ data: AiReplySuggestionRun }>(
    `/conversations/${conversationId}/ai/reply-suggestion`,
    {},
  )

  return response.data.data
}

export async function assignConversation(
  conversationId: string,
  payload: AssignmentPayload,
): Promise<void> {
  await http.put(`/conversations/${conversationId}/assignment`, payload)
}

export async function updateConversationStatus(
  conversationId: string,
  payload: StatusPayload,
): Promise<void> {
  await http.put(`/conversations/${conversationId}/status`, payload)
}

// Catálogos y Respuestas Rápidas
export async function getQueues(): Promise<QueueSummary[]> {
  const response = await http.get<{ data: QueueSummary[] }>('/queues')
  return response.data.data
}

export async function getQuickMessages(): Promise<QuickMessage[]> {
  const response = await http.get<{ data: QuickMessage[] }>('/quick-messages')
  return response.data.data
}

export async function getConversationContacts(): Promise<Contact[]> {
  const response = await http.get<{ data: Contact[] }>('/contacts', {
    params: { per_page: 100 },
  })

  return response.data.data
}

export async function getConversationCompanies(): Promise<Company[]> {
  const response = await http.get<{ data: Company[] }>('/companies', {
    params: { per_page: 100 },
  })

  return response.data.data
}

export async function getConversationUsers(): Promise<UserOption[]> {
  const response = await http.get<{ data: UserOption[] }>('/users')

  return response.data.data
}

export async function openPrivateChat(conversationId: string): Promise<Conversation> {
  const response = await http.post<{ data: Conversation }>(`/social/conversations/${conversationId}/private-chat`)
  return response.data.data
}
