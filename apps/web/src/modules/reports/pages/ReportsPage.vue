<template>
  <AppPage
    eyebrow="Insights"
    title="Reportes"
    description="Resumen operativo del CRM con foco en conversaciones, deals y actividad reciente."
  >
    <template
      v-if="report"
      #actions
    >
      <q-badge
        color="primary"
        outline
        class="reports-page__badge"
      >
        {{ activeOrganizationName }}
      </q-badge>
    </template>

    <AppLoadingState
      v-if="isLoading"
      title="Cargando metricas"
      description="Estamos consolidando estados, canales y actividad reciente del CRM."
    />

    <div
      v-else-if="report"
      class="reports-page"
    >
      <div class="reports-page__summary">
        <q-card
          v-for="item in summaryCards"
          :key="item.label"
          flat
          bordered
          class="reports-card"
        >
          <q-card-section>
            <div class="reports-card__label">
              {{ item.label }}
            </div>
            <div class="reports-card__value">
              {{ item.value }}
            </div>
          </q-card-section>
        </q-card>
      </div>

      <div class="reports-page__grid">
        <q-card
          flat
          bordered
          class="reports-card"
        >
          <q-card-section>
            <div class="reports-card__label">
              Conversaciones por canal
            </div>
            <div class="reports-list">
              <div
                v-for="item in report.conversation_channels"
                :key="item.channel"
                class="reports-list__row"
              >
                <span>{{ channelLabel(item.channel) }}</span>
                <strong>{{ item.total }}</strong>
              </div>
            </div>
          </q-card-section>
        </q-card>

        <q-card
          flat
          bordered
          class="reports-card"
        >
          <q-card-section>
            <div class="reports-card__label">
              Conversaciones por estado
            </div>
            <div class="reports-list">
              <div
                v-for="item in report.conversation_statuses"
                :key="item.status"
                class="reports-list__row"
              >
                <span>{{ statusLabel(item.status) }}</span>
                <strong>{{ item.total }}</strong>
              </div>
            </div>
          </q-card-section>
        </q-card>

        <q-card
          flat
          bordered
          class="reports-card reports-card--wide"
        >
          <q-card-section>
            <div class="reports-card__label">
              Deals por estado
            </div>
            <div class="reports-list">
              <div
                v-for="item in report.deal_statuses"
                :key="item.status"
                class="reports-list__row"
              >
                <span>{{ dealStatusLabel(item.status) }}</span>
                <strong>{{ item.total }} - {{ currency(item.total_amount) }}</strong>
              </div>
            </div>
          </q-card-section>
        </q-card>

        <q-card
          flat
          bordered
          class="reports-card reports-card--wide"
        >
          <q-card-section>
            <div class="reports-card__label">
              Actividad reciente
            </div>

            <div
              v-if="report.recent_activity.length === 0"
              class="reports-empty"
            >
              Todavia no hay actividad reciente para mostrar.
            </div>

            <div
              v-else
              class="reports-activity"
            >
              <button
                v-for="item in report.recent_activity"
                :key="`${item.type}-${item.title}-${item.occurred_at}`"
                type="button"
                class="reports-activity__item"
                @click="goTo(item.href)"
              >
                <div class="reports-activity__header">
                  <span class="reports-activity__type">
                    {{ activityTypeLabel(item.type) }}
                  </span>
                  <span class="reports-activity__date">
                    {{ formatDate(item.occurred_at) }}
                  </span>
                </div>
                <strong>{{ item.title }}</strong>
                <p>{{ item.description }}</p>
                <small>{{ item.meta }}</small>
              </button>
            </div>
          </q-card-section>
        </q-card>
      </div>
    </div>

    <AppEmptyState
      v-else
      title="No se pudo cargar el resumen"
      description="Prueba recargar la vista o espera a que existan mas datos operativos en el workspace."
      icon="sym_r_monitoring"
    />
  </AppPage>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'

import { useAuthStore } from '@/modules/auth/stores/auth.store'
import AppEmptyState from '@/shared/components/AppEmptyState.vue'
import AppLoadingState from '@/shared/components/AppLoadingState.vue'
import AppPage from '@/shared/components/AppPage.vue'

import { useDashboardReport } from '../composables/useReports'

