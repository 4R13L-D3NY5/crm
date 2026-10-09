export interface DashboardSummary {
  open_conversations: number
  pending_conversations: number
  resolved_conversations: number
  active_deals: number
  won_deals: number
  estimated_revenue: number
  contacts_total: number
  inbound_messages_today: number
}

export interface ConversationChannelReportItem {
  channel: string
  total: number
}

export interface ConversationStatusReportItem {
  status: string
  total: number
}

export interface DealStatusReportItem {
  status: string
  total: number
  total_amount: number
}

export interface RecentActivityItem {
  type: 'conversation' | 'deal'
  title: string
  description: string
  meta: string
  href: string
  occurred_at: string
}

export interface DashboardReport {
  summary: DashboardSummary
  conversation_channels: ConversationChannelReportItem[]
  conversation_statuses: ConversationStatusReportItem[]
  deal_statuses: DealStatusReportItem[]
  recent_activity: RecentActivityItem[]
}

// ==========================================
// Tipos para Reporte de Desempeño y SLA de Agentes
// ==========================================
export interface AgentReportItem {
  user_id: string
  name: string
  email: string
  role: string
  assigned_count: number
  resolved_count: number
  open_count: number
  pending_count: number
  sla_on_time: number
  sla_delayed: number
  unanswered: number
  sla_compliance_rate: number
  avg_first_response_time: string
  avg_first_response_time_seconds: number
  avg_resolution_time: string
  avg_resolution_time_seconds: number
  avg_rating: number
}

export interface AgentReportSummary {
  total_assigned: number
  total_resolved: number
  total_on_time: number
  total_delayed: number
  total_unanswered: number
  sla_compliance_rate: number
  avg_first_response_time: string
  avg_resolution_time: string
}

export interface AgentReportData {
  sla_config: {
    timeout_minutes: number
  }
  summary: AgentReportSummary
  agents: AgentReportItem[]
}

// ==========================================
// Tipos para Reporte Académico por Categorías (Sedes / Carreras)
// ==========================================
export interface CategorySubcategoryReportItem {
  id: string
  name: string
  code: string | null
  color: string
  contacts_count: number
  conversations_count: number
  won_leads: number
  conversion_rate: number
}

export interface CategoryCampusReportItem {
  id: string
  name: string
  code: string | null
  color: string
  icon: string
  contacts_count: number
  conversations_count: number
  won_leads: number
  lost_leads: number
  conversion_rate: number
  percentage: number
  subcategories: CategorySubcategoryReportItem[]
}

export interface TopCareerReportItem {
  name: string
  code: string | null
  color: string
  contacts_count: number
  conversations_count: number
  percentage: number
}

export interface CategoryReportSummary {
  total_categorized_contacts: number
  total_uncategorized_contacts: number
  total_categorized_conversations: number
  top_campus: string
  top_career: string
}

export interface CategoryReportData {
  summary: CategoryReportSummary
  campuses: CategoryCampusReportItem[]
  top_careers: TopCareerReportItem[]
}

// ==========================================
// Tipos para CSAT
// ==========================================
export interface CsatReportData {
  average_score: number
  total_responses: number
  satisfaction_percentage: number
  stars_breakdown: {
    '5_stars': number
    '4_stars': number
    '3_stars': number
    '2_stars': number
    '1_star': number
  }
}
