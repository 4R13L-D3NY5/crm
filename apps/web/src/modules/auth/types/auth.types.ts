export type AuthRole = 'owner' | 'admin' | 'agent' | 'member' | 'viewer'

export type PresenceStatus = 'online' | 'busy' | 'offline'

export interface UserPreferences {
  theme_mode?: 'dark' | 'light'
  accent_color?: 'emerald' | 'cyan' | 'indigo' | 'amber' | 'purple' | 'rose'
  notification_sound?: 'chime' | 'bell' | 'modern' | 'pop' | 'none'
  notification_volume?: number
  desktop_notifications?: boolean
  whatsapp_signature_enabled?: boolean
  whatsapp_signature?: string
  language?: 'es' | 'en' | 'pt'
  presence_break_reason?: string | null
  presence_break_until?: string | null
}

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
  phone?: string | null
  avatar_url?: string | null
  presence_status?: PresenceStatus
  last_seen_at?: string | null
  preferences?: UserPreferences
  two_factor_enabled?: boolean
  current_role: AuthRole | null
  permissions: string[]
  current_organization: Organization | null
  organizations: Organization[]
}

export interface LoginPayload {
  email: string
  password: string
}

