import type { Paginated } from '@/shared/types/pagination.types'

export interface WhatsAppAccount {
  id: string
  organization_id: string
  name: string
  phone_number_id: string
  display_phone_number: string | null
  business_account_id: string | null
  verify_token: string
  has_access_token: boolean
  is_active: boolean
  created_at: string | null
  updated_at: string | null
}

export interface WhatsAppAccountPayload {
  name: string
  phone_number_id: string
  display_phone_number: string
  business_account_id: string
  verify_token: string
  access_token: string
  is_active: boolean
}

export interface WhatsAppWebhookEvent {
  id: string
  whatsapp_account_id: string | null
  event_type: string | null
  processing_status: 'pending' | 'processed' | 'failed'
  processed_at: string | null
  error_message: string | null
  payload: Record<string, unknown>
  created_at: string | null
}

export type PaginatedWhatsAppEvents = Paginated<WhatsAppWebhookEvent>
