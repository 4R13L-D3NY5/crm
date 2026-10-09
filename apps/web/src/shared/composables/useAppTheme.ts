import { ref } from 'vue'
import { setCssVar, useQuasar } from 'quasar'
import type { UserPreferences } from '@/modules/auth/types/auth.types'

export interface AccentThemeOption {
  key: NonNullable<UserPreferences['accent_color']>
  name: string
  color: string
  hoverColor: string
  desc: string
}

export const ACCENT_THEMES: AccentThemeOption[] = [
  { key: 'emerald', name: 'Verde Esmeralda', color: '#10b981', hoverColor: '#059669', desc: 'Identidad XpertiFlow' },
  { key: 'cyan', name: 'Cian Océano', color: '#06b6d4', hoverColor: '#0891b2', desc: 'Tecnología limpia' },
  { key: 'indigo', name: 'Índigo Real', color: '#6366f1', hoverColor: '#4f46e5', desc: 'Corporativo profundo' },
  { key: 'amber', name: 'Ámbar Energía', color: '#f59e0b', hoverColor: '#d97706', desc: 'Cálido y dinámico' },
  { key: 'purple', name: 'Púrpura Nocturno', color: '#8b5cf6', hoverColor: '#7c3aed', desc: 'Elegancia moderna' },
  { key: 'rose', name: 'Rosa Pastel', color: '#ec4899', hoverColor: '#db2777', desc: 'Contraste suave' },
]

const currentAccent = ref<NonNullable<UserPreferences['accent_color']>>('emerald')
const isDark = ref(true)

export function useAppTheme() {
  const $q = useQuasar()

  function applyAccentColor(accentKey: NonNullable<UserPreferences['accent_color']>) {
    const theme = ACCENT_THEMES.find((t) => t.key === accentKey) ?? ACCENT_THEMES[0]
    currentAccent.value = theme.key

    // 1. Quasar Brand CSS Variable (re-renderiza todos los componentes de Quasar color="primary")
    setCssVar('primary', theme.color)

    // 2. CSS Custom Properties para el sistema de diseño XpertiFlow
    document.documentElement.style.setProperty('--crm-color-primary', theme.color)
    document.documentElement.style.setProperty('--crm-color-primary-hover', theme.hoverColor)
    document.documentElement.style.setProperty('--crm-color-primary-soft', `${theme.color}20`)
    document.documentElement.setAttribute('data-accent', theme.key)

    // 3. Persistencia local
    localStorage.setItem('whaticket_accent', theme.key)
  }

  function applyThemeMode(darkMode: boolean) {
    isDark.value = darkMode
    $q.dark.set(darkMode)

    if (darkMode) {
      document.documentElement.setAttribute('data-theme', 'dark')
      document.body.classList.add('body--dark')
      document.body.classList.remove('body--light')
    } else {
      document.documentElement.setAttribute('data-theme', 'light')
      document.body.classList.add('body--light')
      document.body.classList.remove('body--dark')
    }

    localStorage.setItem('whaticket_theme', darkMode ? 'dark' : 'light')
  }

  function initTheme(userPrefs?: UserPreferences | null) {
    // 1. Resolver Modo Oscuro / Claro
    const savedTheme = userPrefs?.theme_mode || localStorage.getItem('whaticket_theme') || 'dark'
    applyThemeMode(savedTheme === 'dark')

    // 2. Resolver Color de Acento
    const savedAccent = (userPrefs?.accent_color || localStorage.getItem('whaticket_accent') || 'emerald') as NonNullable<UserPreferences['accent_color']>
    applyAccentColor(savedAccent)
  }

  return {
    ACCENT_THEMES,
    currentAccent,
    isDark,
    applyAccentColor,
    applyThemeMode,
    initTheme,
  }
}
