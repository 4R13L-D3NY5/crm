import { http } from '@/shared/api/http'
import type { QuickMessage, QuickMessagePayload } from '../types/quick-message.types'

export async function getQuickMessages(): Promise<QuickMessage[]> {
  const response = await http.get<{ data: QuickMessage[] }>('/quick-messages')

  return response.data.data
}

export async function createQuickMessage(payload: QuickMessagePayload): Promise<QuickMessage> {
  const response = await http.post<{ data: QuickMessage }>('/quick-messages', payload)

  return response.data.data
}

export async function updateQuickMessage(id: string, payload: QuickMessagePayload): Promise<QuickMessage> {
  const response = await http.put<{ data: QuickMessage }>(`/quick-messages/${id}`, payload)

  return response.data.data
}

export async function deleteQuickMessage(id: string): Promise<void> {
  await http.delete(`/quick-messages/${id}`)
}
