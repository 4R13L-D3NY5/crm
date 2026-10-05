import type { Paginated } from '@/shared/types/pagination.types'

export type WhatsAppStatus = 'CONNECTED' | 'CONNECTING' | 'DISCONNECTED'
export type WhatsAppSessionType = 'baileys_qr' | 'qr_baileys' | 'qr' | 'meta_cloud' | 'facebook' | 'instagram' | 'tiktok'

export interface WhatsAppAccount {
  id: string
  organization_id: string
  name: string
  session_type: WhatsAppSessionType
  status: WhatsAppStatus
  qrcode_raw: string | null
  phone_number_id?: string | null
  display_phone_number?: string | null
  business_account_id?: string | null
  verify_token?: string
  has_access_token?: boolean
  is_active: boolean
  webhook_url?: string | null
  social_webhook_url?: string | null
  last_connected_at?: string | null
  created_at?: string | null
  updated_at?: string | null
}

export interface CreateWhatsAppAccountPayload {
  name: string
  session_type?: WhatsAppSessionType
  display_phone_number?: string
  phone_number_id?: string
  business_account_id?: string
  access_token?: string
  verify_token?: string
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

export interface QrResponse {
  account_id: string
  status: WhatsAppStatus
  qrcode_raw: string
  expires_in_seconds: number
  generated_at: string
}

export interface SimulateIncomingPayload {
  from_phone: string
  from_name?: string
  message: string
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
