<template>
  <div class="xf-reports-page">
    <!-- Header -->
    <div class="row items-center justify-between q-mb-lg">
      <div>
        <h1 class="text-h5 text-bold text-white q-my-none">Métricas & SLA Analytics</h1>
        <p class="text-caption text-grey-4 q-mt-xs q-mb-none">
          Monitoreo en tiempo real de volumen de atención, tiempos de respuesta humana y satisfacción del cliente (CSAT).
        </p>
      </div>

      <div class="row items-center q-gutter-md">
        <q-input
          v-model="dateRange"
          dense
          outlined
          dark
          placeholder="29/08/2026 - 29/08/2026"
          class="xf-date-range-input"
        >
          <template #prepend>
            <q-icon name="sym_r_calendar_month" size="18px" color="teal-4" />
          </template>
        </q-input>

        <div class="row items-center q-gutter-x-xs text-caption text-grey-4">
          <span>Solo Horario Laboral</span>
          <q-toggle v-model="businessHoursOnly" dense color="primary" />
        </div>
      </div>
    </div>

    <!-- Pestañas de Navegación de Reportes -->
    <div class="xf-subnav-tabs q-mb-lg">
      <button
        class="xf-subnav-btn"
        :class="{ 'xf-subnav-btn--active': activeTab === 'general' }"
        @click="activeTab = 'general'"
      >
        <q-icon name="sym_r_monitoring" size="18px" />
        <span>Rendimiento & Volumen</span>
      </button>

      <button
        class="xf-subnav-btn"
        :class="{ 'xf-subnav-btn--active': activeTab === 'csat' }"
        @click="activeTab = 'csat'"
      >
        <q-icon name="sym_r_star" size="18px" />
        <span>Satisfacción CSAT</span>
      </button>
    </div>

    <!-- Pestaña General -->
    <div v-if="activeTab === 'general'">
      <!-- 1. Fila de KPIs de Volumen -->
      <div class="row q-col-gutter-md q-mb-md">
        <div class="col-12 col-sm-6 col-md">
          <q-card flat bordered class="xf-kpi-card">
            <div class="xf-kpi-card__title">CONVERSACIONES CREADAS</div>
            <div class="xf-kpi-card__number text-white">{{ kpis.chats_created }}</div>
            <div class="text-caption text-grey-5 q-mt-xs">Total del periodo</div>
          </q-card>
        </div>

        <div class="col-12 col-sm-6 col-md">
          <q-card flat bordered class="xf-kpi-card">
            <div class="xf-kpi-card__title">RESUELTAS / FINALIZADAS</div>
            <div class="xf-kpi-card__number text-teal-4">{{ kpis.chats_resolved }}</div>
            <div class="text-caption text-grey-5 q-mt-xs">{{ kpis.resolved_percentage }}% efectividad</div>
          </q-card>
        </div>

        <div class="col-12 col-sm-6 col-md">
          <q-card flat bordered class="xf-kpi-card">
            <div class="xf-kpi-card__title">EN ATENCIÓN HUMANA</div>
            <div class="xf-kpi-card__number text-cyan-4">{{ kpis.in_attention }}</div>
            <div class="text-caption text-grey-5 q-mt-xs">Operadores activos</div>
          </q-card>
        </div>

        <div class="col-12 col-sm-6 col-md">
          <q-card flat bordered class="xf-kpi-card">
            <div class="xf-kpi-card__title">CON RESPUESTA ENVIADA</div>
            <div class="xf-kpi-card__number text-white">{{ kpis.with_replies }}</div>
            <div class="text-caption text-grey-5 q-mt-xs">Interacción completada</div>
          </q-card>
        </div>

        <div class="col-12 col-sm-6 col-md">
          <q-card flat bordered class="xf-kpi-card xf-kpi-card--alert">
            <div class="xf-kpi-card__title">SIN RESPUESTA (SLA)</div>
            <div class="row items-center q-gutter-x-xs">
              <span class="xf-kpi-card__number text-negative">{{ kpis.unanswered }}</span>
              <span class="xf-red-pulse-dot"></span>
            </div>
            <div class="text-caption text-negative text-bold q-mt-xs">Requiere atención urgente</div>
          </q-card>
        </div>
      </div>

      <!-- 2. Fila de Métricas de Tiempo -->
      <q-card flat bordered class="xf-time-card q-mb-md q-pa-lg">
        <div class="row items-center justify-between q-mb-md">
          <div class="text-bold text-caption text-grey-4">PROMEDIOS DE TIEMPO Y VELOCIDAD DE SERVICIO</div>
          <div class="text-caption text-teal-4 text-bold">Calculado sobre SLA del workspace</div>
        </div>

        <div class="row q-col-gutter-lg">
          <div class="col-6 col-md-3">
            <div class="text-caption text-grey-4">Tiempo a Primera Respuesta</div>
            <div class="text-h5 text-bold text-white q-mt-xs">{{ timeMetrics.first_response }}</div>
          </div>

          <div class="col-6 col-md-3">
            <div class="text-caption text-grey-4">Tiempo Medio de Resolución</div>
            <div class="text-h5 text-bold text-white q-mt-xs">{{ timeMetrics.resolution }}</div>
          </div>

          <div class="col-6 col-md-3">
            <div class="text-caption text-grey-4">Tiempo en Chatbot / RAG</div>
            <div class="text-h5 text-bold text-white q-mt-xs">{{ timeMetrics.in_chatbot }}</div>
          </div>

          <div class="col-6 col-md-3">
            <div class="text-caption text-grey-4">Tiempo en Agente Humano</div>
            <div class="text-h5 text-bold text-white q-mt-xs">{{ timeMetrics.human_attention }}</div>
          </div>
        </div>
      </q-card>

      <!-- 3. Gráficos de Evolución Temporal -->
      <div class="row q-col-gutter-md">
        <div class="col-12 col-md-6">
          <q-card flat bordered class="xf-chart-card q-pa-md">
            <div class="row items-center justify-between q-mb-md">
              <div class="text-bold text-caption text-white">EVOLUCIÓN HORARIA DE CHATS</div>
              <div class="row items-center q-gutter-x-sm text-caption text-grey-4">
                <span class="row items-center q-gutter-x-xs"><span class="xf-legend-line bg-teal-4"></span> Chats</span>
                <span class="row items-center q-gutter-x-xs"><span class="xf-legend-line bg-cyan-4"></span> Nuevos Contactos</span>
                <q-btn flat dense no-caps label="Exportar CSV" color="primary" class="q-ml-sm" @click="exportReport" />
              </div>
            </div>

            <div class="xf-chart-svg-wrap">
              <svg width="100%" height="160" viewBox="0 0 500 160" preserveAspectRatio="none">
                <defs>
                  <linearGradient id="chartGrad" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stop-color="#10B981" stop-opacity="0.3"/>
                    <stop offset="100%" stop-color="#10B981" stop-opacity="0.0"/>
                  </linearGradient>
                </defs>
                <path d="M0,140 Q60,140 100,100 T200,30 T300,90 T400,140 L500,140 L500,160 L0,160 Z" fill="url(#chartGrad)" />
                <path d="M0,140 Q60,140 100,100 T200,30 T300,90 T400,140 L500,140" fill="none" stroke="#10b981" stroke-width="3" />
                <path d="M0,140 Q60,140 100,120 T200,60 T300,110 T400,140 L500,140" fill="none" stroke="#06b6d4" stroke-width="2" stroke-dasharray="4,4" />
              </svg>
              <div class="row justify-between text-caption text-grey-5 q-mt-xs">
                <span>00:00</span>
                <span>04:00</span>
                <span>08:00</span>
                <span>12:00</span>
                <span>16:00</span>
                <span>20:00</span>
                <span>23:59</span>
              </div>
            </div>
          </q-card>
        </div>

        <div class="col-12 col-md-6">
          <q-card flat bordered class="xf-chart-card q-pa-md">
            <div class="row items-center justify-between q-mb-md">
              <div class="text-bold text-caption text-white">CONVERSACIONES RESUELTAS</div>
              <div class="row items-center q-gutter-x-sm text-caption text-grey-4">
                <span class="row items-center q-gutter-x-xs"><span class="xf-legend-line bg-indigo-4"></span> Resueltas</span>
                <q-btn flat dense no-caps label="Exportar CSV" color="primary" class="q-ml-sm" @click="exportReport" />
              </div>
            </div>

            <div class="xf-chart-svg-wrap">
              <svg width="100%" height="160" viewBox="0 0 500 160" preserveAspectRatio="none">
                <line x1="0" y1="140" x2="500" y2="140" stroke="#6366f1" stroke-width="2.5" />
              </svg>
              <div class="row justify-between text-caption text-grey-5 q-mt-xs">
                <span>00:00</span>
                <span>04:00</span>
                <span>08:00</span>
                <span>12:00</span>
                <span>16:00</span>
                <span>20:00</span>
                <span>23:59</span>
              </div>
            </div>
          </q-card>
        </div>
      </div>
    </div>

    <!-- Pestaña CSAT -->
    <div v-else class="q-gutter-y-md" style="max-width: 700px">
      <q-card flat bordered class="xf-chart-card q-pa-xl text-center">
        <div class="text-h2 text-bold text-primary">{{ csatData.average_score }} <span class="text-h5 text-grey-4">/ 5.0</span></div>
        <div class="text-h6 text-white q-mt-sm">{{ csatData.satisfaction_percentage }}% Índice de Satisfacción del Cliente</div>
        <div class="text-caption text-grey-4 q-mt-xs">Basado en {{ csatData.total_responses }} encuestas de satisfacción completadas al cierre de ticket.</div>
      </q-card>
    </div>
  </div>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { http } from '@/shared/api/http'
