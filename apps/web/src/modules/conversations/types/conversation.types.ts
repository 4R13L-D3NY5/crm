import type { Contact } from '@/modules/contacts/types/contact.types'
import type { Company } from '@/modules/companies/types/company.types'
import type { Paginated } from '@/shared/types/pagination.types'

export interface ConversationAssignee {
  id: string
  name: string
  email?: string
}

export interface QueueSummary {
  id: string
  name: string
  color: string
}

export interface ConversationMessage {
  id: string
  conversation_id: string
  direction: 'internal' | 'inbound' | 'outbound'
  is_internal?: boolean
  message_type: string
  message_status?: string | null
  delivery_status?: 'pending' | 'sent' | 'delivered' | 'read' | 'failed'
  error_message?: string | null
  body: string
  media_url?: string | null
  media_type?: 'image' | 'audio' | 'video' | 'document' | 'ptt' | null
  media_duration_seconds?: number | null
  transcription?: string | null
  quoted_message?: {
    id: string
    body: string
    user_name?: string
  } | null
  sent_at: string | null
  created_at: string | null
  user: ConversationAssignee | null
}

export interface ConversationSummaryRelation {
  id: string
  name: string
  phone?: string | null
  email?: string | null
}

export interface Conversation {
  id: string
  organization_id: string
  channel: 'manual' | 'whatsapp' | 'email' | 'facebook' | 'instagram' | 'tiktok'
  status: 'open' | 'pending' | 'closed'
  subject: string | null
  unread_count?: number
  is_group?: boolean
  last_message_at: string | null
  closed_at?: string | null
  rating?: number | null
  feedback?: string | null
  queue?: QueueSummary | null
  contact: ConversationSummaryRelation | null
  company: ConversationSummaryRelation | null
  assignee?: ConversationAssignee | null
  assignment?: ConversationAssignee | null
  latest_message?: ConversationMessage | null
  messages: ConversationMessage[]
  created_at: string | null
  updated_at: string | null
}

export interface QuickMessage {
  id: string
  shortcut: string
  message: string
  media_url?: string | null
  media_type?: string | null
  is_general: boolean
}

export interface UserOption {
  id: string
  name: string
  email: string
}

export interface ConversationFilters {
  tab?: 'attending' | 'pending' | 'closed'
  queue_id?: string
  search?: string
  status?: string
  assigned_to?: string
  page?: number
  per_page?: number
}

export interface AcceptTicketPayload {
  conversation_id: string
}

export interface TransferTicketPayload {
  assigned_to_user_id?: string | null
  queue_id?: string | null
  transfer_note?: string | null
}

export interface CloseTicketPayload {
  rating?: number | null
  feedback?: string | null
}

export interface CreateConversationPayload {
  subject: string
  status: 'open' | 'pending' | 'closed'
  channel: 'manual' | 'whatsapp' | 'email'
  queue_id?: string | null
  contact_id: string | null
  company_id: string | null
  message: string
  assigned_to_user_id: string | null
}

export interface InternalMessagePayload {
  body: string
}

export interface WhatsAppMessagePayload {
  body: string
  media_url?: string | null
  media_type?: string | null
}

export type PaginatedConversations = Paginated<Conversation>
export type ConversationFormContact = Contact
export type ConversationFormCompany = Company

export interface AssignmentPayload {
  assigned_to_user_id: string | null
}

export interface StatusPayload {
  status: 'open' | 'pending' | 'closed'
}

export interface AiReplySuggestionRun {
  id: string
  suggestion: string
  tokens_used?: number
}
