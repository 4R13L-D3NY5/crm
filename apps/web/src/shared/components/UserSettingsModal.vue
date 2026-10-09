<template>
  <q-dialog
    :model-value="modelValue"
    persistent
    @update:model-value="emit('update:modelValue', $event)"
  >
    <q-card class="user-settings-card column" style="width: 720px; max-width: 95vw; height: 620px; max-height: 90vh;">
      <!-- Header del Modal -->
      <q-card-section class="row items-center justify-between q-pb-none bg-subtle">
        <div class="row items-center q-gutter-x-sm">
          <q-avatar size="36px" color="primary" text-color="white">
            <q-icon name="sym_r_manage_accounts" size="22px" />
          </q-avatar>
          <div>
            <div class="text-h6 text-bold text-ink">Ajustes de Cuenta & Preferencias</div>
            <div class="text-caption text-muted">Personaliza tu perfil, audio, aspecto visual y seguridad</div>
          </div>
        </div>
        <q-btn flat round dense icon="sym_r_close" :color="isDarkTheme ? 'grey-4' : 'grey-8'" v-close-popup />
      </q-card-section>

      <!-- Tabs de Navegación -->
      <div class="bg-subtle q-px-md">
        <q-tabs
          v-model="activeTab"
          dense
          align="left"
          class="text-muted"
          active-color="primary"
          indicator-color="primary"
        >
          <q-tab name="profile" icon="sym_r_person" label="Perfil & Datos" />
          <q-tab name="appearance" icon="sym_r_palette" label="Apariencia & Temas" />
          <q-tab name="notifications" icon="sym_r_notifications" label="Audio & Alertas" />
          <q-tab name="security" icon="sym_r_shield" label="Seguridad & 2FA" />
        </q-tabs>
      </div>

      <q-separator />

      <!-- Cuerpo de las Pestañas -->
      <q-card-section class="col q-pa-none scroll">
        <q-tab-panels v-model="activeTab" animated class="bg-transparent q-pa-md">
          <!-- TAB 1: PERFIL & DATOS PERSONALES -->
          <q-tab-panel name="profile" class="q-pa-none q-gutter-y-md">
            <div class="xf-settings-section">
              <div class="text-subtitle2 text-bold text-ink q-mb-xs">Datos de Contacto</div>
              <div class="text-caption text-muted q-mb-md">Información visible para los supervisores y miembros del equipo.</div>

              <div class="row q-col-gutter-md">
                <div class="col-12 col-md-6">
                  <q-input
                    v-model="profileForm.name"
                    label="Nombre Completo *"
                    outlined
                    dense
                    placeholder="Tu nombre y apellido"
                  />
                </div>

                <div class="col-12 col-md-6">
                  <q-input
                    v-model="profileForm.email"
                    label="Correo Electrónico *"
                    type="email"
                    outlined
                    dense
                    placeholder="tu.correo@unitepc.net"
                  />
                </div>

                <div class="col-12 col-md-6">
                  <q-input
                    v-model="profileForm.phone"
                    label="Teléfono Móvil / Interno"
                    outlined
                    dense
                    placeholder="+591 70000000"
                  />
                </div>

                <div class="col-12 col-md-6">
                  <q-input
                    :model-value="authStore.user?.current_role?.toUpperCase() || 'OPERADOR'"
                    label="Rol en el Workspace"
                    readonly
                    outlined
                    dense
                    class="opacity-75"
                  />
                </div>
              </div>

              <div class="row justify-end q-mt-md">
                <q-btn
                  unelevated
                  no-caps
                  color="primary"
                  label="Guardar Datos de Perfil"
                  :loading="savingProfile"
                  @click="handleSaveProfile"
                />
              </div>
            </div>

            <!-- Cambio de Contraseña -->
            <div class="xf-settings-section q-mt-md">
              <div class="text-subtitle2 text-bold text-ink q-mb-xs">Seguridad de Acceso: Cambiar Contraseña</div>
              <div class="text-caption text-muted q-mb-md">Ingresa tu contraseña actual para establecer una nueva clave segura.</div>

              <div class="row q-col-gutter-sm">
                <div class="col-12 col-md-4">
                  <q-input
                    v-model="passwordForm.current_password"
                    label="Contraseña Actual *"
                    type="password"
                    outlined
                    dense
                  />
                </div>
                <div class="col-12 col-md-4">
                  <q-input
                    v-model="passwordForm.password"
                    label="Nueva Contraseña *"
                    type="password"
                    outlined
                    dense
                  />
                </div>
                <div class="col-12 col-md-4">
                  <q-input
                    v-model="passwordForm.password_confirmation"
                    label="Confirmar Nueva Clave *"
                    type="password"
                    outlined
                    dense
                  />
                </div>
              </div>

              <div class="row justify-end q-mt-md">
                <q-btn
                  outline
                  no-caps
                  color="primary"
                  label="Actualizar Contraseña"
                  :loading="savingPassword"
                  @click="handleSavePassword"
                />
              </div>
            </div>
          </q-tab-panel>

          <!-- TAB 2: APARIENCIA & TEMAS -->
          <q-tab-panel name="appearance" class="q-pa-none q-gutter-y-md">
            <!-- Modo Oscuro / Claro -->
            <div class="xf-settings-section">
              <div class="row items-center justify-between">
                <div>
                  <div class="text-subtitle2 text-bold text-ink">Modo de Visualización</div>
                  <div class="text-caption text-muted">Alterna entre tema oscuro para menor fatiga visual o tema claro de alto contraste.</div>
                </div>
                <div class="row items-center q-gutter-x-sm">
                  <q-icon
                    :name="preferences.theme_mode === 'dark' ? 'sym_r_dark_mode' : 'sym_r_light_mode'"
                    size="20px"
                    :color="preferences.theme_mode === 'dark' ? 'primary' : 'amber-7'"
                  />
                  <span class="text-caption text-ink text-bold">
                    {{ preferences.theme_mode === 'dark' ? 'Modo Oscuro' : 'Modo Claro' }}
                  </span>
                  <q-toggle
                    v-model="isDarkTheme"
                    dense
                    color="primary"
                    @update:model-value="toggleDarkTheme"
                  />
                </div>
              </div>
            </div>

            <!-- Paleta de Color de Acento -->
            <div class="xf-settings-section">
              <div class="text-subtitle2 text-bold text-ink q-mb-xs">Color de Acento Principal</div>
              <div class="text-caption text-muted q-mb-md">Personaliza en tiempo real los botones, tabs activas, badges e indicadores del CRM.</div>

              <div class="row q-col-gutter-sm">
                <div v-for="t in ACCENT_THEMES" :key="t.key" class="col-12 col-sm-6 col-md-4">
                  <div
                    class="xf-theme-card cursor-pointer row items-center justify-between q-pa-sm"
                    :class="{ 'xf-theme-card--active': preferences.accent_color === t.key }"
                    @click="setAccentColor(t.key)"
                  >
                    <div class="row items-center q-gutter-x-sm">
                      <span class="xf-theme-circle" :style="{ backgroundColor: t.color }"></span>
                      <div>
                        <div class="text-caption text-bold text-ink">{{ t.name }}</div>
                        <div class="text-caption text-muted" style="font-size: 0.7rem">{{ t.desc }}</div>
                      </div>
                    </div>
                    <q-icon v-if="preferences.accent_color === t.key" name="sym_r_check_circle" color="primary" size="18px" />
                  </div>
                </div>
              </div>
            </div>

            <!-- Idioma de la Plataforma -->
            <div class="xf-settings-section">
              <div class="row items-center justify-between">
                <div>
                  <div class="text-subtitle2 text-bold text-ink">Idioma de la Interfaz</div>
                  <div class="text-caption text-muted">Selecciona tu idioma preferido para la navegación.</div>
                </div>
                <q-select
                  v-model="preferences.language"
                  :options="languageOptions"
                  emit-value
                  map-options
                  outlined
                  dense
                  style="width: 170px"
                  @update:model-value="handleSavePreferences"
                />
              </div>
            </div>
          </q-tab-panel>

          <!-- TAB 3: AUDIO & NOTIFICACIONES -->
          <q-tab-panel name="notifications" class="q-pa-none q-gutter-y-md">
            <!-- Tono de Timbre para Chats Entrantes -->
            <div class="xf-settings-section">
              <div class="text-subtitle2 text-bold text-ink q-mb-xs">Tono de Notificación de Mensaje Entrante</div>
              <div class="text-caption text-muted q-mb-md">Elige el timbre sintetizado por Web Audio que sonará cuando un cliente escriba en la bandeja omnicanal.</div>

              <div class="row q-col-gutter-md items-center">
                <div class="col-12 col-sm-6">
                  <q-select
                    v-model="preferences.notification_sound"
                    :options="soundOptions"
                    emit-value
                    map-options
                    outlined
                    dense
                    label="Sonido de Notificación"
                    @update:model-value="handleSavePreferences"
                  >
                    <template #prepend>
                      <q-icon name="sym_r_volume_up" color="primary" size="18px" />
                    </template>
                  </q-select>
                </div>

                <div class="col-12 col-sm-6 row items-center q-gutter-x-sm">
                  <q-btn
                    outline
                    dense
                    no-caps
                    color="primary"
                    icon="sym_r_play_arrow"
                    label="Probar Sonido"
                    class="q-px-sm"
                    @click="playTestSound(preferences.notification_sound || 'chime')"
                  />
                  <span class="text-caption text-muted">Volumen: {{ preferences.notification_volume ?? 80 }}%</span>
                </div>
              </div>

              <!-- Slider de Volumen -->
              <div class="q-mt-md" style="max-width: 360px">
                <q-slider
                  v-model="preferences.notification_volume"
                  :min="0"
                  :max="100"
                  :step="5"
                  color="primary"
                  label
                  @change="handleSavePreferences"
                />
              </div>
            </div>

            <!-- Notificaciones Push de Escritorio -->
            <div class="xf-settings-section">
              <div class="row items-center justify-between">
                <div>
                  <div class="text-subtitle2 text-bold text-ink">Notificaciones de Escritorio (Desktop Push)</div>
                  <div class="text-caption text-muted">Muestra alertas emergentes del sistema cuando estés en otra pestaña o ventana.</div>
                </div>
                <div class="row items-center q-gutter-x-sm">
                  <q-btn
                    v-if="!hasDesktopPermission"
                    outline
                    dense
                    no-caps
                    size="sm"
                    color="primary"
                    label="Habilitar en Navegador"
                    @click="requestNotificationPermission"
                  />
                  <q-toggle
                    v-model="preferences.desktop_notifications"
                    dense
                    color="primary"
                    @update:model-value="handleSavePreferences"
                  />
                </div>
              </div>
            </div>

            <!-- Firma Automática del Operador en WhatsApp -->
            <div class="xf-settings-section">
              <div class="row items-center justify-between q-mb-xs">
                <div>
                  <div class="text-subtitle2 text-bold text-ink">Firma Automática de Asesor (WhatsApp)</div>
                  <div class="text-caption text-muted">Adjunta automáticamente tu nombre o firma al final de cada respuesta.</div>
                </div>
                <q-toggle
                  v-model="preferences.whatsapp_signature_enabled"
                  dense
                  color="primary"
                  @update:model-value="handleSavePreferences"
                />
              </div>

              <div v-if="preferences.whatsapp_signature_enabled" class="q-mt-sm">
                <q-input
                  v-model="preferences.whatsapp_signature"
                  outlined
                  dense
                  placeholder="ej: ~ Lic. José Claure | Asesor Académico"
                  @blur="handleSavePreferences"
                >
                  <template #append>
                    <q-btn flat round dense size="xs" icon="sym_r_check" color="positive" @click="handleSavePreferences" />
                  </template>
                </q-input>
              </div>
            </div>
          </q-tab-panel>

          <!-- TAB 4: SEGURIDAD & 2FA -->
          <q-tab-panel name="security" class="q-pa-none q-gutter-y-md">
            <div class="xf-settings-section">
              <div class="row items-center justify-between q-mb-md">
                <div class="row items-center q-gutter-x-sm">
                  <q-avatar size="38px" :color="authStore.user?.two_factor_enabled ? 'positive' : 'grey-7'" text-color="white">
                    <q-icon :name="authStore.user?.two_factor_enabled ? 'sym_r_verified_user' : 'sym_r_security'" size="22px" />
                  </q-avatar>
                  <div>
                    <div class="text-subtitle2 text-bold text-ink">Autenticación en Dos Factores (2FA - TOTP)</div>
                    <div class="text-caption text-muted">
                      {{ authStore.user?.two_factor_enabled ? 'Tu cuenta está protegida con verificación en 2 pasos activa.' : 'Protege tu cuenta con Google Authenticator, Authy o Microsoft Authenticator.' }}
                    </div>
                  </div>
                </div>

                <q-badge :color="authStore.user?.two_factor_enabled ? 'positive' : 'grey-7'" class="text-caption text-bold q-px-sm q-py-xs">
                  {{ authStore.user?.two_factor_enabled ? 'ACTIVO' : 'INACTIVO' }}
                </q-badge>
              </div>

              <!-- Tarjeta Explicativa de Cómo Funciona 2FA -->
              <div class="xf-2fa-info-box q-pa-sm q-mb-md rounded-borders">
                <div class="row items-center q-gutter-x-xs text-primary text-bold text-caption q-mb-xs">
                  <q-icon name="sym_r_info" size="16px" />
                  <span>¿Cómo funciona la doble autenticación?</span>
                </div>
                <div class="text-caption text-muted" style="line-height: 1.45;">
                  1. Descargas una app de autenticación (<strong>Google Authenticator</strong> o <strong>Authy</strong>) en tu teléfono.<br/>
                  2. Registras la llave secreta para sincronizar el generador de claves temporales (TOTP).<br/>
                  3. La app genera un código de 6 dígitos que <strong>cambia cada 30 segundos</strong>.<br/>
                  4. Al iniciar sesión en un nuevo dispositivo, se te solicitará ese código, evitando que cualquiera ingrese solo conociendo tu contraseña.
                </div>
              </div>

              <!-- Si 2FA NO está activo: Iniciar Enrolamiento -->
              <div v-if="!authStore.user?.two_factor_enabled && !twoFactorSetupData">
                <p class="text-caption text-muted">
                  Haz clic en el botón inferior para generar tu llave secreta y tus códigos de recuperación de respaldo:
                </p>
                <q-btn
                  unelevated
                  no-caps
                  color="primary"
                  icon="sym_r_qr_code_scanner"
                  label="Configurar Autenticación 2FA Ahora"
                  :loading="loadingTwoFactor"
                  @click="handleStartTwoFactorSetup"
                />
              </div>

              <!-- Enrolamiento en Progreso -->
              <div v-else-if="twoFactorSetupData" class="q-gutter-y-sm bg-subtle q-pa-md rounded-borders">
                <div class="text-body2 text-bold text-ink">Paso 1: Registra tu Llave Secreta en tu Authenticator</div>
                <div class="row items-center q-gutter-x-sm font-mono text-bold text-primary xf-totp-badge q-pa-sm rounded-borders">
                  <span class="letter-spacing-wide">{{ twoFactorSetupData.secret }}</span>
                  <q-btn flat round dense size="sm" icon="sym_r_content_copy" color="primary" @click="copyText(twoFactorSetupData.secret)">
                    <q-tooltip>Copiar Llave</q-tooltip>
                  </q-btn>
                </div>

                <div class="text-body2 text-bold text-ink q-mt-md">Paso 2: Ingresa el código de 6 dígitos generado por tu app</div>
                <div class="row items-center q-gutter-x-sm" style="max-width: 300px">
                  <q-input
                    v-model="twoFactorCode"
                    outlined
                    dense
                    mask="######"
                    placeholder="123456"
                    style="width: 140px"
                  />
                  <q-btn
                    unelevated
                    no-caps
                    color="positive"
                    label="Confirmar y Activar"
                    :loading="loadingTwoFactor"
                    @click="handleConfirmTwoFactor"
                  />
                </div>

                <!-- Códigos de recuperación -->
                <div class="q-mt-md">
                  <div class="text-caption text-ink text-bold">Códigos de Recuperación de Respaldo:</div>
                  <div class="text-caption text-muted q-mb-xs">Guarda estos 8 códigos en un lugar seguro. Te permitirán ingresar si pierdes acceso a tu teléfono:</div>
                  <div class="row q-gutter-xs q-mt-xs">
                    <q-badge v-for="rc in twoFactorSetupData.recovery_codes" :key="rc" outline color="primary" class="font-mono text-weight-bold">
                      {{ rc }}
                    </q-badge>
                  </div>
                </div>
              </div>

              <!-- Si 2FA YA está activo: Opción de Desactivar -->
              <div v-else class="q-gutter-y-sm">
                <p class="text-caption text-muted">
                  Tu cuenta se encuentra actualmente protegida. Para desactivar la verificación en dos pasos, se requiere confirmar tu contraseña actual.
                </p>
                <div class="row items-center q-gutter-x-sm" style="max-width: 380px">
                  <q-input
                    v-model="disable2FaPassword"
                    type="password"
                    label="Contraseña actual"
                    outlined
                    dense
                    style="flex: 1"
                  />
                  <q-btn
                    outline
                    no-caps
                    color="negative"
                    label="Desactivar 2FA"
                    :loading="loadingTwoFactor"
                    @click="handleDisableTwoFactor"
                  />
                </div>
              </div>
            </div>
          </q-tab-panel>
        </q-tab-panels>
      </q-card-section>
    </q-card>
  </q-dialog>
