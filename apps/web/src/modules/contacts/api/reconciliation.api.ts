import { http } from '@/shared/api/http'

export interface ReconciliationRule {
  id?: string
  name?: string
  phone_column: string
  name_column?: string
  ci_column?: string
  email_column?: string
  target_status_id: string
  category_mode?: 'none' | 'fixed' | 'dynamic'
  category_column?: string
  fixed_category_id?: string
  auto_create_unmatched?: boolean
  auto_tag?: string
  updated_at?: string
}

export interface ReconciliationPreviewResult {
  summary: {
    total_rows: number
    matched: number
    to_create: number
    unmatched: number
    invalid: number
  }
  target_status: {
    id: string
    name: string
    color: string
  }
  preview: Array<{
    row_index: number
    input_name: string
    input_phone: string
    input_ci?: string
    input_email?: string
    status: 'matched' | 'to_create' | 'unmatched' | 'invalid'
    action: string
    matched_contact?: {
      id: string
      name: string
      phone: string
      current_status: string
    } | null
    target_status: string
    target_category?: string | null
  }>
}

export interface ReconciliationExecuteResult {
  message: string
  updated_contacts: number
  created_contacts: number
  updated_conversations: number
  processed_rows: any[]
}

export async function previewReconciliation(payload: {
  rows: any[]
  rule: ReconciliationRule
}): Promise<ReconciliationPreviewResult> {
  const response = await http.post<ReconciliationPreviewResult>('/contacts/reconcile/preview', payload)
  return response.data
}

export async function executeReconciliation(payload: {
  rows: any[]
  rule: ReconciliationRule
  save_rule?: boolean
  rule_name?: string
}): Promise<ReconciliationExecuteResult> {
  const response = await http.post<ReconciliationExecuteResult>('/contacts/reconcile/execute', payload)
  return response.data
}

export async function getReconciliationRules(): Promise<ReconciliationRule[]> {
  const response = await http.get<{ data: ReconciliationRule[] }>('/contacts/reconcile/rules')
  return response.data.data
}

export async function saveReconciliationRule(name: string, rule: ReconciliationRule): Promise<ReconciliationRule> {
  const response = await http.post<{ data: ReconciliationRule }>('/contacts/reconcile/rules', { name, rule })
  return response.data.data
}

export async function deleteReconciliationRule(id: string): Promise<void> {
  await http.delete(`/contacts/reconcile/rules/${id}`)
}
