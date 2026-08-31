import { http } from '@/shared/api/http'

import type {
  Contact,
  ContactFilters,
  ContactPayload,
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