</template>

<script setup lang="ts">
import { reactive, ref, watch } from 'vue'
import { useAuthStore } from '@/modules/auth/stores/auth.store'
import type { UserPreferences } from '@/modules/auth/types/auth.types'
import { useAppNotify } from '@/shared/composables/useAppNotify'
import { useAppTheme } from '@/shared/composables/useAppTheme'
import {
  confirmTwoFactor,
  disableTwoFactor,
  setupTwoFactor,
  updatePassword,
} from '@/modules/auth/api/profile.api'

const props = defineProps<{
  modelValue: boolean
}>()

const emit = defineEmits<{
  'update:modelValue': [val: boolean]
}>()

const authStore = useAuthStore()
const notify = useAppNotify()
const { ACCENT_THEMES, applyAccentColor, applyThemeMode } = useAppTheme()

const activeTab = ref('profile')
const isDarkTheme = ref(true)

// Formularios
const profileForm = reactive({
  name: '',
  email: '',
  phone: '',
})

const passwordForm = reactive({
  current_password: '',
  password: '',
  password_confirmation: '',
})

const preferences = reactive<UserPreferences>({
  theme_mode: 'dark',
  accent_color: 'emerald',
  notification_sound: 'chime',
  notification_volume: 80,
  desktop_notifications: true,
  whatsapp_signature_enabled: false,
  whatsapp_signature: '',
  language: 'es',
})

