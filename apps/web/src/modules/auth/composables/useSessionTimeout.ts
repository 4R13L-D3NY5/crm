import { computed, ref } from 'vue'
import { Notify } from 'quasar'
import { useAuthStore } from '../stores/auth.store'
import { http } from '@/shared/api/http'

// 2 horas en milisegundos (120 minutos)
export const INACTIVITY_TIMEOUT_MS = 2 * 60 * 60 * 1000
// Advertencia preventiva: 2 minutos antes de alcanzar las 2 horas
export const WARNING_BEFORE_TIMEOUT_MS = 2 * 60 * 1000

const LAST_ACTIVITY_KEY = 'xf_crm_last_activity'
const SESSION_EXTEND_KEY = 'xf_crm_session_extended'

// Estado global compartido (singleton) para sincronizar entre layout, modal e interceptores
const isWarningOpen = ref(false)
const isExpiredOpen = ref(false)
const isRevokedByAnotherDeviceOpen = ref(false)
const remainingSeconds = ref(WARNING_BEFORE_TIMEOUT_MS / 1000)
const isExtending = ref(false)
const isReauthenticating = ref(false)
const reauthError = ref<string | null>(null)
const cachedUserEmail = ref('')
const cachedUserName = ref('')

let timerInterval: ReturnType<typeof setInterval> | null = null
let lastRecordedActivity = Date.now()
let lastHeartbeat = Date.now()
let isMonitoring = false

function getLastActivity(): number {
  try {
    const stored = localStorage.getItem(LAST_ACTIVITY_KEY)
    if (stored) {
      const parsed = parseInt(stored, 10)
      if (!isNaN(parsed) && parsed > 0) return parsed
    }
  } catch {
    // localStorage no disponible o bloqueado
  }
  return lastRecordedActivity
}

function updateLocalActivity(timestamp: number = Date.now()) {
  lastRecordedActivity = timestamp
  try {
    localStorage.setItem(LAST_ACTIVITY_KEY, String(timestamp))
  } catch {
    // Tolerante
  }
}

export function notifySessionExpired() {
  const authStore = useAuthStore()

  // Guardar datos de usuario antes de que se limpie la sesión para permitir re-login rápido
  if (authStore.user) {
    cachedUserEmail.value = authStore.user.email
    cachedUserName.value = authStore.user.name
  }

  isWarningOpen.value = false
  isExpiredOpen.value = true
  isRevokedByAnotherDeviceOpen.value = false
  reauthError.value = null
}

export function notifySessionRevokedByAnotherDevice() {
  const authStore = useAuthStore()

  if (authStore.user) {
    cachedUserEmail.value = authStore.user.email
    cachedUserName.value = authStore.user.name
  }

  isWarningOpen.value = false
  isExpiredOpen.value = false
  isRevokedByAnotherDeviceOpen.value = true
  reauthError.value = null
}

export function resetActivityTimer() {
  // Si la advertencia, revocación o expiración ya están en pantalla, no resetear silenciosamente
  if (isWarningOpen.value || isExpiredOpen.value || isRevokedByAnotherDeviceOpen.value) {
    return
  }
  updateLocalActivity(Date.now())
}

