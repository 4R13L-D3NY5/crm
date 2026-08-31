import type { RouteRecordRaw } from 'vue-router'

import LoginPage from './pages/LoginPage.vue'

export const authRoutes: RouteRecordRaw[] = [
  {
    path: '',
    name: 'login',
    component: LoginPage,
  },
]
