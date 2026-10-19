import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { Notify } from 'quasar'
import router from '@/app/router'
import { useAuthStore } from '@/modules/auth/stores/auth.store'
import { playNotificationSound } from '../utils/sound.util'
import {
  fetchNotifications,
  markNotificationAsRead,
  markAllNotificationsAsRead,
  deleteNotification as apiDeleteNotification,
  triggerTestNotificationApi,
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
  const seenNotificationIds = new Set<string>()
  let isInitialLoad = true

  const hasDesktopPermission = ref<boolean>(
    typeof window !== 'undefined' && 'Notification' in window && Notification.permission === 'granted',
  )

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

  function playAlertSound() {
    try {
      const authStore = useAuthStore()
      const soundType = authStore.user?.preferences?.notification_sound ?? 'chime'
      const volume = authStore.user?.preferences?.notification_volume ?? 80
      playNotificationSound(soundType, volume)
    } catch {
      // Ignorar si el usuario no tiene preferencias cargadas aún
    }
  }

  function showPopupToast(item: AppNotificationItem) {
    let icon = 'sym_r_notifications'
    let color = 'primary'

    if (item.type === 'unread' || item.type === 'chat') {
      icon = 'sym_r_mark_chat_unread'
      color = 'amber-9'
    } else if (item.type === 'waiting_advisor') {
      icon = 'sym_r_hourglass_top'
      color = 'warning'
    } else if (item.type === 'waiting_client') {
      icon = 'sym_r_hourglass_empty'
      color = 'cyan-8'
    }

    Notify.create({
      message: item.title,
      caption: item.message,
      icon,
      color,
      position: 'top-right',
      timeout: 7000,
      progress: true,
      classes: 'xf-notification-popup-toast',
      actions: [
        {
          label: 'Abrir',
          color: 'white',
          class: 'text-bold q-px-sm',
          handler: () => {
            if (item.action_url) {
              router.push(item.action_url)
            }
            markAsRead(item.id)
          },
        },
        {
          icon: 'sym_r_close',
          color: 'white',
          round: true,
          dense: true,
          size: 'sm',
        },
      ],
    })
  }

  function showDesktopPush(item: AppNotificationItem) {
    if (typeof window === 'undefined' || !('Notification' in window)) return
    if (Notification.permission !== 'granted') return

    try {
      const authStore = useAuthStore()
      const isEnabled = authStore.user?.preferences?.desktop_notifications ?? true
      if (!isEnabled) return

      const notif = new Notification(item.title, {
        body: item.message,
        icon: '/favicon.ico',
        tag: item.id,
      })

      notif.onclick = () => {
        window.focus()
        if (item.action_url) {
          router.push(item.action_url)
        }
        markAsRead(item.id)
      }
    } catch {
      // Navegadores que restrinjan la construcción de Notification
    }
  }

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

      // Si es el inicio de sesión o primer fetch, memorizar las notificaciones existentes
      if (isInitialLoad) {
        data.notifications.forEach((n) => seenNotificationIds.add(n.id))
        isInitialLoad = false
      } else {
        // Detectar nuevos eventos no leídos que hayan llegado en este ciclo
        const newUnread = data.notifications.filter(
          (n) => !n.is_read && !seenNotificationIds.has(n.id),
        )

        if (newUnread.length > 0) {
          playAlertSound()

          // Disparar popup emergente (máximo 3 para no saturar la pantalla si entraron muchas)
          newUnread.slice(0, 3).forEach((item) => {
            seenNotificationIds.add(item.id)
            showPopupToast(item)
            showDesktopPush(item)
          })

          newUnread.slice(3).forEach((item) => seenNotificationIds.add(item.id))
        }
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

  async function triggerTestNotification() {
    try {
      const res = await triggerTestNotificationApi()
      const item = res.notification

      notifications.value.unshift(item)
      unreadCount.value++
      counts.value.all++
      counts.value.unread++
      seenNotificationIds.add(item.id)

      playAlertSound()
      showPopupToast(item)
      showDesktopPush(item)
      return true
    } catch {
      // Fallback si la API aún no recargó
      const testItem: AppNotificationItem = {
        id: `test_${Date.now()}`,
        type: 'unread',
        title: 'Mensaje de WhatsApp: Postulante UNITEPC (Prueba)',
        message: '¡Hola! Quisiera consultar los requisitos y fechas de admisión para Medicina en Cochabamba.',
        action_url: '/app/conversations',
        data: { is_test: true },
        is_read: false,
        read_at: null,
        created_at: new Date().toISOString(),
        created_at_human: 'Ahora mismo',
      }

      notifications.value.unshift(testItem)
      unreadCount.value++
      counts.value.all++
      counts.value.unread++
      seenNotificationIds.add(testItem.id)

      playAlertSound()
      showPopupToast(testItem)
      showDesktopPush(testItem)
      return true
    }
  }

  async function requestDesktopPermission() {
    if (typeof window !== 'undefined' && 'Notification' in window) {
      const perm = await Notification.requestPermission()
      hasDesktopPermission.value = perm === 'granted'
      if (perm === 'granted') {
        Notify.create({
          type: 'positive',
          message: 'Notificaciones de escritorio autorizadas.',
          caption: 'Ahora recibirás alertas emergentes incluso si minimizas el CRM.',
          icon: 'sym_r_notifications_active',
          position: 'top-right',
        })
        try {
          new Notification('XpertiFlow CRM', {
            body: '¡Alertas de escritorio habilitadas con éxito!',
            icon: '/favicon.ico',
          })
        } catch {
          // Ignorar
        }
      }
      return perm
    }
    return 'denied'
  }

  function startPolling(intervalMs = 15000) {
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
    hasDesktopPermission,
    loadNotifications,
    markAsRead,
    markAllAsRead,
    removeNotification,
    triggerTestNotification,
    requestDesktopPermission,
    playAlertSound,
    startPolling,
    stopPolling,
  }
})
