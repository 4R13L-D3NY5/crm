import { useQuery } from '@tanstack/vue-query'
import type { MaybeRefOrGetter } from 'vue'
import { computed, toValue } from 'vue'

import { getAuditLogs, getAuditUsers } from '../api/audit.api'
import type { AuditFilters } from '../types/audit.types'

export function useAuditLogs(filters: MaybeRefOrGetter<AuditFilters>) {
  return useQuery({
    queryKey: computed(() => ['audit-logs', toValue(filters)]),
    queryFn: () => getAuditLogs(toValue(filters)),
  })
}

export function useAuditUsers() {
  return useQuery({
    queryKey: ['audit-users'],
    queryFn: getAuditUsers,
  })
}