export function useSessionTimeout() {
  const authStore = useAuthStore()

  // Cachear información del usuario mientras esté autenticado
  if (authStore.user) {
    cachedUserEmail.value = authStore.user.email
    cachedUserName.value = authStore.user.name
  }

  const formattedRemainingTime = computed(() => {
    const totalSec = Math.max(0, remainingSeconds.value)
    const mins = Math.floor(totalSec / 60)
    const secs = totalSec % 60
    return `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`
  })

  function onUserInteraction() {
    // Si algún modal bloqueante ya está abierto, ignorar interacciones del ratón
    if (isWarningOpen.value || isExpiredOpen.value || isRevokedByAnotherDeviceOpen.value) {
      return
    }

    const now = Date.now()
    // Limitar escritura a localStorage a una vez cada 5 segundos
    if (now - lastRecordedActivity >= 5000) {
      updateLocalActivity(now)
    }
  }

  async function checkSessionStatusHeartbeat() {
    if (!authStore.isAuthenticated || isExpiredOpen.value || isRevokedByAnotherDeviceOpen.value) {
      return
    }

    try {
      await http.get('/auth/session-status')
    } catch (err: any) {
      if (err?.response?.data?.code === 'SESSION_REVOKED_ANOTHER_DEVICE') {
        notifySessionRevokedByAnotherDevice()
      }
    }
  }

  function checkInactivityLoop() {
    if (!authStore.isAuthenticated) {
      return
    }

    if (isExpiredOpen.value || isRevokedByAnotherDeviceOpen.value) {
      return
    }

    // Heartbeat ligero cada 30 segundos para detectar si otro dispositivo tomó control
    const now = Date.now()
    if (now - lastHeartbeat >= 30000) {
      lastHeartbeat = now
      checkSessionStatusHeartbeat()
    }

    // Si la advertencia preventiva está abierta, restar 1 segundo en cada tick
    if (isWarningOpen.value) {
      remainingSeconds.value--
      if (remainingSeconds.value <= 0) {
        isWarningOpen.value = false
        notifySessionExpired()
      }
      return
    }

    const lastActivity = getLastActivity()
    const elapsed = now - lastActivity

    // Si transcurrieron 2 horas completas sin actividad
    if (elapsed >= INACTIVITY_TIMEOUT_MS) {
      notifySessionExpired()
      return
    }

    // Si nos encontramos dentro de la ventana de advertencia (faltan 2 minutos para las 2 horas)
    const warningThreshold = INACTIVITY_TIMEOUT_MS - WARNING_BEFORE_TIMEOUT_MS
    if (elapsed >= warningThreshold) {
      const remainingMs = INACTIVITY_TIMEOUT_MS - elapsed
      remainingSeconds.value = Math.max(1, Math.ceil(remainingMs / 1000))
      isWarningOpen.value = true
    }
  }

  function onStorageEvent(e: StorageEvent) {
    // Solo si otra pestaña EXPLÍCITAMENTE extendió la sesión en el servidor
    if (e.key === SESSION_EXTEND_KEY) {
      if (isWarningOpen.value) {
        isWarningOpen.value = false
      }
      remainingSeconds.value = WARNING_BEFORE_TIMEOUT_MS / 1000
      updateLocalActivity(Number(e.newValue) || Date.now())
    }
  }

  function onVisibilityChange() {
    if (document.visibilityState === 'visible' && !isWarningOpen.value && !isExpiredOpen.value && !isRevokedByAnotherDeviceOpen.value) {
      checkInactivityLoop()
      checkSessionStatusHeartbeat()
    }
  }

  function startMonitoring() {
    if (isMonitoring || typeof window === 'undefined') return
    isMonitoring = true

    updateLocalActivity(Date.now())
    lastHeartbeat = Date.now()

    // Eventos de interacción del usuario
    const events = ['mousemove', 'keydown', 'click', 'scroll', 'touchstart']
    events.forEach((ev) => {
      window.addEventListener(ev, onUserInteraction, { passive: true })
    })

    window.addEventListener('storage', onStorageEvent)
    document.addEventListener('visibilitychange', onVisibilityChange)

    // Bucle de comprobación cada 1 segundo
    timerInterval = setInterval(checkInactivityLoop, 1000)

    // Exponer helpers globales para pruebas fáciles en DevTools
    if (typeof window !== 'undefined') {
      ;(window as any).__testSessionWarning = (seconds: number = 100) => {
        remainingSeconds.value = seconds
        isWarningOpen.value = true
        isExpiredOpen.value = false
        isRevokedByAnotherDeviceOpen.value = false
        console.log(`[SessionTimeout] Modal de advertencia activado con ${seconds}s restantes.`)
      }
      ;(window as any).__testSessionExpired = () => {
        notifySessionExpired()
        console.log('[SessionTimeout] Modal de sesión expirada activado.')
      }
      ;(window as any).__testSessionRevoked = () => {
        notifySessionRevokedByAnotherDevice()
        console.log('[SessionTimeout] Modal de sesión revocada por otro dispositivo activado.')
      }
    }
  }

  function stopMonitoring() {
    if (!isMonitoring || typeof window === 'undefined') return
    isMonitoring = false

    if (timerInterval) {
      clearInterval(timerInterval)
      timerInterval = null
    }

    const events = ['mousemove', 'keydown', 'click', 'scroll', 'touchstart']
    events.forEach((ev) => {
      window.removeEventListener(ev, onUserInteraction)
    })

    window.removeEventListener('storage', onStorageEvent)
    document.removeEventListener('visibilitychange', onVisibilityChange)
  }

  async function extendSession(): Promise<boolean> {
    isExtending.value = true
    try {
      await authStore.refreshUser()

      const now = Date.now()
      updateLocalActivity(now)
      try {
        localStorage.setItem(SESSION_EXTEND_KEY, String(now))
      } catch {}

      isWarningOpen.value = false
      remainingSeconds.value = WARNING_BEFORE_TIMEOUT_MS / 1000

      Notify.create({
        type: 'positive',
        message: 'Sesión renovada exitosamente.',
        position: 'bottom-right',
        icon: 'sym_r_check_circle',
        timeout: 2500,
      })
      return true
    } catch {
      notifySessionExpired()
      return false
    } finally {
      isExtending.value = false
    }
  }

  async function reauthenticate(password: string): Promise<boolean> {
    if (!password.trim()) {
      reauthError.value = 'Por favor introduce tu contraseña.'
      return false
    }

    const email = authStore.user?.email || cachedUserEmail.value
    if (!email) {
      reauthError.value = 'No se encontró el correo de usuario. Inicia sesión normalmente.'
      return false
    }

    isReauthenticating.value = true
    reauthError.value = null

    try {
      await authStore.login(email, password)

      const now = Date.now()
      updateLocalActivity(now)
      try {
        localStorage.setItem(SESSION_EXTEND_KEY, String(now))
      } catch {}

      isExpiredOpen.value = false
      isWarningOpen.value = false
      isRevokedByAnotherDeviceOpen.value = false

      Notify.create({
        type: 'positive',
        message: '¡Sesión reanudada con éxito! Puedes continuar tu trabajo.',
        position: 'bottom-right',
        icon: 'sym_r_lock_open',
        timeout: 3500,
      })
      return true
    } catch (err: any) {
      reauthError.value =
        err?.response?.data?.message || 'Contraseña incorrecta. Por favor verifica tus credenciales.'
      return false
    } finally {
      isReauthenticating.value = false
    }
  }

  return {
    isWarningOpen,
    isExpiredOpen,
    isRevokedByAnotherDeviceOpen,
    remainingSeconds,
    formattedRemainingTime,
    isExtending,
    isReauthenticating,
    reauthError,
    cachedUserEmail,
    cachedUserName,
    startMonitoring,
    stopMonitoring,
    extendSession,
    reauthenticate,
    notifySessionExpired,
    notifySessionRevokedByAnotherDevice,
    resetActivityTimer,
  }
}
