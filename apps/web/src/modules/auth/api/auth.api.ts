import axios from 'axios'

import { http, resolveAppOrigin } from '@/shared/api/http'

import type { AuthUser, LoginPayload, Organization } from '../types/auth.types'

export async function getCsrfCookie(): Promise<void> {
  const appOrigin = resolveAppOrigin()
  await axios.get(`${appOrigin}/sanctum/csrf-cookie`, {
    withCredentials: true,
    timeout: 5000,
  })
}

export async function login(payload: LoginPayload): Promise<AuthUser> {
  const response = await http.post<{ data: AuthUser }>('/login', payload)

  return response.data.data
}

export async function logout(): Promise<void> {
  await http.post('/logout')
}

export async function getMe(): Promise<AuthUser> {
  const response = await http.get<{ data: AuthUser }>('/me')

  return response.data.data
}

export async function getOrganizations(): Promise<Organization[]> {
  const response = await http.get<{ data: Organization[] }>('/organizations')

  return response.data.data
}

export async function switchOrganization(
  organizationId: string,
): Promise<Organization | null> {
  const response = await http.put<{ data: Organization | null }>(
    '/organizations/current',
    {
      organization_id: organizationId,
    },
  )

  return response.data.data
}