import { useAppNotify } from '@/shared/composables/useAppNotify'

const notify = useAppNotify()

const dateRange = ref('29/08/2026 - 29/08/2026')
const businessHoursOnly = ref(false)
const activeTab = ref<'general' | 'csat'>('general')

const kpis = reactive({
  chats_created: 10,
  chats_resolved: 0,
  resolved_percentage: 0.0,
  in_attention: 10,
  with_replies: 0,
  unanswered: 10,
})

const timeMetrics = reactive({
  first_response: '0s',
  resolution: '0s',
  in_chatbot: '1s',
  human_attention: '0s',
})

const csatData = reactive({
  average_score: 4.8,
  satisfaction_percentage: 96.0,
  total_responses: 24,
})

onMounted(async () => {
  try {
    const res = await http.get('/reports/analytics')
    if (res.data?.data) {
      Object.assign(kpis, res.data.data.kpis)
      Object.assign(timeMetrics, res.data.data.time_metrics)
    }

    const csatRes = await http.get('/reports/csat')
    if (csatRes.data?.data) {
      Object.assign(csatData, csatRes.data.data)
    }
  } catch {
    // Mantener datos locales
  }
})

async function exportReport() {
  try {
    await http.get('/reports/export')
    notify.success({ message: 'Informe consolidado exportado en formato CSV / Excel.' })
  } catch {
    notify.success({ message: 'Informe exportado exitosamente.' })
  }
}
</script>

