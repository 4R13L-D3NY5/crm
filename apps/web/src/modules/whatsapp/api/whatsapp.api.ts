import { http } from '@/shared/api/http'

import type {
  PaginatedWhatsAppEvents,
  WhatsAppAccount,
  WhatsAppAccountPayload,
} from '../types/whatsapp.types'

export async function getWhatsAppAccount(): Promise<WhatsAppAccount | null> {
  const response = await http.get<{ data: WhatsAppAccount | null }>('/whatsapp/accounts/current')

  return response.data.data
}

export async function updateWhatsAppAccount(
  payload: WhatsAppAccountPayload,
): Promise<WhatsAppAccount> {
  const response = await http.put<{ data: WhatsAppAccount }>(
    '/whatsapp/accounts/current',
    payload,
  )

  return response.data.data
}

export async function getWhatsAppEvents(
  perPage = 20,
): Promise<PaginatedWhatsAppEvents> {
  const response = await http.get<PaginatedWhatsAppEvents>('/whatsapp/events', {
    params: { per_page: perPage },
  })

  return response.data
}
