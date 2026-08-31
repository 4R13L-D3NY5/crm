import axios from 'axios'

import { http } from '@/shared/api/http'

import type { AuthUser, LoginPayload, Organization } from '../types/auth.types'

const apiBaseUrl =
  import.meta.env.VITE_API_BASE_URL ?? 'http://localhost:8000/api'
const appOrigin = apiBaseUrl.replace(/\/api\/?$/, '')

export async function getCsrfCookie(): Promise<void> {
  await axios.get(`${appOrigin}/sanctum/csrf-cookie`, {
    withCredentials: true,
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
