<template>
  <section class="login-page">
    <div class="login-page__header">
      <div class="login-page__eyebrow">
        Acceso
      </div>
      <h2>Iniciar sesion</h2>
      <p>Ingresa a tu workspace con una experiencia privada, segura y ligera.</p>
    </div>

    <q-form class="login-page__form">
      <q-banner
        v-if="authStore.errorMessage"
        inline-actions
        rounded
        class="login-page__error"
      >
        {{ authStore.errorMessage }}
      </q-banner>

      <q-input
        v-model="email"
        label="Correo"
        outlined
        type="email"
        autocomplete="username"
      />
      <q-input
        v-model="password"
        label="Contrasena"
        outlined
        type="password"
        autocomplete="current-password"
      />
      <q-btn
        color="primary"
        label="Entrar"
        unelevated
        class="login-page__submit"
        :loading="authStore.isLoading"
        @click="submit"
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

async function submit() {
  try {
    await authStore.login(email.value, password.value)
    await router.replace({ name: 'dashboard' })
  } catch {
    //
  }
}
</script>

<style scoped lang="scss">
.login-page {
  display: grid;
  gap: 24px;
}

.login-page__eyebrow {
  display: inline-flex;
  align-items: center;
  padding: 8px 12px;
  border: 1px solid rgba(31, 78, 95, 0.08);
  border-radius: 999px;
  color: var(--crm-color-primary);
  font-size: 0.75rem;
  font-weight: 700;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  background: rgba(17, 33, 51, 0.9);
  box-shadow: inset 0 1px 0 rgba(136, 240, 255, 0.08);
}

.login-page__header h2 {
  margin: 14px 0 10px;
  font-family: var(--crm-font-display);
  color: var(--crm-color-ink);
  font-size: 2.6rem;
  line-height: 0.96;
  letter-spacing: -0.04em;
}

.login-page__header p {
  margin: 0;
  color: var(--crm-color-muted);
  line-height: 1.7;
}

.login-page__form {
  display: grid;
  gap: 18px;
}

.login-page__error {
  border-color: rgba(185, 77, 63, 0.16);
  background: linear-gradient(180deg, rgba(185, 77, 63, 0.22) 0%, rgba(40, 18, 21, 0.92) 100%);
  color: #ffd2da;
}

.login-page__submit {
  margin-top: 4px;
  min-height: 52px;
}
</style>
