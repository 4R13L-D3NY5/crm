import { useQuery } from '@tanstack/vue-query'

import { getDashboardReport } from '../api/reports.api'

export function useDashboardReport() {
  return useQuery({
    queryKey: ['reports', 'dashboard'],
    queryFn: getDashboardReport,
  })
}
