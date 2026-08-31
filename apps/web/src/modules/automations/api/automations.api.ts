import { http } from '@/shared/api/http'

import type {
  AutomationFilters,
  AutomationRule,
  AutomationRulePayload,
  PaginatedAutomationRules,
} from '../types/automation.types'

export async function getAutomationRules(
  params: AutomationFilters,
): Promise<PaginatedAutomationRules> {
  const response = await http.get<PaginatedAutomationRules>('/automation-rules', { params })

  return response.data
}

export async function createAutomationRule(
  payload: AutomationRulePayload,
): Promise<AutomationRule> {
  const response = await http.post<{ data: AutomationRule }>('/automation-rules', payload)

  return response.data.data
}

export async function updateAutomationRule(
  id: string,
  payload: AutomationRulePayload,
): Promise<AutomationRule> {
  const response = await http.put<{ data: AutomationRule }>(`/automation-rules/${id}`, payload)

  return response.data.data
}

export async function deleteAutomationRule(id: string): Promise<void> {
  await http.delete(`/automation-rules/${id}`)
}
