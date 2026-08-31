import { http } from '@/shared/api/http'

import type { AuditFilters, PaginatedAuditLogs, UserOption } from '../types/audit.types'

export async function getAuditLogs(params: AuditFilters): Promise<PaginatedAuditLogs> {
  const response = await http.get<PaginatedAuditLogs>('/audit-logs', { params })

  return response.data
}

export async function getAuditUsers(): Promise<UserOption[]> {
  const response = await http.get<{ data: UserOption[] }>('/users')

  return response.data.data
}
