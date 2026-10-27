import axios from 'axios'
import type { AxiosError, InternalAxiosRequestConfig } from 'axios'
import { notifySessionExpired, notifySessionRevokedByAnotherDevice, resetActivityTimer } from '@/modules/auth/composables/useSessionTimeout'

export function resolveApiBaseUrl(): string {
  const envUrl = import.meta.env.VITE_API_BASE_URL
  if (typeof window !== 'undefined') {
    const isLocalhost = ['localhost', '127.0.0.1'].includes(window.location.hostname)
    if (!isLocalhost) {
      return '/api'
    }
  }
  return envUrl || 'http://localhost:8010/api'
}

export function resolveAppOrigin(): string {
  return resolveApiBaseUrl().replace(/\/api\/?$/, '')
}

const apiBaseUrl = resolveApiBaseUrl()
const appOrigin = resolveAppOrigin()

function getXsrfCookie(): string | null {
  const cookie = document.cookie
    .split(';')
    .map((chunk) => chunk.trim())
    .find((chunk) => chunk.startsWith('XSRF-TOKEN='))

  if (!cookie) {
    return null
  }

  return decodeURIComponent(cookie.replace('XSRF-TOKEN=', ''))
}

function applyXsrfHeader(config: InternalAxiosRequestConfig): void {
  const token = getXsrfCookie()

  if (token) {
    config.headers.set('X-XSRF-TOKEN', token)
  }
}

function isMutatingRequest(config?: InternalAxiosRequestConfig): boolean {
  const method = (config?.method ?? 'get').toLowerCase()

  return ['post', 'put', 'patch', 'delete'].includes(method)
}

async function ensureCsrfCookie(): Promise<void> {
  if (getXsrfCookie()) {
    return
  }

  try {
    await axios.get(`${appOrigin}/sanctum/csrf-cookie`, {
      withCredentials: true,
      timeout: 5000,
      headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
    })
  } catch {
    // Tolerant in case domain is stateful or endpoint is handled
  }
}

export const http = axios.create({
  baseURL: apiBaseUrl,
  withCredentials: true,
  withXSRFToken: true,
  xsrfCookieName: 'XSRF-TOKEN',
  xsrfHeaderName: 'X-XSRF-TOKEN',
  headers: {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
})

http.interceptors.request.use(
  async (config: InternalAxiosRequestConfig) => {
    if (isMutatingRequest(config)) {
      await ensureCsrfCookie()
      applyXsrfHeader(config)
    }

    return config
  },
)

http.interceptors.response.use(
  (response) => {
    resetActivityTimer()
    return response
  },
  async (error: AxiosError) => {
    const status = error.response?.status
    const originalRequest = error.config as (InternalAxiosRequestConfig & {
      _retriedWithFreshCsrf?: boolean
    }) | undefined

    if (status === 419 && originalRequest && isMutatingRequest(originalRequest) && !originalRequest._retriedWithFreshCsrf) {
      originalRequest._retriedWithFreshCsrf = true
      await ensureCsrfCookie()
      applyXsrfHeader(originalRequest)

      return http(originalRequest)
    }

    if (status === 401) {
      const url = originalRequest?.url || ''
      const isLoginRequest = url.includes('/login')
      const isMeCheck = url.includes('/auth/me')
      const pathname = typeof window !== 'undefined' ? window.location.pathname : ''
      const isPublicOrAuthPage = pathname.startsWith('/login') || pathname === '/' || pathname === ''

      // No disparar aviso de sesión expirada para peticiones de login, restore inicial /auth/me o rutas públicas
      if (!isLoginRequest && !isMeCheck && !isPublicOrAuthPage) {
        const errorCode = (error.response?.data as any)?.code
        if (errorCode === 'SESSION_REVOKED_ANOTHER_DEVICE') {
          notifySessionRevokedByAnotherDevice()
        } else {
          notifySessionExpired()
        }
      }
    }

    throw error
  },
)
