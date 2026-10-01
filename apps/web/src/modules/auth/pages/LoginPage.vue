<template>
  <section class="login-page">
    <div class="login-page__header">
      <div class="row items-center justify-between">
        <h2 class="login-page__title">Acceso al Sistema</h2>
        <q-badge color="teal-9" text-color="teal-2" rounded class="q-px-sm">
          V2.0
        </q-badge>
      </div>
      <p class="login-page__subtitle">
        Selecciona una cuenta de prueba rápida o introduce tus credenciales:
      </p>
    </div>

    <!-- Selector Rápido de Cuentas Demo -->
    <div class="demo-selector">
      <div class="demo-selector__header text-caption text-grey-4 q-mb-xs">
        <q-icon name="sym_r_touch_app" size="14px" color="teal-4" class="q-mr-xs" />
        Prueba con 1 clic:
      </div>
      <div class="row q-gutter-xs">
        <button
          type="button"
          class="demo-card"
          :class="{ 'demo-card--active': email === 'admin@crm.local' }"
          @click="selectRole('admin@crm.local', 'Administrador (Owner)')"
        >
          <span class="demo-card__icon">👑</span>
          <div class="demo-card__info">
            <span class="demo-card__role">Admin</span>
            <span class="demo-card__sub">Control total</span>
          </div>
        </button>

        <button
          type="button"
          class="demo-card"
          :class="{ 'demo-card--active': email === 'supervisora@crm.local' }"
          @click="selectRole('supervisora@crm.local', 'Supervisora')"
        >
          <span class="demo-card__icon">🛡️</span>
          <div class="demo-card__info">
            <span class="demo-card__role">Supervisor</span>
            <span class="demo-card__sub">Colas y SLA</span>
          </div>
        </button>

        <button
          type="button"
          class="demo-card"
          :class="{ 'demo-card--active': email === 'agente@crm.local' }"
          @click="selectRole('agente@crm.local', 'Agente Operativo')"
        >
          <span class="demo-card__icon">🎧</span>
          <div class="demo-card__info">
            <span class="demo-card__role">Agente</span>
            <span class="demo-card__sub">Bandeja chat</span>
          </div>
        </button>
      </div>
    </div>

    <q-form class="login-page__form" @submit.prevent="submit">
      <q-banner
        v-if="authStore.errorMessage"
        inline-actions
        rounded
        class="login-page__error"
      >
        <template #avatar>
          <q-icon name="sym_r_error" color="negative" />
        </template>
        {{ authStore.errorMessage }}
      </q-banner>

      <q-input
        v-model="email"
        label="Correo corporativo"
        outlined
        dark
        dense
        type="email"
        autocomplete="username"
        class="login-input"
        :rules="[val => !!val || 'El correo es requerido']"
      >
        <template #prepend>
          <q-icon name="sym_r_mail" size="18px" color="teal-4" />
        </template>
      </q-input>

      <q-input
        v-model="password"
        label="Contraseña"
        outlined
        dark
        dense
        :type="showPassword ? 'text' : 'password'"
        autocomplete="current-password"
        class="login-input"
        :rules="[val => !!val || 'La contraseña es requerida']"
      >
        <template #prepend>
          <q-icon name="sym_r_key" size="18px" color="teal-4" />
        </template>
        <template #append>
          <q-icon
            :name="showPassword ? 'sym_r_visibility_off' : 'sym_r_visibility'"
            class="cursor-pointer text-grey-4"
            size="18px"
            @click="showPassword = !showPassword"
          />
        </template>
      </q-input>

      <q-btn
        type="submit"
        color="primary"
        :label="`Ingresar como ${currentRoleLabel}`"
        icon-right="sym_r_arrow_forward"
        unelevated
        no-caps
        class="login-page__submit"
        :loading="authStore.isLoading"
      />
    </q-form>
  </section>
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
const currentRoleLabel = ref('Administrador')

function selectRole(demoEmail: string, roleLabel: string) {
  email.value = demoEmail
  password.value = 'password'
  currentRoleLabel.value = roleLabel
}

async function submit() {
  try {
    await authStore.login(email.value, password.value)
    await router.replace({ name: 'dashboard' })
  } catch {
    // Error message handled in authStore
  }
}
</script>

<style scoped lang="scss">
.login-page {
  display: grid;
  gap: 18px;
}

.login-page__title {
  margin: 0;
  font-family: var(--crm-font-display, sans-serif);
  color: #ffffff;
  font-size: 1.65rem;
  font-weight: 700;
  letter-spacing: -0.02em;
}

.login-page__subtitle {
  margin: 6px 0 0;
  color: #94a3b8;
  font-size: 0.88rem;
  line-height: 1.5;
}

.demo-selector {
  padding: 10px 12px;
  background: rgba(255, 255, 255, 0.03);
  border: 1px solid rgba(255, 255, 255, 0.07);
  border-radius: 12px;
}

.demo-card {
  flex: 1;
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 10px;
  background: rgba(255, 255, 255, 0.03);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 8px;
  cursor: pointer;
  text-align: left;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);

  &:hover {
    background: rgba(16, 185, 129, 0.08);
    border-color: rgba(16, 185, 129, 0.4);
    transform: translateY(-1px);
  }

  &--active {
    background: rgba(16, 185, 129, 0.15);
    border-color: #10b981;
    box-shadow: 0 0 12px -2px rgba(16, 185, 129, 0.3);
  }
}

.demo-card__icon {
  font-size: 1.1rem;
}

.demo-card__info {
  display: flex;
  flex-direction: column;
}

.demo-card__role {
  font-size: 0.78rem;
  font-weight: 700;
  color: #ffffff;
}

.demo-card__sub {
  font-size: 0.65rem;
  color: #94a3b8;
}

.login-page__form {
  display: grid;
  gap: 14px;
}

.login-page__error {
  border: 1px solid rgba(239, 68, 68, 0.3);
  background: rgba(239, 68, 68, 0.1);
  color: #fca5a5;
  font-size: 0.85rem;
}

.login-page__submit {
  min-height: 48px;
  font-size: 0.95rem;
  font-weight: 700;
  border-radius: 10px;
  background: linear-gradient(135deg, #10b981 0%, #059669 100%) !important;
  box-shadow: 0 4px 16px -2px rgba(16, 185, 129, 0.4);
  transition: transform 0.15s ease, box-shadow 0.15s ease;

  &:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 20px -2px rgba(16, 185, 129, 0.5);
  }
}
</style>
