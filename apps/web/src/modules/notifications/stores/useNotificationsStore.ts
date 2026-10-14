import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import {
  fetchNotifications,
  markNotificationAsRead,
  markAllNotificationsAsRead,
  deleteNotification as apiDeleteNotification,
  type AppNotificationItem,
  type NotificationCounts,
} from '../api/notifications.api'

export const useNotificationsStore = defineStore('notifications', () => {
  const notifications = ref<AppNotificationItem[]>([])
  const unreadCount = ref<number>(0)
  const loading = ref<boolean>(false)
  const activeFilter = ref<'all' | 'unread' | 'waiting_advisor' | 'waiting_client'>('all')

  const counts = ref<NotificationCounts>({
    all: 0,
    unread: 0,
    waiting_advisor: 0,
    waiting_client: 0,
  })

  const advisorTimeoutMinutes = ref<number>(20)
  const clientTimeoutMinutes = ref<number>(120)

  let pollInterval: ReturnType<typeof setInterval> | null = null

  const filteredNotifications = computed(() => {
    if (activeFilter.value === 'all') {
      return notifications.value
    }
    if (activeFilter.value === 'unread') {
      return notifications.value.filter((n) => !n.is_read || n.type === 'unread')
    }
    if (activeFilter.value === 'waiting_advisor') {
      return notifications.value.filter((n) => n.type === 'waiting_advisor')
    }
    if (activeFilter.value === 'waiting_client') {
      return notifications.value.filter((n) => n.type === 'waiting_client')
    }
    return notifications.value
  })

  async function loadNotifications() {
    loading.value = true
    try {
      const data = await fetchNotifications({ limit: 50 })
      notifications.value = data.notifications
      unreadCount.value = data.unread_count

      if (data.counts) {
        counts.value = data.counts
      } else {
        counts.value = {
          all: data.notifications.length,
          unread: data.notifications.filter((n) => !n.is_read || n.type === 'unread').length,
          waiting_advisor: data.notifications.filter((n) => n.type === 'waiting_advisor').length,
          waiting_client: data.notifications.filter((n) => n.type === 'waiting_client').length,
        }
      }

      if (data.advisor_timeout_minutes) {
        advisorTimeoutMinutes.value = data.advisor_timeout_minutes
      }
      if (data.client_timeout_minutes) {
        clientTimeoutMinutes.value = data.client_timeout_minutes
      }
    } catch {
      // Polling silencioso
    } finally {
      loading.value = false
    }
  }

  async function markAsRead(id: string) {
    const item = notifications.value.find((n) => n.id === id)
    if (item && !item.is_read) {
      item.is_read = true
      unreadCount.value = Math.max(0, unreadCount.value - 1)
      if (counts.value.unread > 0 && item.type === 'unread') {
        counts.value.unread--
      }
    }
    try {
      await markNotificationAsRead(id)
    } catch {
      // Ignorar
    }
  }

  async function markAllAsRead() {
    notifications.value.forEach((n) => {
      n.is_read = true
    })
    unreadCount.value = 0
    counts.value.unread = 0
    try {
      await markAllNotificationsAsRead()
    } catch {
      await loadNotifications()
    }
  }

  async function removeNotification(id: string) {
    const idx = notifications.value.findIndex((n) => n.id === id)
    if (idx !== -1) {
      const item = notifications.value[idx]
      if (!item.is_read) {
        unreadCount.value = Math.max(0, unreadCount.value - 1)
      }
      notifications.value.splice(idx, 1)
    }
    try {
      await apiDeleteNotification(id)
    } catch {
      // Ignorar
    }
  }

  function startPolling(intervalMs = 25000) {
    stopPolling()
    loadNotifications()
    pollInterval = setInterval(() => {
      loadNotifications()
    }, intervalMs)
  }

  function stopPolling() {
    if (pollInterval) {
      clearInterval(pollInterval)
      pollInterval = null
    }
  }

  return {
    notifications,
    unreadCount,
    counts,
    advisorTimeoutMinutes,
    clientTimeoutMinutes,
    loading,
    activeFilter,
    filteredNotifications,
    loadNotifications,
    markAsRead,
    markAllAsRead,
    removeNotification,
    startPolling,
    stopPolling,
  }
})
