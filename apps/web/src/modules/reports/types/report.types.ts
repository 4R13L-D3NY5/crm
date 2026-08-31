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
