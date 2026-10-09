import type { RouteRecordRaw } from 'vue-router'

import PublicLayout from '@/shared/layouts/PublicLayout.vue'
import PrivateLayout from '@/shared/layouts/PrivateLayout.vue'
import { auditRoutes } from '@/modules/audit/routes'
import { automationRoutes } from '@/modules/automations/routes'
import { authRoutes } from '@/modules/auth/routes'
import { companiesRoutes } from '@/modules/companies/routes'
import { conversationsRoutes } from '@/modules/conversations/routes'
import { contactsRoutes } from '@/modules/contacts/routes'
import { dashboardRoutes } from '@/modules/dashboard/routes'
import { dealsRoutes } from '@/modules/deals/routes'
import { reportsRoutes } from '@/modules/reports/routes'
import { whatsappRoutes } from '@/modules/whatsapp/routes'

// Nuevas Páginas de Whaticket
import QuickMessagesPage from '@/modules/quick-messages/pages/QuickMessagesPage.vue'
import ScheduledMessagesPage from '@/modules/scheduled-messages/pages/ScheduledMessagesPage.vue'
import WabotKnowledgePage from '@/modules/ai/pages/WabotKnowledgePage.vue'
import UsersManagementPage from '@/modules/users/pages/UsersManagementPage.vue'
import DepartmentsPage from '@/modules/departments/pages/DepartmentsPage.vue'
import CampaignsPage from '@/modules/campaigns/pages/CampaignsPage.vue'
import ParametersPage from '@/modules/parameters/pages/ParametersPage.vue'
import SettingsPage from '@/modules/settings/pages/SettingsPage.vue'
import TokensPage from '@/modules/tokens/pages/TokensPage.vue'
import ApiDocsPage from '@/modules/docs/pages/ApiDocsPage.vue'

const whaticketModuleRoutes: RouteRecordRaw[] = [
  { path: 'quick-messages', name: 'quick-messages.index', component: QuickMessagesPage },
  { path: 'scheduled-messages', name: 'scheduled-messages.index', component: ScheduledMessagesPage },
  { path: 'wabot', name: 'wabot.index', component: WabotKnowledgePage },
  { path: 'users', name: 'users.index', component: UsersManagementPage },
  { path: 'departments', name: 'departments.index', component: DepartmentsPage },
  { path: 'parameters', name: 'parameters.index', component: ParametersPage },
  { path: 'campaigns', name: 'campaigns.index', component: CampaignsPage },
  { path: 'settings', name: 'settings.index', component: SettingsPage },
  { path: 'tokens', name: 'tokens.index', component: TokensPage },
  { path: 'docs', name: 'docs.index', component: ApiDocsPage },
]

export const routes: RouteRecordRaw[] = [
  {
    path: '/',
    component: PublicLayout,
    children: authRoutes,
  },
  {
    path: '/app',
    component: PrivateLayout,
    meta: { requiresAuth: true },
    children: [
      ...dashboardRoutes,
      ...contactsRoutes,
      ...companiesRoutes,
      ...dealsRoutes,
      ...conversationsRoutes,
      ...whatsappRoutes,
      ...automationRoutes,
      ...reportsRoutes,
      ...auditRoutes,
      ...whaticketModuleRoutes,
    ],
  },
  {
    path: '/:pathMatch(.*)*',
    redirect: '/app/contacts',
  },
]

