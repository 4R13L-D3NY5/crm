import type { Contact } from '@/modules/contacts/types/contact.types'
import type { Company } from '@/modules/companies/types/company.types'
import type { Paginated } from '@/shared/types/pagination.types'

export interface AdReferralMetadata {
  source_type: 'ad' | 'post' | string
  source_id?: string | null
  source_url?: string | null
  headline?: string | null
  body?: string | null
  media_type?: 'image' | 'video' | 'text' | string | null
  image_url?: string | null
  video_url?: string | null
  thumbnail_base64?: string | null
  ctwa_clid?: string | null
}

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
  is_edited?: boolean
  edited_at?: string | null
  message_type: string
  message_status?: string | null
  delivery_status?: 'pending' | 'sent' | 'delivered' | 'read' | 'failed'
  error_message?: string | null
  body: string
  media_url?: string | null
  media_type?: 'image' | 'audio' | 'video' | 'document' | 'ptt' | 'sticker' | null
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
  metadata?: {
    referral?: AdReferralMetadata
    [key: string]: any
  } | null
}

export interface ConversationSummaryRelation {
  first_name?: string | null
  last_name?: string | null
  custom_fields?: Record<string, any> | null
  id: string
  name: string
  phone?: string | null
  email?: string | null
  tags?: Array<{ id: string; name: string; slug?: string; color?: string | null }>
}

export interface Conversation {
  id: string
  organization_id: string
  channel: 'manual' | 'whatsapp' | 'email' | 'facebook' | 'instagram' | 'tiktok'
  whatsapp_account_id?: string | null
  channel_account?: {
    id: string
    name: string
    display_phone_number?: string | null
    session_type?: string
  } | null
  status: 'open' | 'pending' | 'closed'
  subject: string | null
  unread_count?: number
  is_group?: boolean
  last_message_at: string | null
  closed_at?: string | null
  rating?: number | null
  feedback?: string | null
  queue?: QueueSummary | null
  custom_status_id?: string | null
  custom_status?: {
    id: string
    name: string
    color: string
    icon?: string | null
    stage_type?: string
  } | null
  categories?: Array<{
    id: string
    name: string
    code?: string | null
    color: string
    icon?: string | null
  }>
  contact: ConversationSummaryRelation | null
  company: ConversationSummaryRelation | null
  assigned_to_user_id?: string | null
  assignee?: ConversationAssignee | null
  assignment?: ConversationAssignee | null
  latest_message?: ConversationMessage | null
  messages: ConversationMessage[]
  origin_referral?: AdReferralMetadata | null
  metadata?: {
    origin_referral?: AdReferralMetadata
    [key: string]: any
  } | null
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
  body?: string
  media_url?: string | null
  media_type?: string | null
  file?: File | null
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
