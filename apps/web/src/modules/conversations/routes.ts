import type { RouteRecordRaw } from 'vue-router'

import ConversationInboxPage from './pages/ConversationInboxPage.vue'

export const conversationsRoutes: RouteRecordRaw[] = [
  {
    path: 'conversations',
    name: 'conversations.inbox',
    component: ConversationInboxPage,
    alias: ['inbox'],
    meta: { permission: 'conversations.view' },
  },
  {
    path: 'inbox',
    redirect: '/app/conversations',
  },
]
