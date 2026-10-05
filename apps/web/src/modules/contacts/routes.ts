import type { RouteRecordRaw } from 'vue-router'

import ContactDetailPage from './pages/ContactDetailPage.vue'
import ContactListPage from './pages/ContactListPage.vue'
import TagsListPage from './pages/TagsListPage.vue'

export const contactsRoutes: RouteRecordRaw[] = [
  {
    path: 'contacts',
    name: 'contacts.list',
    component: ContactListPage,
    meta: { permission: 'contacts.view' },
  },
  {
    path: 'contacts-index',
    redirect: '/app/contacts',
    name: 'contacts.index',
  },
  {
    path: 'tags',
    name: 'contacts.tags',
    component: TagsListPage,
    meta: { permission: 'contacts.view' },
  },
  {
    path: 'contacts/:id',
    name: 'contacts.detail',
    component: ContactDetailPage,
    props: true,
    meta: { permission: 'contacts.view' },
  },
]
