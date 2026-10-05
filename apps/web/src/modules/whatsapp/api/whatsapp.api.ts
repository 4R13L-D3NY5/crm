import { http } from '@/shared/api/http'
import type {
  CreateWhatsAppAccountPayload,
  PaginatedWhatsAppEvents,
  QrResponse,
  SimulateIncomingPayload,
  WhatsAppAccount,
  WhatsAppAccountPayload,
} from '../types/whatsapp.types'

export async function getWhatsAppAccounts(): Promise<WhatsAppAccount[]> {
  const response = await http.get<{ data: WhatsAppAccount[] }>('/whatsapp/accounts')
  return response.data.data
}

export async function createWhatsAppAccount(
  payload: CreateWhatsAppAccountPayload,
): Promise<WhatsAppAccount> {
  const response = await http.post<{ data: WhatsAppAccount; message: string }>(
    '/whatsapp/accounts',
    payload,
  )
  return response.data.data
}

export async function deleteWhatsAppAccount(id: string): Promise<void> {
  await http.delete(`/whatsapp/accounts/${id}`)
}

export async function getWhatsAppQr(id: string): Promise<QrResponse> {
  const response = await http.get<{ data: QrResponse; message: string }>(
    `/whatsapp/accounts/${id}/qr`,
  )
  return response.data.data
}

export async function simulateScan(id: string, phoneNumber?: string): Promise<WhatsAppAccount> {
  const response = await http.post<{ data: WhatsAppAccount; message: string }>(
    `/whatsapp/accounts/${id}/simulate-scan`,
    { phone_number: phoneNumber },
  )
  return response.data.data
}

export async function getWhatsAppPairingCode(id: string, phone: string): Promise<string> {
  const response = await http.post<{ data: { pairing_code: string }; message: string }>(
    `/whatsapp/accounts/${id}/pairing-code`,
    { phone },
  )
  return response.data.data.pairing_code
}

export async function disconnectWhatsApp(id: string): Promise<void> {
  await http.post(`/whatsapp/accounts/${id}/disconnect`)
}

export async function simulateIncomingMessage(
  id: string,
  payload: SimulateIncomingPayload,
): Promise<{ conversation_id: string; message: any }> {
  const response = await http.post<{ data: any; message: string }>(
    `/whatsapp/accounts/${id}/simulate-incoming`,
    payload,
  )
  return response.data.data
}

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

export async function syncFacebookMessages(
  accountId?: string,
): Promise<{ message: string; imported?: number }> {
  const response = await http.post<{ message: string; imported?: number }>(
    '/social/facebook/sync',
    { account_id: accountId },
  )
  return response.data
}

export async function syncInstagramMessages(
  accountId?: string,
): Promise<{ message: string; imported?: number }> {
  const response = await http.post<{ message: string; imported?: number }>(
    '/social/instagram/sync',
    { account_id: accountId },
  )
  return response.data
}

