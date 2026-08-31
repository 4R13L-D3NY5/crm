<template>
  <div class="whaticket-settings-page">
    <!-- Header -->
    <div class="q-mb-md">
      <h1 class="text-h5 text-bold text-white q-my-none">Configuración</h1>
    </div>

    <!-- Pestañas: General | Atención | Suscripción -->
    <div class="whaticket-subnav-tabs q-mb-lg">
      <button
        class="whaticket-subnav-btn"
        :class="{ 'whaticket-subnav-btn--active': activeTab === 'general' }"
        @click="activeTab = 'general'"
      >
        <q-icon name="sym_r_tune" size="18px" />
        <span>General</span>
      </button>

      <button
        class="whaticket-subnav-btn"
        :class="{ 'whaticket-subnav-btn--active': activeTab === 'service' }"
        @click="activeTab = 'service'"
      >
        <q-icon name="sym_r_support_agent" size="18px" />
        <span>Atención</span>
      </button>

      <button
        class="whaticket-subnav-btn"
        :class="{ 'whaticket-subnav-btn--active': activeTab === 'billing' }"
        @click="activeTab = 'billing'"
      >
        <q-icon name="sym_r_credit_card" size="18px" />
        <span>Suscripción</span>
      </button>
    </div>

    <!-- Panel de Ajustes Generales -->
    <div v-if="activeTab === 'general'" class="q-gutter-y-md" style="max-width: 800px">
      <!-- 1. Idioma padrón -->
      <q-card flat bordered class="whaticket-setting-row-card q-pa-md">
        <div class="row items-center justify-between">
          <div>
            <div class="text-bold text-white">Idioma padrón de los mensajes automáticos.</div>
          </div>
          <q-select
            v-model="settings.default_language"
            :options="['es', 'pt', 'en']"
            dense
            outlined
            dark
            class="whaticket-setting-select"
            @update:model-value="saveSettings"
          />
        </div>
      </q-card>

      <!-- 2. Huso Horario -->
      <q-card flat bordered class="whaticket-setting-row-card q-pa-md">
        <div class="row items-center justify-between">
          <div>
            <div class="text-bold text-white">Huso horario</div>
          </div>
          <q-select
            v-model="settings.timezone"
            :options="['America/La_Paz', 'America/Sao_Paulo', 'America/Bogota']"
            dense
            outlined
            dark
            class="whaticket-setting-select"
            @update:model-value="saveSettings"
          />
        </div>
      </q-card>

      <!-- 3. Ocultar datos de contacto -->
      <q-card flat bordered class="whaticket-setting-row-card q-pa-md">
        <div class="row items-center justify-between">
          <div>
            <div class="text-bold text-white">Ocultar datos de contacto para los usuarios</div>
            <div class="text-caption text-grey-4 q-mt-xs">Los agentes no verán el teléfono ni el correo de los contactos.</div>
          </div>
          <q-toggle v-model="settings.hide_contact_data" dense color="primary" @update:model-value="saveSettings" />
        </div>
      </q-card>

      <!-- 4. Exigir 2FA -->
      <q-card flat bordered class="whaticket-setting-row-card q-pa-md">
        <div class="row items-center justify-between">
          <div>
            <div class="text-bold text-white">Exigir 2FA para todos los usuarios</div>
            <div class="text-caption text-grey-4 q-mt-xs">Todos los usuarios de la empresa deberán activar la autenticación en dos factores para acceder.</div>
          </div>
          <q-toggle v-model="settings.enforce_2fa" dense color="primary" @update:model-value="saveSettings" />
        </div>
      </q-card>
    </div>

    <!-- Panel de Atención -->
    <div v-else-if="activeTab === 'service'" class="q-gutter-y-md" style="max-width: 800px">
      <q-card flat bordered class="whaticket-setting-row-card q-pa-md">
        <div class="row items-center justify-between">
          <div>
            <div class="text-bold text-white">Alerta SLA de Conversaciones no Respondidas</div>
            <div class="text-caption text-grey-4 q-mt-xs">Tiempo máximo de espera sin respuesta humana antes de emitir alerta sonora y visual.</div>
          </div>
          <div class="row items-center q-gutter-x-sm">
            <q-input
              v-model.number="settings.sla_timeout_minutes"
              type="number"
              dense
              outlined
              dark
              style="width: 80px"
              @blur="saveSettings"
            />
            <span class="text-caption text-grey-4">minutos</span>
          </div>
        </div>
      </q-card>
    </div>

    <!-- Panel de Suscripción -->
    <div v-else class="q-gutter-y-md" style="max-width: 800px">
      <q-card flat bordered class="whaticket-setting-row-card q-pa-md">
        <div class="text-bold text-white text-subtitle1">Plan Activo: XpertiFlow Enterprise Pro</div>
        <div class="text-caption text-grey-4 q-mt-xs">Conexiones multicanal ilimitadas, RAG con pgvector, WebSockets y Modo Supervisor Fantasma activo.</div>
      </q-card>
    </div>
  </div>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { http } from '@/shared/api/http'
import { useAppNotify } from '@/shared/composables/useAppNotify'

const notify = useAppNotify()

const activeTab = ref<'general' | 'service' | 'billing'>('general')

const settings = reactive({
  default_language: 'es',
  timezone: 'America/La_Paz',
  hide_contact_data: false,
  enforce_2fa: false,
  sla_timeout_minutes: 10,
})

onMounted(async () => {
  try {
    const response = await http.get('/settings/workspace')
    if (response.data?.data) {
      Object.assign(settings, response.data.data)
    }
  } catch {
    // Mantener defaults si no está autenticado
  }
})

async function saveSettings() {
  try {
    await http.put('/settings/workspace', settings)
    notify.success({ message: 'Configuración del workspace guardada.' })
  } catch {
    notify.warning({ message: 'Ajuste actualizado localmente.' })
  }
}
</script>

<style scoped lang="scss">
.whaticket-settings-page {
  padding: 24px 32px;
  background-color: var(--crm-bg-app);
  min-height: calc(100vh - 52px);
}

.whaticket-subnav-tabs {
  display: flex;
  gap: 8px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
  padding-bottom: 8px;
}

.whaticket-subnav-btn {
  display: flex;
  align-items: center;
  gap: 8px;
  background: transparent;
  border: none;
  color: #8696a0;
  padding: 8px 16px;
  border-radius: 8px;
  font-size: 0.88rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.15s ease;

  &:hover {
    background: rgba(255, 255, 255, 0.04);
    color: #e9edef;
  }

  &--active {
    background: #182229;
    color: #ffffff;
    font-weight: 600;
  }
}

.whaticket-setting-row-card {
  background-color: #182229 !important;
  border: 1px solid rgba(255, 255, 255, 0.08) !important;
  border-radius: 12px !important;
}

.whaticket-setting-select {
  min-width: 220px;
  .q-field__control {
    background: #111b21 !important;
    border-radius: 8px !important;
    height: 36px !important;
    min-height: 36px !important;
    font-size: 0.85rem;
  }
}
</style>
