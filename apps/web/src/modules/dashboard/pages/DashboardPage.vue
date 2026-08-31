<template>
  <AppPage
    title="Dashboard"
    description="Resumen operativo de la organización con métricas de conversaciones, ventas y actividad reciente."
  >
    <div
      v-if="report"
      class="dashboard-container"
    >
      <!-- Fila 1: KPIs Principales estilo Whaticket / Métricas Modernas -->
      <div class="row q-col-gutter-md q-mb-md">
        <!-- KPI 1: Conversaciones Abiertas -->
        <div class="col-12 col-sm-6 col-md-3">
          <q-card flat bordered class="dashboard-kpi-card">
            <q-card-section class="row items-center justify-between">
              <div>
                <div class="dashboard-kpi-card__label">CONVERSACIONES ABIERTAS</div>
                <div class="dashboard-kpi-card__value text-primary">
                  {{ report.summary.open_conversations }}
                </div>
              </div>
              <div class="dashboard-kpi-card__icon-wrap bg-teal-9 text-teal-2">
                <q-icon name="sym_r_forum" size="24px" />
              </div>
            </q-card-section>
          </q-card>
        </div>

        <!-- KPI 2: Conversaciones Pendientes -->
        <div class="col-12 col-sm-6 col-md-3">
          <q-card flat bordered class="dashboard-kpi-card">
            <q-card-section class="row items-center justify-between">
              <div>
                <div class="dashboard-kpi-card__label">CONVERSACIONES PENDIENTES</div>
                <div class="dashboard-kpi-card__value text-amber-5">
                  {{ report.summary.pending_conversations }}
                </div>
              </div>
              <div class="dashboard-kpi-card__icon-wrap bg-amber-10 text-amber-2">
                <q-icon name="sym_r_hourglass_empty" size="24px" />
              </div>
            </q-card-section>
          </q-card>
        </div>

        <!-- KPI 3: Deals Activos -->
        <div class="col-12 col-sm-6 col-md-3">
          <q-card flat bordered class="dashboard-kpi-card">
            <q-card-section class="row items-center justify-between">
              <div>
                <div class="dashboard-kpi-card__label">DEALS ACTIVOS</div>
                <div class="dashboard-kpi-card__value text-light-blue-4">
                  {{ report.summary.active_deals }}
                </div>
              </div>
              <div class="dashboard-kpi-card__icon-wrap bg-blue-10 text-blue-2">
                <q-icon name="sym_r_stacks" size="24px" />
              </div>
            </q-card-section>
          </q-card>
        </div>

        <!-- KPI 4: Ingresos Estimados -->
        <div class="col-12 col-sm-6 col-md-3">
          <q-card flat bordered class="dashboard-kpi-card">
            <q-card-section class="row items-center justify-between">
              <div>
                <div class="dashboard-kpi-card__label">INGRESOS ESTIMADOS</div>
                <div class="dashboard-kpi-card__value text-positive">
                  {{ formatCurrency(report.summary.estimated_revenue) }}
                </div>
              </div>
              <div class="dashboard-kpi-card__icon-wrap bg-green-10 text-green-2">
                <q-icon name="sym_r_payments" size="24px" />
              </div>
            </q-card-section>
          </q-card>
        </div>
      </div>

      <!-- Fila 2: Actividad Reciente & Canales Activos -->
      <div class="row q-col-gutter-md">
        <!-- Columna Izquierda: Actividad Reciente -->
        <div class="col-12 col-md-8">
          <q-card flat bordered class="dashboard-panel-card">
            <q-card-section class="row items-center justify-between q-pb-sm">
              <div class="dashboard-panel-card__title">Actividad Reciente</div>
              <q-badge color="primary" rounded outline>En tiempo real</q-badge>
            </q-card-section>

            <q-card-section class="q-pt-none">
              <div
                v-if="report.recent_activity.length === 0"
                class="text-caption text-grey q-py-lg text-center"
              >
                No hay actividad relevante todavía.
              </div>

              <div v-else class="dashboard-activity-list">
                <div
                  v-for="item in report.recent_activity.slice(0, 5)"
                  :key="`${item.type}-${item.title}-${item.occurred_at}`"
                  class="dashboard-activity-item"
                  @click="goTo(item.href)"
                >
                  <div class="dashboard-activity-item__icon">
                    <q-icon
                      :name="getActivityIcon(item.type)"
                      size="18px"
                      color="primary"
                    />
                  </div>

                  <div class="dashboard-activity-item__content">
                    <div class="row items-center justify-between">
                      <span class="dashboard-activity-item__title">{{ item.title }}</span>
                      <span class="dashboard-activity-item__time">{{ formatDate(item.occurred_at) }}</span>
                    </div>
                    <p class="dashboard-activity-item__desc">{{ item.description }}</p>
                  </div>
                </div>
              </div>
            </q-card-section>
          </q-card>
        </div>

        <!-- Columna Derecha: Canales Activos & Contexto -->
        <div class="col-12 col-md-4 q-gutter-y-md">
          <!-- Canales Activos -->
          <q-card flat bordered class="dashboard-panel-card">
            <q-card-section>
              <div class="dashboard-panel-card__title q-mb-md">Canales Activos</div>

              <div class="q-gutter-y-sm">
                <div
                  v-for="item in report.conversation_channels"
                  :key="item.channel"
                  class="row items-center justify-between dashboard-channel-row"
                >
                  <div class="row items-center q-gutter-x-sm">
                    <q-icon
                      :name="item.channel === 'whatsapp' ? 'sym_r_chat' : 'sym_r_alternate_email'"
                      :color="item.channel === 'whatsapp' ? 'positive' : 'info'"
                      size="20px"
                    />
                    <span class="text-weight-medium">{{ channelLabel(item.channel) }}</span>
                  </div>
                  <q-badge color="dark" class="text-bold q-px-sm">{{ item.total }}</q-badge>
                </div>
              </div>
            </q-card-section>
          </q-card>

          <!-- Contexto Operativo -->
          <q-card flat bordered class="dashboard-panel-card">
            <q-card-section>
              <div class="dashboard-panel-card__title q-mb-sm">Contexto Operativo</div>
              <div class="text-caption text-grey-4 q-gutter-y-xs">
                <div class="row justify-between">
                  <span>Usuario:</span>
                  <span class="text-white text-bold">{{ authStore.user?.name }}</span>
                </div>
                <div class="row justify-between">
                  <span>Organización:</span>
                  <span class="text-primary text-bold">{{ authStore.currentOrganization?.name }}</span>
                </div>
                <div class="row justify-between">
                  <span>Mensajes Hoy:</span>
                  <span class="text-positive text-bold">{{ report.summary.inbound_messages_today }}</span>
                </div>
              </div>
            </q-card-section>
          </q-card>
        </div>
      </div>
    </div>

    <AppLoadingState
      v-else-if="isLoading"
      title="Cargando Dashboard"
      description="Obteniendo indicadores y actividad..."
    />
  </AppPage>
