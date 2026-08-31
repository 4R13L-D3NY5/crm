import type { RouteRecordRaw } from 'vue-router'

import CompanyDetailPage from './pages/CompanyDetailPage.vue'
import CompanyListPage from './pages/CompanyListPage.vue'

export const companiesRoutes: RouteRecordRaw[] = [
  {
    path: 'companies',
    name: 'companies.list',
    component: CompanyListPage,
    meta: { permission: 'companies.view' },
  },
  {
    path: 'companies/:id',
    name: 'companies.detail',
    component: CompanyDetailPage,
    props: true,
    meta: { permission: 'companies.view' },
  },
]
