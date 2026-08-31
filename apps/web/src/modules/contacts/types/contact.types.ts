import type { Paginated } from '@/shared/types/pagination.types'

export interface ContactTag {
  id: string
  name: string
  slug: string
}

export interface ContactCompanySummary {
  id: string
  name: string
  industry: string | null
  status: 'active' | 'lead' | 'inactive'
}

export interface ContactDealSummary {
  id: string
  name: string
  status: 'open' | 'pending' | 'won' | 'lost'
  amount: number
  expected_close_date: string | null
  company: {
    id: string
    name: string
  } | null
}

export interface ContactConversationSummary {
  id: string
  channel: 'manual' | 'whatsapp' | 'email'
  status: 'open' | 'pending' | 'resolved'
  subject: string | null
  last_message_at: string | null
  assignee: {
    id: string
    name: string
  } | null
}

export interface Contact {
  id: string
  organization_id: string
  first_name: string
  last_name: string | null
  name: string
  email: string | null
  phone: string | null
  status: 'active' | 'lead' | 'inactive'
  notes: string | null
  tags: ContactTag[]
  companies?: ContactCompanySummary[]
  deals?: ContactDealSummary[]
  conversations?: ContactConversationSummary[]
  created_at: string | null
  updated_at: string | null
}

export interface ContactFilters {
  search?: string
  status?: string
  tag?: string
  page?: number
  per_page?: number
}

export interface ContactPayload {
  first_name: string
  last_name: string
  email: string
  phone: string
  status: 'active' | 'lead' | 'inactive'
  notes: string
  tags: string[]
}

export type PaginatedContacts = Paginated<Contact>
