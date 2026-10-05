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

    <!-- Selector de Rol Demo Rápido -->
    <div class="role-selector q-mb-md">
      <div class="role-selector__label text-caption q-mb-xs">
        Acceso rápido de prueba:
      </div>
      <div class="role-pills">
        <button
          type="button"
          class="role-pill"
          :class="{ 'role-pill--active': email === 'admin@crm.local' }"
          @click="selectRole('admin@crm.local', 'Admin')"
        >
          <span class="role-pill__emoji">👑</span>
          <span>Admin</span>
        </button>

        <button
          type="button"
          class="role-pill"
          :class="{ 'role-pill--active': email === 'supervisora@crm.local' }"
          @click="selectRole('supervisora@crm.local', 'Supervisor')"
        >
          <span class="role-pill__emoji">🛡️</span>
          <span>Supervisor</span>
        </button>

        <button
          type="button"
          class="role-pill"
          :class="{ 'role-pill--active': email === 'agente@crm.local' }"
          @click="selectRole('agente@crm.local', 'Agente')"
        >
          <span class="role-pill__emoji">🎧</span>
          <span>Agente</span>
        </button>
      </div>
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

    <!-- Footer Discreto: Fase 1 Activa -->
    <div class="login-card__footer text-center q-mt-lg">
      <div class="row items-center justify-center q-gutter-x-xs text-caption text-grey-5">
        <span class="phase-indicator-dot"></span>
        <span>Fase 1: Directorio & Contactos</span>
        <span>•</span>
        <span>V2 Modular</span>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/modules/auth/stores/auth.store'

const authStore = useAuthStore()
const router = useRouter()

const email = ref('admin@crm.local')
const password = ref('password')
const showPassword = ref(false)

function selectRole(demoEmail: string, _roleLabel: string) {
  email.value = demoEmail
  password.value = 'password'
}

async function submit() {
  try {
    await authStore.login(email.value, password.value)
    await router.replace('/app/contacts')
  } catch (error: any) {
    if (!authStore.errorMessage) {
      authStore.errorMessage = error?.message || 'Error al conectar o iniciar sesión.'
    }
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

.role-selector {
  padding: 10px 12px;
  background: rgba(255, 255, 255, 0.02);
  border: 1px solid var(--crm-color-border);
  border-radius: 10px;
}

.role-selector__label {
  color: var(--crm-color-dim);
  font-size: 0.72rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.role-pills {
  display: flex;
  gap: 6px;
}

.role-pill {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 6px 8px;
  background: rgba(255, 255, 255, 0.03);
  border: 1px solid var(--crm-color-border);
  border-radius: 6px;
  color: var(--crm-color-muted);
  font-size: 0.78rem;
  font-weight: 500;
  cursor: pointer;
  transition: all var(--crm-transition-fast);

  &:hover {
    background: rgba(255, 255, 255, 0.06);
    color: var(--crm-color-ink);
    border-color: var(--crm-color-border-hover);
  }

  &--active {
    background: var(--crm-color-primary-soft);
    border-color: rgba(16, 185, 129, 0.35);
    color: var(--crm-color-primary);
    font-weight: 600;
  }
}

.role-pill__emoji {
  font-size: 0.85rem;
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
</style>