const savingProfile = ref(false)
const savingPassword = ref(false)
const loadingTwoFactor = ref(false)
const twoFactorSetupData = ref<any>(null)
const twoFactorCode = ref('')
const disable2FaPassword = ref('')
const hasDesktopPermission = ref(false)

const soundOptions = [
  { label: 'Chime Suave (Armónico)', value: 'chime' },
  { label: 'Campanilla Clásica', value: 'bell' },
  { label: 'Modern Pulse (Discreto)', value: 'modern' },
  { label: 'Pop Burbuja', value: 'pop' },
  { label: 'Silencioso (Sin audio)', value: 'none' },
]

const languageOptions = [
  { label: 'Español (ES)', value: 'es' },
  { label: 'English (US)', value: 'en' },
  { label: 'Português (BR)', value: 'pt' },
]

// Sincronizar al abrir modal
watch(
  () => props.modelValue,
  (open) => {
    if (open && authStore.user) {
      profileForm.name = authStore.user.name || ''
      profileForm.email = authStore.user.email || ''
      profileForm.phone = authStore.user.phone || ''

      const userPrefs = authStore.user.preferences || {}
      preferences.theme_mode = userPrefs.theme_mode || 'dark'
      preferences.accent_color = userPrefs.accent_color || 'emerald'
      preferences.notification_sound = userPrefs.notification_sound || 'chime'
      preferences.notification_volume = userPrefs.notification_volume ?? 80
      preferences.desktop_notifications = userPrefs.desktop_notifications ?? true
      preferences.whatsapp_signature_enabled = userPrefs.whatsapp_signature_enabled ?? false
      preferences.whatsapp_signature = userPrefs.whatsapp_signature || ''
      preferences.language = userPrefs.language || 'es'

      isDarkTheme.value = preferences.theme_mode === 'dark'
      checkNotificationPermission()
    }
  },
  { immediate: true },
)

