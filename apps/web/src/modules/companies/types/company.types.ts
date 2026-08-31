import type { Paginated } from '@/shared/types/pagination.types'

export interface CompanyContact {
  id: string
  name: string
  email: string | null
  phone?: string | null
  status?: 'active' | 'lead' | 'inactive'
}

export interface CompanyDealSummary {
  id: string
  name: string
  status: 'open' | 'pending' | 'won' | 'lost'
  amount: number
  expected_close_date: string | null
  contact: {
    id: string
    name: string
  } | null
}

export interface CompanyConversationSummary {
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

export interface Company {
  id: string
  organization_id: string
  name: string
  industry: string | null
  website: string | null
  email: string | null
  phone: string | null
  status: 'active' | 'lead' | 'inactive'
  notes: string | null
  contacts: CompanyContact[]
  deals?: CompanyDealSummary[]
  conversations?: CompanyConversationSummary[]
  created_at: string | null
  updated_at: string | null
}

export interface CompanyFilters {
  search?: string
  status?: string
  page?: number
  per_page?: number
}

export interface CompanyPayload {
  name: string
  industry: string
  website: string
  email: string
  phone: string
  status: 'active' | 'lead' | 'inactive'
  notes: string
  contact_ids: string[]
}

export type PaginatedCompanies = Paginated<Company>
