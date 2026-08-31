import type { RouteRecordRaw } from 'vue-router'

import ConversationInboxPage from './pages/ConversationInboxPage.vue'

export const conversationsRoutes: RouteRecordRaw[] = [
  {
    path: 'conversations',
    name: 'conversations.inbox',
    component: ConversationInboxPage,
    meta: { permission: 'conversations.view' },
  },
]
