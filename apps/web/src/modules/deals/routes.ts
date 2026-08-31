import type { RouteRecordRaw } from 'vue-router'

import DealKanbanPage from './pages/DealKanbanPage.vue'

export const dealsRoutes: RouteRecordRaw[] = [
  {
    path: 'deals',
    name: 'deals.board',
    component: DealKanbanPage,
    meta: { permission: 'deals.view' },
  },
]
