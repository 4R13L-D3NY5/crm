<template>
  <section class="login-page">
    <div class="login-page__header">
      <div class="login-page__eyebrow">
        <q-icon name="sym_r_lock" size="14px" class="q-mr-xs" />
        Acceso Seguro V2
      </div>
      <h2>Iniciar sesión</h2>
      <p>Ingresa a tu workspace omnicanal con control multi-tenant y presencia en tiempo real.</p>
    </div>

    <!-- Cuentas Demo Rápidas para Validación y Pruebas -->
    <div class="login-page__demo-box">
      <div class="text-caption text-grey-4 q-mb-xs font-weight-500">
        <q-icon name="sym_r_flash_on" size="14px" color="amber-4" class="q-mr-xs" />
        Acceso rápido para pruebas:
      </div>
      <div class="row q-gutter-xs">
        <q-btn
          outline
          dense
          no-caps
          size="sm"
          label="👑 Administrador"
          class="demo-role-chip"
          :class="{ 'demo-role-chip--active': email === 'admin@crm.local' }"
          @click="selectDemoUser('admin@crm.local')"
        />
        <q-btn
          outline
          dense
          no-caps
          size="sm"
          label="🛡️ Supervisora"
          class="demo-role-chip"
          :class="{ 'demo-role-chip--active': email === 'supervisora@crm.local' }"
          @click="selectDemoUser('supervisora@crm.local')"
        />
        <q-btn
          outline
          dense
          no-caps
          size="sm"
          label="🎧 Agente"
          class="demo-role-chip"
          :class="{ 'demo-role-chip--active': email === 'agente@crm.local' }"
          @click="selectDemoUser('agente@crm.local')"
        />
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
        type="email"
        autocomplete="username"
        :rules="[val => !!val || 'El correo es requerido', val => /.+@.+\..+/.test(val) || 'Ingresa un correo válido']"
        lazy-rules
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
        :type="showPassword ? 'text' : 'password'"
        autocomplete="current-password"
        :rules="[val => !!val || 'La contraseña es requerida']"
        lazy-rules
      >
        <template #prepend>
          <q-icon name="sym_r_key" size="18px" color="teal-4" />
        </template>
        <template #append>
          <q-icon
            :name="showPassword ? 'sym_r_visibility_off' : 'sym_r_visibility'"
            class="cursor-pointer"
            size="18px"
            @click="showPassword = !showPassword"
          />
        </template>
      </q-input>

      <q-btn
        type="submit"
        color="primary"
        label="Ingresar al Workspace"
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

function selectDemoUser(demoEmail: string) {
  email.value = demoEmail
  password.value = 'password'
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
  gap: 20px;
}

.login-page__eyebrow {
  display: inline-flex;
  align-items: center;
  padding: 6px 12px;
  border: 1px solid rgba(16, 185, 129, 0.25);
  border-radius: 999px;
  color: #10b981;
  font-size: 0.75rem;
  font-weight: 700;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  background: rgba(16, 185, 129, 0.08);
}

.login-page__header h2 {
  margin: 12px 0 8px;
  font-family: var(--crm-font-display);
  color: var(--crm-color-ink);
  font-size: 2.2rem;
  line-height: 1;
  letter-spacing: -0.03em;
}

.login-page__header p {
  margin: 0;
  color: var(--crm-color-muted);
  font-size: 0.92rem;
  line-height: 1.6;
}

.login-page__demo-box {
  padding: 12px 14px;
  background: rgba(255, 255, 255, 0.03);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 10px;
}

.demo-role-chip {
  border-color: rgba(255, 255, 255, 0.15);
  color: #cbd5e1;
  border-radius: 6px;
  transition: all 0.2s ease;

  &:hover {
    background: rgba(16, 185, 129, 0.1);
    border-color: #10b981;
    color: #10b981;
  }

  &--active {
    background: rgba(16, 185, 129, 0.15);
    border-color: #10b981;
    color: #10b981;
    font-weight: 600;
  }
}

.login-page__form {
  display: grid;
  gap: 16px;
}

.login-page__error {
  border: 1px solid rgba(239, 68, 68, 0.3);
  background: rgba(239, 68, 68, 0.1);
  color: #fca5a5;
  font-size: 0.88rem;
}

.login-page__submit {
  min-height: 48px;
  font-size: 0.95rem;
  font-weight: 600;
  border-radius: 10px;
  background: linear-gradient(135deg, #10b981 0%, #059669 100%) !important;
  box-shadow: 0 4px 14px -2px rgba(16, 185, 129, 0.35);
}
</style>