function toggleDarkTheme(isDark: boolean) {
  preferences.theme_mode = isDark ? 'dark' : 'light'
  applyThemeMode(isDark)
  handleSavePreferences()
}

function setAccentColor(colorKey: NonNullable<UserPreferences['accent_color']>) {
  preferences.accent_color = colorKey
  applyAccentColor(colorKey)
  handleSavePreferences()
}

async function handleSaveProfile() {
  if (!profileForm.name.trim()) {
    notify.warning({ message: 'El nombre es obligatorio.' })
    return
  }
  savingProfile.value = true
  try {
    await authStore.saveProfile({
      name: profileForm.name.trim(),
      email: profileForm.email.trim(),
      phone: profileForm.phone.trim() || undefined,
    })
    notify.success({ message: 'Perfil actualizado exitosamente.' })
  } catch (err: any) {
    notify.error({ message: err?.response?.data?.message || 'Error al actualizar perfil.' })
  } finally {
    savingProfile.value = false
  }
}

async function handleSavePassword() {
  if (!passwordForm.password || passwordForm.password !== passwordForm.password_confirmation) {
    notify.warning({ message: 'Las contraseñas no coinciden o están vacías.' })
    return
  }
  savingPassword.value = true
  try {
    await updatePassword({
      current_password: passwordForm.current_password,
      password: passwordForm.password,
      password_confirmation: passwordForm.password_confirmation,
    })
    notify.success({ message: 'Contraseña actualizada correctamente.' })
    passwordForm.current_password = ''
    passwordForm.password = ''
    passwordForm.password_confirmation = ''
  } catch (err: any) {
    notify.error({ message: err?.response?.data?.message || 'Error al cambiar contraseña.' })
  } finally {
    savingPassword.value = false
  }
}

