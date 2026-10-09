import type { Paginated } from '@/shared/types/pagination.types'

export interface ContactTag {
  id: string
  name: string
  slug: string
  color_hex?: string
}

export interface ImportContactItem {
  name: string
  phone: string
  email?: string
  tags?: string[]
}

export interface ImportContactsPayload {
  contacts: ImportContactItem[]
}

export interface ImportContactsResult {
  created_count: number
  total_processed: number
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

export interface ContactCustomStatus {
  id: string
  name: string
  color: string
  icon?: string
  stage_type: string
}

export interface ContactCategory {
  id: string
  name: string
  code?: string
  color?: string
  icon?: string
  full_path?: string
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
  origin_channel?: string
  channel_account?: {
    id: string
    name: string
    display_phone_number?: string | null
  } | null
  custom_status_id?: string | null
  custom_status?: ContactCustomStatus | null
  categories?: ContactCategory[]
  notes: string | null
  tags: ContactTag[]
  company?: ContactCompanySummary | null
  companies?: ContactCompanySummary[]
  deals?: ContactDealSummary[]
  conversations?: ContactConversationSummary[]
  created_at: string | null
  updated_at: string | null
}

export interface ContactFilters {
  search?: string
  status?: string
  custom_status_id?: string | string[] | null
  custom_status_ids?: string[]
  category_id?: string | string[] | null
  category_ids?: string[]
  tag?: string | string[] | null
  tags?: string[]
  page?: number
  per_page?: number
}

export interface ContactPayload {
  first_name: string
  last_name: string
  email: string
  phone: string
  status: 'active' | 'lead' | 'inactive'
  custom_status_id?: string | null
  notes: string
  tags: string[]
}

export type PaginatedContacts = Paginated<Contact>
