<template>
  <div>
    <!-- 1. Modal de Advertencia Preventiva de Inactividad (Cuenta regresiva) -->
    <q-dialog
      v-model="isWarningOpen"
      persistent
      transition-show="scale"
      transition-hide="scale"
    >
      <q-card class="session-modal-card text-white">
        <!-- Barra de progreso superior indicativa -->
        <q-linear-progress
          :value="progressFraction"
          color="amber-6"
          track-color="amber-10"
          size="4px"
          class="session-modal-progress"
        />

        <q-card-section class="q-pt-lg q-pb-none text-center">
          <!-- Ícono de alarma pulsante -->
          <div class="row justify-center q-mb-md">
            <div class="session-icon-halo session-icon-halo--warning">
              <q-avatar size="56px" color="amber-9" text-color="amber-1">
                <q-icon name="sym_r_timer" size="32px" class="session-pulse-icon" />
              </q-avatar>
            </div>
          </div>

          <div class="text-h6 text-weight-bold text-white q-mb-xs">
            Aviso de Inactividad de Sesión
          </div>
          <p class="text-caption text-grey-4 q-px-sm">
            No se ha detectado interacción en el CRM recientemente. Tu sesión se cerrará automáticamente al cumplirse las 2 horas para proteger la información.
          </p>

          <!-- Contador regresivo grande -->
          <div class="countdown-badge q-my-md">
            <span class="text-caption text-amber-3 text-weight-medium q-mr-xs">Tiempo restante:</span>
            <span class="font-mono text-h5 text-weight-bolder text-amber-4 tracking-wider">
              {{ formattedRemainingTime }}
            </span>
          </div>
        </q-card-section>

        <q-card-actions align="center" class="q-px-lg q-pb-lg q-pt-sm q-gutter-x-sm">
          <q-btn
            flat
            dense
            color="grey-4"
            label="Cerrar sesión"
            class="q-px-md text-caption"
            :disable="isExtending"
            @click="onLogout"
          />

          <q-btn
            unelevated
            color="primary"
            icon="sym_r_autorenew"
            label="Mantener sesión activa"
            class="q-px-lg text-weight-bold"
            :loading="isExtending"
            @click="onExtendSession"
          >
            <template #loading>
              <q-spinner-dots size="18px" />
            </template>
          </q-btn>
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- 2. Modal de Sesión Cerrada / Expirada con Re-autenticación Rápida -->
    <q-dialog
      v-model="isExpiredOpen"
      persistent
      transition-show="scale"
      transition-hide="scale"
    >
      <q-card class="session-modal-card text-white">
        <q-linear-progress
          :value="1"
          color="negative"
          size="4px"
        />

        <q-card-section class="q-pt-lg q-pb-xs text-center">
          <!-- Ícono de candado -->
          <div class="row justify-center q-mb-md">
            <div class="session-icon-halo session-icon-halo--expired">
              <q-avatar size="56px" color="red-10" text-color="red-1">
                <q-icon name="sym_r_lock" size="32px" />
              </q-avatar>
            </div>
          </div>

          <div class="text-h6 text-weight-bold text-white q-mb-xs">
            Tu sesión ha expirado o ha sido cerrada
          </div>
          <p class="text-caption text-grey-4 q-px-sm">
            Para continuar trabajando y no perder los datos o borradores en pantalla, introduce tu contraseña para renovar la sesión:
          </p>

          <!-- Identificación del usuario en sesión -->
          <div class="user-identity-badge row items-center justify-center q-gutter-x-sm q-py-xs q-px-md q-my-sm">
            <q-avatar size="24px" color="teal-8" text-color="white" class="text-weight-bold" style="font-size: 11px;">
              {{ userInitials }}
            </q-avatar>
            <div class="text-left ellipsis" style="max-width: 240px;">
              <div v-if="cachedUserName" class="text-caption text-weight-bold text-white ellipsis">
                {{ cachedUserName }}
              </div>
              <div class="text-caption text-grey-4 ellipsis font-mono" style="font-size: 0.72rem;">
                {{ cachedUserEmail || 'Usuario autenticado' }}
              </div>
            </div>
          </div>

          <!-- Mensaje de error si falla la re-autenticación -->
          <q-banner
            v-if="reauthError"
            rounded
            dense
            class="bg-red-10 text-red-1 text-caption q-my-sm text-left"
          >
            <template #avatar>
              <q-icon name="sym_r_error" color="red-3" size="18px" />
            </template>
            {{ reauthError }}
          </q-banner>

          <!-- Campo de Contraseña -->
          <q-form @submit.prevent="onReauthSubmit" class="q-mt-md">
            <q-input
              v-model="passwordInput"
              :type="showPassword ? 'text' : 'password'"
              dense
              outlined
              autofocus
              dark
              label="Ingresa tu contraseña"
              class="xf-reauth-input"
              :disable="isReauthenticating"
              @keydown.enter.prevent="onReauthSubmit"
            >
              <template #prepend>
                <q-icon name="sym_r_key" size="18px" color="grey-5" />
              </template>
              <template #append>
                <q-btn
                  flat
                  dense
                  round
                  size="sm"
                  :icon="showPassword ? 'sym_r_visibility_off' : 'sym_r_visibility'"
                  color="grey-4"
                  @click="showPassword = !showPassword"
                />
              </template>
            </q-input>

            <div class="row justify-between items-center q-mt-lg">
              <q-btn
                flat
                dense
                color="grey-4"
                label="Ir a Login"
                class="text-caption q-px-sm"
                :disable="isReauthenticating"
                @click="onFullLoginRedirect"
              />

              <q-btn
                unelevated
                color="primary"
                icon="sym_r_lock_open"
                label="Reanudar sesión"
                class="q-px-md text-weight-bold"
                :loading="isReauthenticating"
                :disable="!passwordInput.trim()"
                @click="onReauthSubmit"
              >
                <template #loading>
                  <q-spinner-dots size="18px" />
                </template>
              </q-btn>
            </div>
          </q-form>
        </q-card-section>
      </q-card>
    </q-dialog>

    <!-- 3. Modal de Sesión Cerrada por Inicio en Otro Dispositivo (Monosesión) -->
    <q-dialog
      v-model="isRevokedByAnotherDeviceOpen"
      persistent
      transition-show="scale"
      transition-hide="scale"
    >
      <q-card class="session-modal-card text-white">
        <q-linear-progress
          :value="1"
          color="warning"
          size="4px"
        />

        <q-card-section class="q-pt-lg q-pb-md text-center">
          <div class="row justify-center q-mb-md">
            <div class="session-icon-halo session-icon-halo--warning">
              <q-avatar size="56px" color="amber-10" text-color="amber-1">
                <q-icon name="sym_r_devices_off" size="32px" />
              </q-avatar>
            </div>
          </div>

          <div class="text-h6 text-weight-bold text-white q-mb-xs">
            Sesión finalizada en este dispositivo
          </div>
          <p class="text-body2 text-grey-3 q-px-sm q-mt-sm">
            Se ha iniciado sesión con tu cuenta desde <strong>otro equipo o navegador</strong>.
          </p>
          <p class="text-caption text-grey-4 q-px-sm">
            Por políticas de uso y licencias de usuario, <strong>solo se permite un dispositivo activo a la vez</strong> para esta cuenta.
          </p>
        </q-card-section>

        <q-card-actions align="center" class="q-px-lg q-pb-lg q-pt-none">
          <q-btn
            unelevated
            no-caps
            color="primary"
            icon="sym_r_login"
            label="Volver a Iniciar Sesión"
            class="full-width text-weight-bold"
            @click="onRevokedLoginRedirect"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth.store'