async function handleSavePreferences() {
  try {
    await authStore.savePreferences({ ...preferences })
  } catch {
    // Silencioso
  }
}

// Web Audio API Synthesizer (reproduce sonidos reales sin necesidad de mp3 externos)
function playTestSound(soundType?: string) {
  if (!soundType || soundType === 'none') return
  try {
    const ctx = new (window.AudioContext || (window as any).webkitAudioContext)()
    const gain = ctx.createGain()
    const vol = ((preferences.notification_volume ?? 80) / 100) * 0.3
    gain.gain.setValueAtTime(vol, ctx.currentTime)
    gain.connect(ctx.destination)

    if (soundType === 'chime') {
      const osc1 = ctx.createOscillator()
      const osc2 = ctx.createOscillator()
      osc1.frequency.setValueAtTime(523.25, ctx.currentTime) // C5
      osc2.frequency.setValueAtTime(659.25, ctx.currentTime + 0.1) // E5
      osc1.connect(gain)
      osc2.connect(gain)
      osc1.start(ctx.currentTime)
      osc1.stop(ctx.currentTime + 0.2)
      osc2.start(ctx.currentTime + 0.1)
      osc2.stop(ctx.currentTime + 0.35)
    } else if (soundType === 'bell') {
      const osc = ctx.createOscillator()
      osc.type = 'triangle'
      osc.frequency.setValueAtTime(880, ctx.currentTime) // A5
      osc.connect(gain)
      osc.start(ctx.currentTime)
      gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.5)
      osc.stop(ctx.currentTime + 0.5)
    } else if (soundType === 'modern') {
      const osc = ctx.createOscillator()
      osc.type = 'sine'
      osc.frequency.setValueAtTime(600, ctx.currentTime)
      osc.frequency.setValueAtTime(900, ctx.currentTime + 0.08)
      osc.connect(gain)
      osc.start(ctx.currentTime)
      osc.stop(ctx.currentTime + 0.2)
    } else if (soundType === 'pop') {
      const osc = ctx.createOscillator()
      osc.type = 'sine'
      osc.frequency.setValueAtTime(400, ctx.currentTime)
      osc.frequency.exponentialRampToValueAtTime(800, ctx.currentTime + 0.1)
      osc.connect(gain)
      osc.start(ctx.currentTime)
      osc.stop(ctx.currentTime + 0.12)
    }
  } catch {
    // Si no tiene soporte de AudioContext
  }
}

