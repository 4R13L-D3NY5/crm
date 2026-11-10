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

export interface AiStatusRecommendation {
  suggested_status: string | null
  suggested_custom_status_id: string | null
  suggested_custom_status_name: string | null
  reason: string | null
  confidence: number
}

export interface AiTagRecommendation {
  name: string
  color: string
  reason: string
}

export interface AiExtractedContactData {
  first_name: string | null
  email: string | null
  ci: string | null
  city: string | null
  alternative_phone: string | null
  has_new_data: boolean
}

export interface AiSentimentAnalysis {
  sentiment: 'positive' | 'neutral' | 'hesitant' | 'frustrated' | 'angry'
  frustration_score: number
  is_frustrated: boolean
  reason: string
}

export interface AiConversationAnalysis {
  status_recommendation: AiStatusRecommendation | null
  tag_recommendations: AiTagRecommendation[]
  extracted_contact_data: AiExtractedContactData
  sentiment: AiSentimentAnalysis
}

export async function getConversationAiAnalysis(conversationId: string): Promise<AiConversationAnalysis> {
  const res = await http.get<{ data: AiConversationAnalysis }>(`/conversations/${conversationId}/ai/copilot/analysis`)
  return res.data.data
}

export async function applyAiStatus(conversationId: string, payload: { status?: string; custom_status_id?: string }): Promise<void> {
  await http.post(`/conversations/${conversationId}/ai/copilot/apply-status`, payload)
}

export async function applyAiTags(conversationId: string, tags: string[]): Promise<void> {
  await http.post(`/conversations/${conversationId}/ai/copilot/apply-tags`, { tags })
}

export async function applyAiContactData(conversationId: string, data: Partial<AiExtractedContactData>): Promise<void> {
  await http.post(`/conversations/${conversationId}/ai/copilot/apply-contact-data`, data)
}

export interface BulkTextPreviewPayload {
  text: string
  direction?: 'both' | 'inbound' | 'outbound'
  whatsapp_account_id?: string
  date_from?: string
  date_to?: string
  format?: 'xlsx' | 'csv'
}

export interface BulkTextPreviewSample {
  id: string
  contact_name: string
  contact_phone: string | null
  status: string
  channel: string
  account_name: string
}

export interface BulkTextPreviewResult {
  conversations_count: number
  contacts_count: number
  sample: BulkTextPreviewSample[]
}

export interface BulkTextClassifyPayload {
  text: string
  direction?: 'both' | 'inbound' | 'outbound'
  whatsapp_account_id?: string
  date_from?: string
  date_to?: string
  tag_ids?: string[]
  new_tag_name?: string
  category_ids?: string[]
  status?: 'open' | 'pending' | 'closed'
  custom_status_id?: string
}

export interface BulkTextClassifyResult {
  affected_conversations: number
  affected_contacts: number
  tags_applied: number
  categories_applied: number
}

export async function previewBulkTextMatches(
  payload: BulkTextPreviewPayload,
): Promise<BulkTextPreviewResult> {
  const response = await http.post<{ data: BulkTextPreviewResult }>(
    '/conversations/bulk-text-preview',
    payload,
  )
  return response.data.data
}

export async function executeBulkTextClassify(
  payload: BulkTextClassifyPayload,
): Promise<BulkTextClassifyResult> {
  const response = await http.post<{ success: boolean; data: BulkTextClassifyResult; message: string }>(
    '/conversations/bulk-text-classify',
    payload,
  )
  return response.data.data
}

export async function exportBulkTextMatches(
  payload: BulkTextPreviewPayload,
): Promise<{ blob: Blob; filename?: string }> {
  const response = await http.get('/conversations/bulk-text-export', {
    params: {
      ...payload,
      format: payload.format || 'xlsx',
    },
    responseType: 'blob',
  })

  let filename = ''
  const headers = response.headers as any
  const disposition = headers?.['content-disposition'] || (typeof headers?.get === 'function' ? headers.get('content-disposition') : '')
  if (disposition && typeof disposition === 'string' && disposition.includes('filename=')) {
    const matches = /filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/.exec(disposition)
    if (matches && matches[1]) {
      filename = matches[1].replace(/['"]/g, '').trim()
    }
  }

  return { blob: response.data, filename }
}

export async function exportBulkTextMatchesCsv(
  payload: BulkTextPreviewPayload,
): Promise<Blob> {
  const result = await exportBulkTextMatches(payload)
  return result.blob
}
