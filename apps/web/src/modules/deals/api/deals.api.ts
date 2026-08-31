import { http } from '@/shared/api/http'

import type { Company } from '@/modules/companies/types/company.types'
import type { Contact } from '@/modules/contacts/types/contact.types'

import type { Deal, DealPayload, Pipeline, PipelineListResponse } from '../types/deal.types'

export async function getPipelines(): Promise<Pipeline[]> {
  const response = await http.get<PipelineListResponse>('/pipelines')

  return response.data.data
}

export async function getPipelineBoard(id: string): Promise<Pipeline> {
  const response = await http.get<{ data: Pipeline }>(`/pipelines/${id}/board`)

  return response.data.data
}

export async function createDeal(payload: DealPayload): Promise<Deal> {
  const response = await http.post<{ data: Deal }>('/deals', payload)

  return response.data.data
}

export async function updateDeal(id: string, payload: DealPayload): Promise<Deal> {
  const response = await http.put<{ data: Deal }>(`/deals/${id}`, payload)

  return response.data.data
}

export async function moveDealStage(id: string, pipelineStageId: string): Promise<Deal> {
  const response = await http.put<{ data: Deal }>(`/deals/${id}/stage`, {
    pipeline_stage_id: pipelineStageId,
  })

  return response.data.data
}

export async function deleteDeal(id: string): Promise<void> {
  await http.delete(`/deals/${id}`)
}

export async function getDealContacts(): Promise<Contact[]> {
  const response = await http.get<{ data: Contact[] }>('/contacts', {
    params: {
      per_page: 100,
    },
  })

  return response.data.data
}

export async function getDealCompanies(): Promise<Company[]> {
  const response = await http.get<{ data: Company[] }>('/companies', {
    params: {
      per_page: 100,
    },
  })

  return response.data.data
}
