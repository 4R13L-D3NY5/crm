import { http } from '@/shared/api/http'

export interface TenantSummary {
  total_tenants: number
  total_mrr_usd: number
  total_arr_usd: number
  total_phones_leased: number
  active_count: number
  warning_count: number
  expired_count: number
  suspended_count: number
}

export interface TenantItem {
  id: string
  name: string
  slug: string
  plan: 'basic' | 'pro' | 'elite'
  plan_name: string
  billing_cycle: 'monthly' | 'annual'
  status: 'active' | 'past_due' | 'suspended' | 'trial'
  health_status: 'active' | 'warning' | 'expired' | 'suspended'
  days_remaining: number
  expires_at: string
  last_payment_at: string
  monthly_mrr_usd: number
  phones_total: number
  extra_phones: number
  agents_total: number
  extra_agents: number
  users_count: number
  contact_name: string
  contact_phone: string
  contact_email: string
  license_token: string | null
}

export interface ProrationData {
  current_plan: string
  current_monthly_price: number
  new_plan: string
  new_monthly_price: number
  days_used: number
  days_remaining: number
  unused_credit_usd: number
  new_plan_cost_remaining_days_usd: number
  prorated_charge_due_today_usd: number
}

export async function fetchSuperAdminData() {
  const { data } = await http.get<{ summary: TenantSummary; tenants: TenantItem[] }>('/superadmin/tenants')
  return data
}

export async function renewTenantSubscription(
  tenantId: string,
  payload: { months: number; payment_method: string; reference_code?: string; notes?: string }
) {
  const { data } = await http.post(`/superadmin/tenants/${tenantId}/renew`, payload)
  return data
}

export async function previewProration(
  tenantId: string,
  payload: { new_plan: string; extra_phones: number; extra_agents: number }
) {
  const { data } = await http.post<ProrationData>(`/superadmin/tenants/${tenantId}/proration-preview`, payload)
  return data
}

export async function applyPlanChange(
  tenantId: string,
  payload: { new_plan: string; extra_phones: number; extra_agents: number; payment_method: string; reference_code?: string }
) {
  const { data } = await http.post(`/superadmin/tenants/${tenantId}/change-plan`, payload)
  return data
}

export async function adjustTenantQuotas(
  tenantId: string,
  payload: { extra_phones: number; extra_agents: number; payment_method: string; reference_code?: string; notes?: string }
) {
  const { data } = await http.post<{
    success: boolean
    message: string
    proration: ProrationData
    new_max_whatsapp_lines: number
    new_max_agents: number
    new_monthly_price_usd: number
    license_token: string
  }>(`/superadmin/tenants/${tenantId}/adjust-quotas`, payload)
  return data
}

export async function toggleTenantSuspension(tenantId: string) {
  const { data } = await http.post(`/superadmin/tenants/${tenantId}/toggle-status`)
  return data
}

export async function generateTenantToken(tenantId: string) {
  const { data } = await http.post(`/superadmin/tenants/${tenantId}/generate-token`)
  return data
}

export async function generateAiReminder(tenantId: string, tone: 'friendly' | 'formal' | 'urgent') {
  const { data } = await http.post<{ success: boolean; message_text: string; whatsapp_url: string; contact_phone: string }>(
    `/superadmin/tenants/${tenantId}/ai-reminder`,
    { tone }
  )
  return data
}

export async function generateAiAudit(tenantId: string) {
  const { data } = await http.post<{ success: boolean; churn_risk: string; upsell_opportunity: string; recommendation: string }>(
    `/superadmin/tenants/${tenantId}/ai-audit`
  )
  return data
}
