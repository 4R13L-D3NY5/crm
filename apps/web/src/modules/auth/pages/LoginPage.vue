<template>
  <div class="login-card">
    <!-- Header de la tarjeta: Brand & Identidad Minimalista -->
    <div class="login-card__header text-center q-mb-lg">
      <div class="login-brand-icon q-mb-sm">
        <svg width="32" height="32" viewBox="0 0 28 28" fill="none">
          <rect width="28" height="28" rx="8" fill="#10B981" />
          <path d="M7 14L12 9L16 13L21 8M7 20L12 15L16 19L21 14" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </div>

      <div class="row items-center justify-center q-gutter-x-xs">
        <h1 class="login-card__title">XpertiFlow</h1>
        <span class="login-card__version-pill">V2</span>
      </div>
      <p class="login-card__subtitle">
        Directorio inteligente & Suite omnicanal
      </p>
    </div>

    <!-- Formulario de Acceso -->
    <q-form class="login-form" @submit.prevent="submit">
      <q-banner
        v-if="authStore.errorMessage"
        rounded
        dense
        class="login-error q-mb-sm"
      >
        <template #avatar>
          <q-icon name="sym_r_error" size="18px" color="negative" />
        </template>
        {{ authStore.errorMessage }}
      </q-banner>

      <div class="q-gutter-y-sm">
        <div>
          <label class="text-caption text-weight-medium q-mb-xs block text-grey-4">Correo corporativo</label>
          <q-input
            v-model="email"
            outlined
            dark
            dense
            type="email"
            autocomplete="username"
            placeholder="admin@crm.local"
            class="clean-input"
            :rules="[val => !!val || 'El correo es requerido']"
          >
            <template #prepend>
              <q-icon name="sym_r_mail" size="16px" class="text-grey-5" />
            </template>
          </q-input>
        </div>

        <div>
          <label class="text-caption text-weight-medium q-mb-xs block text-grey-4">Contraseña</label>
          <q-input
            v-model="password"
            outlined
            dark
            dense
            :type="showPassword ? 'text' : 'password'"
            autocomplete="current-password"
            placeholder="••••••••"
            class="clean-input"
            :rules="[val => !!val || 'La contraseña es requerida']"
          >
            <template #prepend>
              <q-icon name="sym_r_key" size="16px" class="text-grey-5" />
            </template>
            <template #append>
              <q-icon
                :name="showPassword ? 'sym_r_visibility_off' : 'sym_r_visibility'"
                class="cursor-pointer text-grey-5"
                size="16px"
                @click="showPassword = !showPassword"
              />
            </template>
          </q-input>
        </div>
      </div>

      <q-btn
        type="submit"
        label="Acceder al CRM"
        icon-right="sym_r_arrow_forward"
        unelevated
        no-caps
        class="login-submit-btn q-mt-md"
        :loading="authStore.isLoading"
      />
    </q-form>

    <!-- Enlace a Planes y Precios -->
    <div class="text-center q-mt-md">
      <router-link
        to="/planes"
        class="text-caption text-primary text-weight-medium row items-center justify-center q-gutter-x-xs text-decoration-none"
        style="transition: opacity 0.2s; opacity: 0.85;"
      >
        <q-icon name="sym_r_loyalty" size="14px" />
        <span>Ver Planes & Precios de Suscripción →</span>
      </router-link>
    </div>

    <!-- Footer Institucional -->
    <div class="login-card__footer text-center q-mt-lg">
      <div class="row items-center justify-center q-gutter-x-xs text-caption text-grey-5">
        <span>© 2026 XpertiFlow CRM</span>
        <span>•</span>
        <span>Suite Omnicanal Enterprise</span>
      </div>
    </div>

    <!-- Modal de Conflicto de Sesión (Toma de Control / Monosesión) -->
    <q-dialog
      v-model="showConflictDialog"
      persistent
      transition-show="scale"
      transition-hide="scale"
    >
      <q-card class="session-conflict-card text-white">
        <q-linear-progress :value="1" color="amber-7" size="4px" />

        <q-card-section class="q-pt-lg text-center">
          <div class="row justify-center q-mb-md">
            <div class="session-conflict-halo">
              <q-avatar size="56px" color="amber-10" text-color="amber-1">
                <q-icon name="sym_r_devices" size="32px" />
              </q-avatar>
            </div>
          </div>

          <div class="text-h6 text-weight-bold text-white q-mb-xs">
            Sesión activa en otro dispositivo
          </div>
          <p class="text-caption text-grey-4 q-px-sm">
            Tu cuenta actualmente se encuentra abierta en otro equipo. Por políticas de licencia y seguridad de ventas, <strong>solo se permite un dispositivo activo a la vez</strong>.
          </p>

          <!-- Tarjeta de información del dispositivo activo -->
          <div v-if="activeSessionData" class="active-device-card q-my-md q-pa-sm text-left">
            <div class="row items-center q-gutter-x-sm q-mb-xs">
              <q-icon name="sym_r_computer" size="18px" color="amber-4" />
              <span class="text-caption text-weight-bold text-white">
                {{ activeSessionData.device }}
              </span>
            </div>
            <div class="row items-center justify-between text-caption text-grey-4" style="font-size: 0.75rem;">
              <span><strong>IP:</strong> {{ activeSessionData.ip }}</span>
              <span><strong>Actividad:</strong> {{ activeSessionData.last_active }}</span>
            </div>
          </div>

          <p class="text-caption text-grey-4 q-px-xs q-mb-none" style="font-size: 0.78rem;">
            ¿Deseas transferir tu sesión a este equipo? La sesión en el otro dispositivo se cerrará automáticamente.
          </p>
        </q-card-section>

        <q-card-actions align="between" class="q-px-md q-pb-md q-pt-none">
          <q-btn
            flat
            no-caps
            color="grey-4"
            label="Cancelar"
            class="q-px-md"
            :disable="isTakingOver"
            @click="showConflictDialog = false"
          />

          <q-btn
            unelevated
            no-caps
            color="primary"
            icon="sym_r_login"
            label="Continuar trabajando aquí"
            class="q-px-md text-weight-bold"
            :loading="isTakingOver"
            @click="confirmTakeover"
          >
            <template #loading>
              <q-spinner-dots size="18px" />
            </template>
          </q-btn>
        </q-card-actions>
      </q-card>
    </q-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/modules/auth/stores/auth.store'