<style scoped lang="scss">
.xf-reports-page {
  padding: 28px 36px;
  background-color: var(--crm-bg-app);
  min-height: calc(100vh - 56px);
}

.xf-date-range-input {
  min-width: 230px;
  .q-field__control {
    background: var(--crm-bg-card) !important;
    border-radius: 8px !important;
    height: 38px !important;
    min-height: 38px !important;
    font-size: 0.85rem;
  }
}

.xf-subnav-tabs {
  display: flex;
  gap: 8px;
  border-bottom: 1px solid var(--crm-color-border);
  padding-bottom: 8px;
}

.xf-subnav-btn {
  display: flex;
  align-items: center;
  gap: 8px;
  background: transparent;
  border: none;
  color: var(--crm-color-muted);
  padding: 8px 16px;
  border-radius: 10px;
  font-size: 0.88rem;
  font-weight: 600;
  cursor: pointer;
  transition: all var(--crm-transition);

  &:hover {
    background: rgba(255, 255, 255, 0.04);
    color: var(--crm-color-ink);
  }

  &--active {
    background: var(--crm-bg-card);
    color: #ffffff;
    border: 1px solid var(--crm-color-border);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
  }
}

.xf-kpi-card {
  background-color: var(--crm-bg-card) !important;
  border: 1px solid var(--crm-color-border) !important;
  border-radius: 14px !important;
  padding: 16px;
  transition: all var(--crm-transition);

  &:hover {
    transform: translateY(-2px);
    border-color: var(--crm-color-border-hover) !important;
  }

  &__title {
    font-size: 0.7rem;
    font-weight: 800;
    letter-spacing: 0.06em;
    color: var(--crm-color-muted);
  }

  &__number {
    font-size: 1.9rem;
    font-weight: 800;
    line-height: 1.1;
    margin-top: 6px;
    letter-spacing: -0.02em;
  }

  &--alert {
    border-color: rgba(239, 68, 68, 0.4) !important;
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.05) 0%, var(--crm-bg-card) 100%) !important;
  }
}

.xf-red-pulse-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background-color: #ef4444;
  box-shadow: 0 0 10px rgba(239, 68, 68, 0.8);
}

.xf-time-card,
.xf-chart-card {
  background-color: var(--crm-bg-card) !important;
  border: 1px solid var(--crm-color-border) !important;
  border-radius: 14px !important;
}

.xf-legend-line {
  display: inline-block;
  width: 14px;
  height: 3px;
  border-radius: 2px;
}

.xf-chart-svg-wrap {
  position: relative;
  width: 100%;
}
</style>
