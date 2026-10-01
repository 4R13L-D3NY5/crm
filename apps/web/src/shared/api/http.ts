import axios from 'axios'
import type { AxiosError, InternalAxiosRequestConfig } from 'axios'

const apiBaseUrl = import.meta.env.VITE_API_BASE_URL ?? 'http://localhost:8010/api'
const appOrigin = apiBaseUrl.replace(/\/api\/?$/, '')

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
  (response) => response,
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

    throw error
  },
)
