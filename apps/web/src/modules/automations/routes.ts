import type { RouteRecordRaw } from 'vue-router'

import AutomationRulesPage from './pages/AutomationRulesPage.vue'

export const automationRoutes: RouteRecordRaw[] = [
  {
    path: 'automations',
    name: 'automations.list',
    component: AutomationRulesPage,
    meta: { permission: 'automations.view' },
  },
]
