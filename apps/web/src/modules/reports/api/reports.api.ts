import { http } from '@/shared/api/http'

import type { DashboardReport } from '../types/report.types'

export async function getDashboardReport(): Promise<DashboardReport> {
  const response = await http.get<{ data: DashboardReport }>('/reports/dashboard')

  return response.data.data
}
