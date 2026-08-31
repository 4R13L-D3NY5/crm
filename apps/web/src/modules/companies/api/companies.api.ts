import { http } from '@/shared/api/http'

import type {
  Company,
  CompanyFilters,
  CompanyPayload,
  PaginatedCompanies,
} from '../types/company.types'

export async function getCompanies(
  params: CompanyFilters,
): Promise<PaginatedCompanies> {
  const response = await http.get<PaginatedCompanies>('/companies', { params })

  return response.data
}

export async function getCompany(id: string): Promise<Company> {
  const response = await http.get<{ data: Company }>(`/companies/${id}`)

  return response.data.data
}

export async function createCompany(payload: CompanyPayload): Promise<Company> {
  const response = await http.post<{ data: Company }>('/companies', payload)

  return response.data.data
}

export async function updateCompany(
  id: string,
  payload: CompanyPayload,
): Promise<Company> {
  const response = await http.put<{ data: Company }>(`/companies/${id}`, payload)

  return response.data.data
}

export async function deleteCompany(id: string): Promise<void> {
  await http.delete(`/companies/${id}`)
}