</template>

<script setup lang="ts">
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/modules/auth/stores/auth.store'
import AppLoadingState from '@/shared/components/AppLoadingState.vue'
import AppPage from '@/shared/components/AppPage.vue'
import { useDashboardReport } from '@/modules/reports/composables/useReports'

const router = useRouter()
const authStore = useAuthStore()
const { data: report, isLoading } = useDashboardReport()

function formatCurrency(amount: number) {
  return new Intl.NumberFormat('es-BO', {
    style: 'currency',
    currency: 'BOB',
    maximumFractionDigits: 0,
  }).format(amount)
}

function formatDate(dateStr: string) {
  const date = new Date(dateStr)
  return new Intl.DateTimeFormat('es-BO', {
    hour: '2-digit',
    minute: '2-digit',
    day: '2-digit',
    month: 'short',
  }).format(date)
}

function channelLabel(channel: string) {
  const labels: Record<string, string> = {
    whatsapp: 'WhatsApp Oficial',
    manual: 'Canal Manual',
    email: 'Correo Electrónico',
  }
  return labels[channel] || channel
}

function getActivityIcon(type: string) {
  switch (type) {
    case 'conversation':
      return 'sym_r_chat'
    case 'deal':
      return 'sym_r_monetization_on'
    default:
      return 'sym_r_notifications'
  }
}

function goTo(href: string) {
  if (href) {
    router.push(href)
  }
}
</script>

<style scoped lang="scss">
.dashboard-kpi-card {
  border-radius: 12px;
  background-color: var(--crm-bg-card);
  border: 1px solid var(--crm-color-border);
  transition: transform 0.15s ease, border-color 0.15s ease;

  &:hover {
    border-color: var(--crm-color-border-hover);
    transform: translateY(-1px);
  }

  &__label {
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    color: var(--crm-color-muted);
    margin-bottom: 4px;
  }

  &__value {
    font-size: 1.75rem;
    font-weight: 700;
    line-height: 1.1;
  }

  &__icon-wrap {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
  }
}

.dashboard-panel-card {
  border-radius: 14px;
  background-color: var(--crm-bg-card);
  border: 1px solid var(--crm-color-border);

  &__title {
    font-size: 0.98rem;
    font-weight: 600;
    color: var(--crm-color-ink);
  }
}

.dashboard-activity-list {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.dashboard-activity-item {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 10px 12px;
  border-radius: 10px;
  background: var(--crm-bg-card-hover);
  cursor: pointer;
  transition: all 0.15s ease;

  &:hover {
    background: var(--crm-bg-surface-elevated);
  }

  &__icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    background: rgba(0, 168, 132, 0.12);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    margin-top: 2px;
  }

  &__content {
    flex: 1;
    min-width: 0;
  }

  &__title {
    font-size: 0.88rem;
    font-weight: 600;
    color: var(--crm-color-ink);
  }

  &__time {
    font-size: 0.75rem;
    color: var(--crm-color-muted);
  }

  &__desc {
    margin: 2px 0 0;
    font-size: 0.8rem;
    color: var(--crm-color-muted);
  }
}

.dashboard-channel-row {
  padding: 8px 12px;
  border-radius: 8px;
  background: var(--crm-bg-card-hover);
}
</style>