function checkNotificationPermission() {
  if (typeof window !== 'undefined' && 'Notification' in window) {
    hasDesktopPermission.value = Notification.permission === 'granted'
  }
}

async function requestNotificationPermission() {
  if ('Notification' in window) {
    const perm = await Notification.requestPermission()
    hasDesktopPermission.value = perm === 'granted'
    if (perm === 'granted') {
      notify.success({ message: 'Notificaciones de escritorio autorizadas.' })
      new Notification('XpertiFlow CRM', {
        body: 'Las notificaciones de escritorio están activas.',
        icon: '/favicon.ico',
      })
    }
  }
}

async function handleStartTwoFactorSetup() {
  loadingTwoFactor.value = true
  try {
    twoFactorSetupData.value = await setupTwoFactor()
  } catch {
    notify.error({ message: 'No se pudo iniciar la configuración 2FA.' })
  } finally {
    loadingTwoFactor.value = false
  }
}

async function handleConfirmTwoFactor() {
  if (twoFactorCode.value.length < 6) return
  loadingTwoFactor.value = true
  try {
    await confirmTwoFactor(twoFactorCode.value)
    if (authStore.user) {
      authStore.user.two_factor_enabled = true
    }
    twoFactorSetupData.value = null
    twoFactorCode.value = ''
    notify.success({ message: '¡Autenticación en 2 factores activada con éxito!' })
  } catch {
    notify.error({ message: 'Código de verificación incorrecto.' })
  } finally {
    loadingTwoFactor.value = false
  }
}

