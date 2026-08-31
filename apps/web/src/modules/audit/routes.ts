import type { RouteRecordRaw } from 'vue-router'

import AuditLogListPage from './pages/AuditLogListPage.vue'

export const auditRoutes: RouteRecordRaw[] = [
  {
    path: 'audit',
    name: 'audit.list',
    component: AuditLogListPage,
    meta: { permission: 'audit.view' },
  },
]
