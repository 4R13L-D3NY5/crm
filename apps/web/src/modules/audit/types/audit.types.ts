import type { Paginated } from '@/shared/types/pagination.types'

export interface AuditLogUser {
  id: number
  name: string
  email: string
}

export interface AuditLogAuditable {
  type: string
  id: string | null
}

export interface AuditLog {
  id: string
  organization_id: string | null
  event: string
  ip_address: string | null
  user_agent: string | null
  metadata: Record<string, unknown>
  user: AuditLogUser | null
  auditable: AuditLogAuditable
  created_at: string | null
}

export interface AuditFilters {
  search?: string
  event?: string
  user_id?: string | null
  page?: number
  per_page?: number
}

export interface UserOption {
  id: number
  name: string
  email: string
}

export type PaginatedAuditLogs = Paginated<AuditLog>
