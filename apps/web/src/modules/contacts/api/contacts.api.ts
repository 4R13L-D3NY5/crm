import { http } from '@/shared/api/http'

import type {
  Contact,
  ContactFilters,
  ContactPayload,
  ContactTag,
  PaginatedContacts,
} from '../types/contact.types'

export async function getContacts(
  params: ContactFilters,
): Promise<PaginatedContacts> {
  const response = await http.get<PaginatedContacts>('/contacts', { params })

  return response.data
}

export async function getContact(id: string): Promise<Contact> {
  const response = await http.get<{ data: Contact }>(`/contacts/${id}`)

  return response.data.data
}

export async function createContact(payload: ContactPayload): Promise<Contact> {
  const response = await http.post<{ data: Contact }>('/contacts', payload)

  return response.data.data
}

export async function updateContact(
  id: string,
  payload: ContactPayload,
): Promise<Contact> {
  const response = await http.put<{ data: Contact }>(`/contacts/${id}`, payload)

  return response.data.data
}

export async function deleteContact(id: string): Promise<void> {
  await http.delete(`/contacts/${id}`)
}

export async function importContacts(
  payload: { contacts: Array<{ name: string; phone: string; email?: string; tags?: string[] }> },
): Promise<{ data: { created_count: number; total_processed: number }; message?: string }> {
  const response = await http.post<{ data: { created_count: number; total_processed: number }; message?: string }>(
    '/contacts/import',
    payload,
  )

  return response.data
}

export async function getTags(): Promise<ContactTag[]> {
  const response = await http.get<{ data: ContactTag[] }>('/tags')

  return response.data.data
}

export async function createTag(payload: { name: string; color_hex?: string }): Promise<ContactTag> {
  const response = await http.post<{ data: ContactTag }>('/tags', payload)

  return response.data.data
}

export async function updateTag(
  id: string,
  payload: { name: string; color_hex?: string },
): Promise<ContactTag> {
  const response = await http.put<{ data: ContactTag }>(`/tags/${id}`, payload)

  return response.data.data
}

export async function deleteTag(id: string): Promise<void> {
  await http.delete(`/tags/${id}`)
}

export async function sendBulkMessage(payload: {
  contact_ids: string[]
  whatsapp_account_id?: string | null
  message: string
}): Promise<{
  data: {
    dispatched_count: number
    skipped_count: number
    total: number
  }
  message: string
}> {
  const response = await http.post<{
    data: {
      dispatched_count: number
      skipped_count: number
      total: number
    }
    message: string
  }>('/contacts/bulk-message', payload)

  return response.data
}

