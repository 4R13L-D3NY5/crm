import type { RouteRecordRaw } from 'vue-router'

import WhatsAppSettingsPage from './pages/WhatsAppSettingsPage.vue'

export const whatsappRoutes: RouteRecordRaw[] = [
  {
    path: 'whatsapp',
    name: 'whatsapp.settings',
    component: WhatsAppSettingsPage,
    meta: { permission: 'whatsapp.view' },
  },
]
