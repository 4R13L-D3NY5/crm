export type AuthRole = 'owner' | 'admin' | 'agent' | 'member' | 'viewer'

export interface Organization {
  id: string
  name: string
  slug: string
  role?: AuthRole | string
}

export interface AuthUser {
  id: string
  name: string
  email: string
  current_role: AuthRole | null
  permissions: string[]
  current_organization: Organization | null
  organizations: Organization[]
}

export interface LoginPayload {
  email: string
  password: string
}
