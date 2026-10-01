import { Notify } from 'quasar'

type NotifyTone = 'success' | 'error' | 'info' | 'warning'
type NotifyInput = string | { message: string; caption?: string }

const palette: Record<NotifyTone, { color: string; icon: string }> = {
  success: { color: 'positive', icon: 'sym_r_check_circle' },
  error: { color: 'negative', icon: 'sym_r_error' },
  info: { color: 'info', icon: 'sym_r_info' },
  warning: { color: 'warning', icon: 'sym_r_warning' },
}

function parseNotifyInput(input: NotifyInput, caption?: string): { message: string; caption?: string } {
  if (typeof input === 'string') {
    return { message: input, caption }
  }
  return { message: input.message, caption: input.caption ?? caption }
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
  const notify = (tone: NotifyTone, input: NotifyInput, caption?: string) => {
    const config = palette[tone]
    const parsed = parseNotifyInput(input, caption)

    Notify.create({
      color: config.color,
      icon: config.icon,
      message: parsed.message,
      caption: parsed.caption,
    })
  }

  return {
    success: (input: NotifyInput, caption?: string) => notify('success', input, caption),
    error: (input: NotifyInput, caption?: string) => notify('error', input, caption),
    info: (input: NotifyInput, caption?: string) => notify('info', input, caption),
    warning: (input: NotifyInput, caption?: string) => notify('warning', input, caption),
    fromError: (error: unknown, fallback?: string) => notify('error', getErrorMessage(error, fallback)),
  }
}
