import { Notify } from 'quasar'

type NotifyTone = 'success' | 'error' | 'info' | 'warning'

const palette: Record<NotifyTone, { color: string; icon: string }> = {
  success: { color: 'positive', icon: 'sym_r_check_circle' },
  error: { color: 'negative', icon: 'sym_r_error' },
  info: { color: 'info', icon: 'sym_r_info' },
  warning: { color: 'warning', icon: 'sym_r_warning' },
}

export function getErrorMessage(error: unknown, fallback = 'Ocurrio un error inesperado.') {
  if (typeof error === 'string' && error.trim()) {
    return error
  }

  if (error && typeof error === 'object') {
    const response = Reflect.get(error, 'response')

    if (response && typeof response === 'object') {
      const data = Reflect.get(response, 'data')

      if (data && typeof data === 'object') {
        const message = Reflect.get(data, 'message')

        if (typeof message === 'string' && message.trim()) {
          return message
        }
      }
    }

    const message = Reflect.get(error, 'message')

    if (typeof message === 'string' && message.trim()) {
      return message
    }
  }

  return fallback
}

export function useAppNotify() {
  const notify = (tone: NotifyTone, message: string, caption?: string) => {
    const config = palette[tone]

    Notify.create({
      color: config.color,
      icon: config.icon,
      message,
      caption,
    })
  }

  return {
    success: (message: string, caption?: string) => notify('success', message, caption),
    error: (message: string, caption?: string) => notify('error', message, caption),
    info: (message: string, caption?: string) => notify('info', message, caption),
    warning: (message: string, caption?: string) => notify('warning', message, caption),
    fromError: (error: unknown, fallback?: string) => notify('error', getErrorMessage(error, fallback)),
  }
}