async function handleDisableTwoFactor() {
  if (!disable2FaPassword.value) return
  loadingTwoFactor.value = true
  try {
    await disableTwoFactor(disable2FaPassword.value)
    if (authStore.user) {
      authStore.user.two_factor_enabled = false
    }
    disable2FaPassword.value = ''
    notify.success({ message: 'Autenticación 2FA desactivada.' })
  } catch {
    notify.error({ message: 'Contraseña incorrecta.' })
  } finally {
    loadingTwoFactor.value = false
  }
}

function copyText(text: string) {
  navigator.clipboard.writeText(text)
  notify.info({ message: 'Copiado al portapapeles' })
}
</script>

<style scoped lang="scss">
.user-settings-card {
  background: var(--crm-bg-card);
  border: 1px solid var(--crm-color-border);
  border-radius: 16px;
  color: var(--crm-color-ink);
}

.bg-subtle {
  background: rgba(255, 255, 255, 0.02);
}

.body--light .bg-subtle {
  background: #f8fafc;
}

.xf-settings-section {
  background: rgba(255, 255, 255, 0.02);
  border: 1px solid var(--crm-color-border);
  border-radius: 12px;
  padding: 16px;
}

.body--light .xf-settings-section {
  background: #f8fafc;
  border-color: #e2e8f0;
}

.xf-theme-card {
  background: rgba(255, 255, 255, 0.03);
  border: 1px solid var(--crm-color-border);
  border-radius: 10px;
  transition: all 0.2s ease;

  &:hover {
    background: rgba(255, 255, 255, 0.06);
  }

  &--active {
    border-color: var(--crm-color-primary) !important;
    background: var(--crm-color-primary-soft) !important;
  }
}

.body--light .xf-theme-card {
  background: #ffffff;
  border-color: #e2e8f0;

  &:hover {
    background: #f1f5f9;
  }
}

.xf-theme-circle {
  width: 22px;
  height: 22px;
  border-radius: 50%;
  display: inline-block;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.25);
}

.xf-2fa-info-box {
  background: var(--crm-color-primary-soft);
  border: 1px solid rgba(16, 185, 129, 0.2);
}

.xf-totp-badge {
  background: rgba(0, 0, 0, 0.3);
  border: 1px solid var(--crm-color-border);
  width: fit-content;
}

.body--light .xf-totp-badge {
  background: #f1f5f9;
  border-color: #cbd5e1;
}

.letter-spacing-wide {
  letter-spacing: 0.1em;
}

.opacity-75 {
  opacity: 0.75;
}
</style>
