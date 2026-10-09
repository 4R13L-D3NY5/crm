import axios from 'axios'
import { defineStore } from 'pinia'

import {
  getCsrfCookie,
  getMe,
  getOrganizations,
  login,
  logout,
  switchOrganization,
} from '../api/auth.api'
import {
  updatePreferences,
  updatePresence,
  updateProfile,
} from '../api/profile.api'
import type { AuthRole, AuthUser, PresenceStatus, UserPreferences } from '../types/auth.types'

const DEMO_ROLE_STORAGE_KEY = 'crm-demo-role'

const ALL_PERMISSIONS = [
  'dashboard.view',
  'reports.view',
  'audit.view',
  'organizations.switch',
  'users.view',
  'contacts.view',
  'contacts.manage',
  'companies.view',
  'companies.manage',
  'deals.view',
  'deals.manage',
  'pipelines.view',
  'pipelines.manage',
  'conversations.view',
  'conversations.manage',
  'conversations.assign',
  'conversations.status.manage',
  'whatsapp.view',
  'whatsapp.manage',
  'automations.view',
  'automations.manage',
  'ai.use',
] as const

const ROLE_PERMISSIONS: Record<AuthRole, string[]> = {
  owner: [...ALL_PERMISSIONS],
  admin: [...ALL_PERMISSIONS],
  agent: [
    'dashboard.view',
    'reports.view',
    'organizations.switch',
    'users.view',
    'contacts.view',
    'contacts.manage',
    'companies.view',
    'companies.manage',
    'deals.view',
    'deals.manage',
    'pipelines.view',
    'conversations.view',
    'conversations.manage',
    'conversations.assign',
    'conversations.status.manage',
    'whatsapp.view',
    'whatsapp.manage',
    'automations.view',
    'ai.use',
  ],
  member: [
    'dashboard.view',
    'reports.view',
    'organizations.switch',
    'users.view',
    'contacts.view',
    'contacts.manage',
    'companies.view',
    'companies.manage',
    'deals.view',
    'deals.manage',
    'pipelines.view',
    'conversations.view',
    'conversations.manage',
    'conversations.assign',
    'conversations.status.manage',
    'whatsapp.view',
    'whatsapp.manage',
    'automations.view',
    'ai.use',
  ],
  viewer: [
    'dashboard.view',
    'reports.view',
    'organizations.switch',
    'users.view',
    'contacts.view',
    'companies.view',
    'deals.view',
    'pipelines.view',
    'conversations.view',
    'whatsapp.view',
  ],
}

interface AuthState {
  user: AuthUser | null
  organizations: AuthUser['organizations']
  isReady: boolean
  isLoading: boolean
  errorMessage: string | null
  demoRole: AuthRole | null
}

export const useAuthStore = defineStore('auth', {
  state: (): AuthState => ({
    user: null,
    organizations: [],
    isReady: false,
    isLoading: false,
    errorMessage: null,
    demoRole: readStoredDemoRole(),
  }),
  getters: {
    isAuthenticated: (state) => state.user !== null,
    currentOrganization: (state) => state.user?.current_organization ?? null,
    currentRole: (state): AuthRole | null => state.demoRole ?? state.user?.current_role ?? null,
    isUsingDemoRole: (state): boolean => state.demoRole !== null,
    permissions: (state): string[] => {
      if (state.demoRole) {
        return ROLE_PERMISSIONS[state.demoRole] ?? []
      }

      return state.user?.permissions ?? []
    },
    hasPermission:
      (state) =>
      (permission: string): boolean =>
        (state.demoRole
          ? (ROLE_PERMISSIONS[state.demoRole] ?? []).includes(permission)
          : state.user?.permissions.includes(permission)) ?? false,
    hasAnyPermission:
      (state) =>
      (permissions: string[]): boolean => {
        const userPerms = state.demoRole
          ? (ROLE_PERMISSIONS[state.demoRole] ?? [])
          : (state.user?.permissions ?? [])
        return permissions.some((p) => userPerms.includes(p))
      },
  },
  actions: {
    async hydrateSession() {
      if (this.isReady) {
        return
      }

      this.isLoading = true

      try {
        const user = await getMe()

        this.user = user
        this.organizations = user.organizations
      } catch (error) {
        if (!axios.isAxiosError(error) || error.response?.status !== 401) {
          this.errorMessage = 'No se pudo restaurar la sesion actual.'
        }

        this.user = null
        this.organizations = []
        this.demoRole = null
        clearStoredDemoRole()
      } finally {
        this.isReady = true
        this.isLoading = false
      }
    },
    async login(email: string, password: string) {
      this.isLoading = true
      this.errorMessage = null

      try {
        try {
          await getCsrfCookie()
        } catch {
          // CSRF cookie endpoint is tolerant for stateful domains
        }

        const user = await login({ email, password })

        this.user = user
        this.organizations = user.organizations
        this.isReady = true
        this.demoRole = null
        clearStoredDemoRole()
      } catch (error: any) {
        this.errorMessage =
          error?.response?.data?.message || 'Credenciales inválidas o sesión no disponible.'
        throw error
      } finally {
        this.isLoading = false
      }
    },
    async logout() {
      this.isLoading = true

      try {
        await logout()
      } finally {
        this.user = null
        this.organizations = []
        this.demoRole = null
        clearStoredDemoRole()
        this.isLoading = false
      }
    },
    async refreshUser() {
      const user = await getMe()

      this.user = user
      this.organizations = user.organizations
    },
    async loadOrganizations() {
      this.organizations = await getOrganizations()
    },
    async switchOrganization(organizationId: string) {
      this.isLoading = true

      try {
        await switchOrganization(organizationId)
        await this.refreshUser()
      } finally {
        this.isLoading = false
      }
    },
    setDemoRole(role: AuthRole | null) {
      this.demoRole = role

      if (role) {
        window.localStorage.setItem(DEMO_ROLE_STORAGE_KEY, role)
      } else {
        clearStoredDemoRole()
      }
    },
    async setPresence(status: PresenceStatus, breakReason?: string | null, breakUntil?: string | null) {
      const res = await updatePresence({
        presence_status: status,
        presence_break_reason: breakReason,
        presence_break_until: breakUntil,
      })
      if (this.user) {
        this.user.presence_status = res.presence_status
        this.user.last_seen_at = res.last_seen_at
        this.user.preferences = res.preferences
      }
    },
    async savePreferences(prefs: Partial<UserPreferences>) {
      const res = await updatePreferences(prefs)
      if (this.user) {
        this.user.preferences = res
      }
    },
    async saveProfile(payload: { name: string; email: string; phone?: string }) {
      const updated = await updateProfile(payload)
      this.user = updated
    },
  },
})

function readStoredDemoRole(): AuthRole | null {
  if (typeof window === 'undefined') {
    return null
  }

  const value = window.localStorage.getItem(DEMO_ROLE_STORAGE_KEY)

  if (value === 'owner' || value === 'admin' || value === 'agent' || value === 'member' || value === 'viewer') {
    return value
  }

  return null
}

function clearStoredDemoRole() {
  if (typeof window === 'undefined') {
    return
  }

  window.localStorage.removeItem(DEMO_ROLE_STORAGE_KEY)
}