const authStore = useAuthStore()
const router = useRouter()
const { data, isLoading } = useDashboardReport()

const report = computed(() => data.value ?? null)

const activeOrganizationName = computed(
  () => authStore.currentOrganization?.name ?? 'Sin organizacion',
)

const summaryCards = computed(() => {
  if (!report.value) {
    return []
  }

  return [
    { label: 'Conversaciones abiertas', value: report.value.summary.open_conversations },
    { label: 'Conversaciones pendientes', value: report.value.summary.pending_conversations },
    { label: 'Deals activos', value: report.value.summary.active_deals },
    { label: 'Ingresos estimados', value: currency(report.value.summary.estimated_revenue) },
  ]
})

function currency(value: number) {
  return new Intl.NumberFormat('es-BO', {
    style: 'currency',
    currency: 'BOB',
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
  }).format(value)
}

function formatDate(value: string) {
  return new Intl.DateTimeFormat('es-BO', {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(new Date(value))
}

function channelLabel(channel: string) {
  const labels: Record<string, string> = {
    manual: 'Manual',
    whatsapp: 'WhatsApp',
    email: 'Email',
  }

  return labels[channel] ?? channel
}

function statusLabel(status: string) {
  const labels: Record<string, string> = {
    open: 'Abierta',
    pending: 'Pendiente',
    resolved: 'Resuelta',
  }

  return labels[status] ?? status
}

function dealStatusLabel(status: string) {
  const labels: Record<string, string> = {
    open: 'Abierto',
    won: 'Ganado',
    lost: 'Perdido',
  }

  return labels[status] ?? status
}

function activityTypeLabel(type: 'conversation' | 'deal') {
  return type === 'conversation' ? 'Conversacion' : 'Deal'
}

function goTo(href: string) {
  void router.push(href)
}
</script>

<style scoped lang="scss">
.reports-page {
  display: grid;
  gap: 20px;
}

.reports-page__loading {
  padding: 32px;
  border: 1px dashed rgba(136, 240, 255, 0.22);
  border-radius: 24px;
  color: var(--crm-color-muted);
  background: rgba(9, 21, 34, 0.78);
}

.reports-page__badge {
  padding: 10px 14px;
}

.reports-page__summary,
.reports-page__grid {
  display: grid;
  grid-template-columns: repeat(12, minmax(0, 1fr));
  gap: 20px;
}

.reports-card {
  grid-column: span 3;
  border-radius: 24px;
  background:
    radial-gradient(circle at top right, rgba(138, 143, 255, 0.08), transparent 28%),
    rgba(9, 21, 34, 0.88);
}

.reports-card--wide {
  grid-column: span 6;
}

.reports-card__label {
  color: var(--crm-color-muted);
  font-size: 0.8rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.reports-card__value {
  margin-top: 12px;
  color: white;
  font-size: 2.2rem;
  font-weight: 800;
  letter-spacing: -0.04em;
}

.reports-list,
.reports-activity {
  display: grid;
  gap: 12px;
  margin-top: 16px;
}

.reports-list__row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 14px;
  border-radius: 16px;
  background: rgba(14, 29, 46, 0.84);
  border: 1px solid rgba(136, 240, 255, 0.08);
  color: var(--crm-color-ink);
}

.reports-empty {
  margin-top: 16px;
  color: var(--crm-color-muted);
}

.reports-activity__item {
  display: grid;
  gap: 8px;
  padding: 16px;
  border: 0;
  border-radius: 18px;
  text-align: left;
  background: rgba(14, 29, 46, 0.84);
  border: 1px solid rgba(136, 240, 255, 0.08);
  color: inherit;
  cursor: pointer;
}

.reports-activity__item:hover {
  transform: translateY(-2px);
  border-color: rgba(136, 240, 255, 0.18);
}

.reports-activity__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  color: var(--crm-color-muted);
  font-size: 0.85rem;
}

.reports-activity__type {
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.reports-activity__item p,
.reports-activity__item small {
  margin: 0;
  color: var(--crm-color-muted);
}

@media (max-width: 1200px) {
  .reports-card,
  .reports-card--wide {
    grid-column: span 6;
  }
}

@media (max-width: 768px) {
  .reports-card,
  .reports-card--wide {
    grid-column: span 12;
  }
}
</style>
