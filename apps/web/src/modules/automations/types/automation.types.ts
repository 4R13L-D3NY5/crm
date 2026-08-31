import type { Paginated } from '@/shared/types/pagination.types'

export interface AutomationAction {
  type: 'assign_user' | 'set_status' | 'send_whatsapp_message'
  value: string
}

export interface AutomationConditions {
  channel?: 'manual' | 'whatsapp' | 'email' | ''
  message_contains?: string
}

export interface AutomationRule {
  id: string
  organization_id: string
  name: string
  trigger_type: 'conversation.created' | 'message.inbound.received'
  conditions: AutomationConditions
  actions: AutomationAction[]
  is_active: boolean
  created_at: string | null
  updated_at: string | null
}

export interface AutomationRulePayload {
  name: string
  trigger_type: 'conversation.created' | 'message.inbound.received'
  conditions: AutomationConditions
  actions: AutomationAction[]
  is_active: boolean
}

export interface AutomationFilters {
  trigger_type?: string
  is_active?: boolean | ''
  page?: number
  per_page?: number
}

export type PaginatedAutomationRules = Paginated<AutomationRule>
