import type { RouteRecordRaw } from 'vue-router'

import ReportsOverviewPage from './pages/ReportsOverviewPage.vue'

export const reportsRoutes: RouteRecordRaw[] = [
  {
    path: 'reports',
    name: 'reports.list',
    component: ReportsOverviewPage,
    meta: { permission: 'reports.view' },
  },
]
