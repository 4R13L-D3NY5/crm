import { http } from '@/shared/api/http'

export interface AppNotificationItem {
  id: string
  type: 'unread' | 'waiting_advisor' | 'waiting_client' | 'chat' | 'billing' | 'system' | 'ai' | 'crm'
  title: string
  message: string
  action_url: string | null
  data: Record<string, any> | null
  is_read: boolean
  read_at: string | null
  created_at: string
  created_at_human: string
}

export interface NotificationCounts {
  all: number
  unread: number
  waiting_advisor: number
  waiting_client: number
}

export interface NotificationResponse {
  unread_count: number
  counts?: NotificationCounts
  advisor_timeout_minutes?: number
  client_timeout_minutes?: number
  notifications: AppNotificationItem[]
}

export async function fetchNotifications(params?: { type?: string; unread_only?: boolean; limit?: number }) {
  const { data } = await http.get<NotificationResponse>('/notifications', { params })
  return data
}

export async function markNotificationAsRead(id: string) {
  const { data } = await http.put<{ success: boolean; message: string }>(`/notifications/${id}/read`)
  return data
}

export async function markAllNotificationsAsRead() {
  const { data } = await http.put<{ success: boolean; updated_count: number; message: string }>('/notifications/read-all')
  return data
}

export async function deleteNotification(id: string) {
  const { data } = await http.delete<{ success: boolean; message: string }>(`/notifications/${id}`)
  return data
}
