import { http } from '@/shared/api/http'
import type { AuthUser, PresenceStatus, UserPreferences } from '../types/auth.types'

export async function updateProfile(payload: { name: string; email: string; phone?: string }): Promise<AuthUser> {
  const response = await http.put<{ data: AuthUser }>('/me/profile', payload)
  return response.data.data
}

export async function updatePassword(payload: {
  current_password: string
  password: string
  password_confirmation: string
}): Promise<{ message: string }> {
  const response = await http.put<{ message: string }>('/me/password', payload)
  return response.data
}

export async function updatePresence(payload: {
  presence_status: PresenceStatus
  presence_break_reason?: string | null
  presence_break_until?: string | null
}): Promise<{ presence_status: PresenceStatus; last_seen_at: string; preferences: UserPreferences }> {
  const response = await http.put<{
    data: { presence_status: PresenceStatus; last_seen_at: string; preferences: UserPreferences }
  }>('/me/presence', payload)
  return response.data.data
}

export async function updatePreferences(
  payload: Partial<UserPreferences>,
): Promise<UserPreferences> {
  const response = await http.put<{ data: UserPreferences }>('/me/preferences', payload)
  return response.data.data
}

export async function setupTwoFactor(): Promise<{
  secret: string
  qr_code_url: string
  recovery_codes: string[]
}> {
  const response = await http.post<{
    secret: string
    qr_code_url: string
    recovery_codes: string[]
  }>('/me/two-factor/setup')
  return response.data
}

export async function confirmTwoFactor(code: string): Promise<{ message: string; two_factor_enabled: boolean }> {
  const response = await http.post<{ message: string; two_factor_enabled: boolean }>('/me/two-factor/confirm', { code })
  return response.data
}

export async function disableTwoFactor(password: string): Promise<{ message: string; two_factor_enabled: boolean }> {
  const response = await http.post<{ message: string; two_factor_enabled: boolean }>('/me/two-factor/disable', {
    password,
  })
  return response.data
}
