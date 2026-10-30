<template>
  <transition name="q-transition--slide-down">
    <div
      v-if="shouldShowBanner"
      class="subscription-alert-banner"
      :class="`subscription-alert-banner--${bannerMode}`"
      role="alert"
    >
      <div class="row items-center justify-between no-wrap q-col-gutter-sm full-width">
        <!-- Icono y Mensaje -->
        <div class="row items-center no-wrap q-gutter-x-sm col-grow ellipsis">
          <q-icon
            :name="bannerMode === 'expired' ? 'sym_r_error' : 'sym_r_hourglass_top'"
            size="20px"
            :class="bannerMode === 'expired' ? 'text-negative text-weight-bolder' : 'text-amber-4 text-weight-bolder'"
            class="q-mr-xs flex-shrink-0"
          />
          <div class="column text-caption text-weight-medium">
            <span class="text-weight-bold banner-title">
              {{ bannerTitle }}
            </span>
            <span class="banner-subtitle gt-xs">
              {{ bannerSubtitle }}
            </span>
          </div>
        </div>

        <!-- Acciones: Botón de Renovación y Cerrar -->
        <div class="row items-center no-wrap q-gutter-x-xs flex-shrink-0">
          <q-btn
            unelevated
            dense
            no-caps
            size="sm"
            :color="bannerMode === 'expired' ? 'negative' : 'amber-9'"
            class="q-px-sm text-weight-bold rounded-borders"
            to="/app/pricing"
          >
            <q-icon name="sym_r_shopping_cart" size="14px" class="q-mr-xs" />
            <span>{{ isTrial ? 'Elegir Plan' : 'Renovar' }}</span>
          </q-btn>

          <q-btn
            v-if="bannerMode !== 'expired'"
            flat
            dense
            round
            size="xs"
            icon="sym_r_close"
            color="grey-4"
            class="q-ml-xs"
            @click="dismissBanner"
          >
            <q-tooltip class="bg-dark text-caption">Ocultar aviso por esta sesión</q-tooltip>
          </q-btn>
        </div>
      </div>
    </div>
  </transition>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useAuthStore } from '@/modules/auth/stores/auth.store'

const authStore = useAuthStore()
const isDismissed = ref(false)

const org = computed(() => authStore.user?.current_organization)

const isTrial = computed(() => org.value?.status === 'trial' || Boolean(org.value?.is_trial))

const daysRemaining = computed<number | null>(() => {
  if (typeof org.value?.days_remaining === 'number') {
    return org.value.days_remaining
  }
  if (!org.value?.expires_at) return null

  const expiresTime = new Date(org.value.expires_at).getTime()
  const nowTime = Date.now()
  const diffDays = Math.round((expiresTime - nowTime) / (1000 * 60 * 60 * 24))
  return diffDays
})

const isExpired = computed<boolean>(() => {
  if (org.value?.is_expired) return true
  if (org.value?.status === 'past_due' || org.value?.status === 'suspended') return true
  if (daysRemaining.value !== null && daysRemaining.value < 0) return true
  return false
})

const bannerMode = computed<'expired' | 'warning' | null>(() => {
  if (isExpired.value) return 'expired'
  if (daysRemaining.value !== null && daysRemaining.value <= 5) return 'warning'
  return null
})

const shouldShowBanner = computed<boolean>(() => {
  if (!org.value || !bannerMode.value) return false
  if (bannerMode.value === 'expired') return true

  // Para advertencias preventivas, verificar si el usuario descartó en esta sesión
  const storageKey = `xf_sub_banner_dismissed_${org.value.id}`
  if (isDismissed.value || sessionStorage.getItem(storageKey) === 'true') {
    return false
  }

  return true
})

const formattedExpiryDate = computed<string>(() => {
  if (!org.value?.expires_at) return 'próximamente'
  try {
    const d = new Date(org.value.expires_at)
    return d.toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric' })
  } catch {
    return 'próximamente'
  }
})

const bannerTitle = computed<string>(() => {
  const plan = (org.value?.plan || 'Básico').toUpperCase()
  if (bannerMode.value === 'expired') {
    return isTrial.value
      ? `Período de prueba finalizado (${plan})`
      : `Suscripción vencida el ${formattedExpiryDate.value}`
  }

  if (daysRemaining.value === 0) {
    return isTrial.value
      ? '¡Tu período de prueba vence hoy!'
      : '¡Tu suscripción vence hoy!'
  }

  const days = daysRemaining.value === 1 ? '1 día' : `${daysRemaining.value} días`
  return isTrial.value
    ? `Tu período de prueba gratuita concluye en ${days}`
    : `Tu suscripción vence en ${days}`
})

const bannerSubtitle = computed<string>(() => {
  if (bannerMode.value === 'expired') {
    return 'Elige un plan para reactivar tus canales de WhatsApp y continuar disfrutando de la suite.'
  }
  return `Vence el ${formattedExpiryDate.value}. Renueva a tiempo para evitar interrupciones de servicio.`
})

function dismissBanner() {
  if (!org.value) return
  isDismissed.value = true
  sessionStorage.setItem(`xf_sub_banner_dismissed_${org.value.id}`, 'true')
}
</script>

<style scoped lang="scss">
.subscription-alert-banner {
  padding: 6px 16px;
  width: 100%;
  z-index: 10;
  transition: all 0.3s ease;

  &--warning {
    background: rgba(245, 158, 11, 0.12);
    border-bottom: 1px solid rgba(245, 158, 11, 0.35);
    color: #fde68a;

    .banner-title {
      color: #fbbf24;
    }
    .banner-subtitle {
      color: #fef3c7;
      opacity: 0.85;
    }
  }

  &--expired {
    background: rgba(239, 68, 68, 0.16);
    border-bottom: 1px solid rgba(239, 68, 68, 0.4);
    color: #fca5a5;

    .banner-title {
      color: #f87171;
    }
    .banner-subtitle {
      color: #fee2e2;
      opacity: 0.9;
    }
  }
}
</style>
