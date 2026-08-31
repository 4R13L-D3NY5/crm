import type { RouteRecordRaw } from 'vue-router'

import ReportsPage from './pages/ReportsPage.vue'

export const reportsRoutes: RouteRecordRaw[] = [
  {
    path: 'reports',
    name: 'reports.list',
    component: ReportsPage,
    meta: { permission: 'reports.view' },
  },
]