import type { ActiveDeviceInfo } from '@/modules/auth/types/auth.types'

const authStore = useAuthStore()
const router = useRouter()

const email = ref('')
const password = ref('')
const showPassword = ref(false)

const showConflictDialog = ref(false)
const activeSessionData = ref<ActiveDeviceInfo | null>(null)
const isTakingOver = ref(false)

async function submit() {
  try {
    await authStore.login(email.value, password.value, false)
    await router.replace('/app/contacts')
  } catch (error: any) {
    if (error?.response?.status === 409 && error?.response?.data?.status === 'session_conflict') {
      activeSessionData.value = error.response.data.active_session
      showConflictDialog.value = true
      authStore.errorMessage = null
      return
    }
    if (!authStore.errorMessage) {
      authStore.errorMessage = error?.message || 'Error al conectar o iniciar sesión.'
    }
  }
}

async function confirmTakeover() {
  isTakingOver.value = true
  try {
    await authStore.login(email.value, password.value, true)
    showConflictDialog.value = false
    await router.replace('/app/contacts')
  } catch (error: any) {
    showConflictDialog.value = false
    if (!authStore.errorMessage) {
      authStore.errorMessage =
        error?.response?.data?.message || error?.message || 'Error al transferir la sesión.'
    }
  } finally {
    isTakingOver.value = false
  }
}
</script>

<style scoped lang="scss">
.login-card {
  padding: 32px 28px;
  background: var(--crm-bg-card);
  border: 1px solid var(--crm-color-border);
  border-radius: 16px;
  box-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.5);
  backdrop-filter: blur(12px);
}

.login-brand-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.login-card__title {
  margin: 0;
  font-size: 1.4rem;
  font-weight: 700;
  letter-spacing: -0.025em;
  color: var(--crm-color-ink);
}

.login-card__version-pill {
  font-size: 0.65rem;
  font-weight: 700;
  padding: 2px 6px;
  border-radius: 6px;
  background: var(--crm-color-primary-soft);
  color: var(--crm-color-primary);
  border: 1px solid rgba(16, 185, 129, 0.2);
}

.login-card__subtitle {
  margin: 4px 0 0;
  font-size: 0.82rem;
  color: var(--crm-color-muted);
}

.login-form {
  display: flex;
  flex-direction: column;
}

.login-error {
  border: 1px solid rgba(239, 68, 68, 0.25);
  background: rgba(239, 68, 68, 0.08);
  color: #fca5a5;
  font-size: 0.8rem;
}

.login-submit-btn {
  width: 100%;
  min-height: 42px;
  font-size: 0.88rem;
  font-weight: 600;
  background: var(--crm-color-primary) !important;
  color: #ffffff !important;
  border-radius: 8px;
  transition: all var(--crm-transition);

  &:hover {
    background: var(--crm-color-primary-hover) !important;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.25);
  }

  &:active {
    transform: translateY(0);
  }
}

.phase-indicator-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: var(--crm-color-primary);
}

.session-conflict-card {
  width: 440px;
  max-width: 95vw;
  background: #0f172a;
  border: 1px solid rgba(255, 255, 255, 0.14);
  border-radius: 16px;
  box-shadow: 0 20px 40px -15px rgba(0, 0, 0, 0.7);
  overflow: hidden;
}

.session-conflict-halo {
  padding: 8px;
  border-radius: 50%;
  display: inline-flex;
  background: rgba(245, 158, 11, 0.15);
  box-shadow: 0 0 20px rgba(245, 158, 11, 0.3);
}

.active-device-card {
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(245, 158, 11, 0.25);
  border-radius: 10px;
}
</style>