import { useSessionTimeout, WARNING_BEFORE_TIMEOUT_MS } from '../composables/useSessionTimeout'
import { queryClient } from '@/app/plugins/query-client'

const router = useRouter()
const authStore = useAuthStore()
const {
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
  extendSession,
  reauthenticate,
} = useSessionTimeout()

const passwordInput = ref('')
const showPassword = ref(false)

// Fracción para la barra de progreso (de 1 a 0 en los 2 minutos)
const progressFraction = computed(() => {
  const maxSec = WARNING_BEFORE_TIMEOUT_MS / 1000
  return Math.min(1, Math.max(0, remainingSeconds.value / maxSec))
})

const userInitials = computed(() => {
  const name = cachedUserName.value || authStore.user?.name || 'CRM'
  return name.slice(0, 2).toUpperCase()
})

watch(isExpiredOpen, (open) => {
  if (open) {
    passwordInput.value = ''
    showPassword.value = false
  }
})

async function onExtendSession() {
  await extendSession()
}

async function onLogout() {
  isWarningOpen.value = false
  await authStore.logout()
  queryClient.clear(); await router.replace({ name: 'login' })
}

async function onReauthSubmit() {
  if (!passwordInput.value.trim()) return
  const success = await reauthenticate(passwordInput.value)
  if (success) {
    passwordInput.value = ''
  }
}

async function onFullLoginRedirect() {
  isExpiredOpen.value = false
  await authStore.logout()
  queryClient.clear(); await router.replace({ name: 'login' })
}

async function onRevokedLoginRedirect() {
  isRevokedByAnotherDeviceOpen.value = false
  await authStore.logout()
  queryClient.clear(); await router.replace({ name: 'login' })
}
</script>

<style scoped lang="scss">
.session-modal-card {
  width: 440px;
  max-width: 95vw;
  background: #0f172a;
  border: 1px solid rgba(255, 255, 255, 0.14);
  border-radius: 16px;
  box-shadow: 0 20px 40px -15px rgba(0, 0, 0, 0.7);
  overflow: hidden;
}

.session-modal-progress {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
}

.session-icon-halo {
  padding: 8px;
  border-radius: 50%;
  display: inline-flex;

  &--warning {
    background: rgba(245, 158, 11, 0.15);
    box-shadow: 0 0 20px rgba(245, 158, 11, 0.3);
  }

  &--expired {
    background: rgba(239, 68, 68, 0.15);
    box-shadow: 0 0 20px rgba(239, 68, 68, 0.3);
  }
}

.session-pulse-icon {
  animation: pulse-slow 1.6s ease-in-out infinite;
}

@keyframes pulse-slow {
  0%, 100% {
    transform: scale(1);
  }
  50% {
    transform: scale(1.1);
  }
}

.countdown-badge {
  display: inline-flex;
  align-items: center;
  background: rgba(245, 158, 11, 0.12);
  border: 1px solid rgba(245, 158, 11, 0.35);
  border-radius: 10px;
  padding: 8px 16px;
}

.user-identity-badge {
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 10px;
}

.xf-reauth-input {
  :deep(.q-field__control) {
    background: rgba(15, 23, 42, 0.8) !important;
    border-radius: 10px;
  }
}
</style>
