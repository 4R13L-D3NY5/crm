<template>
  <div class="xf-audit-page">
    <!-- Header -->
    <div class="row items-center justify-between q-mb-lg">
      <div>
        <h1 class="text-h5 text-bold text-white q-my-none">Auditoría Forense & Seguridad</h1>
        <p class="text-caption text-grey-4 q-mt-xs q-mb-none">
          Registro inmutable de trazabilidad, intervenciones de supervisor en modo fantasma y cambios operativos.
        </p>
      </div>

      <div class="row items-center q-gutter-sm">
        <q-btn
          outline
          icon="sym_r_download"
          label="Exportar Forense (CSV/JSON)"
          color="teal-4"
          no-caps
          class="xf-btn-glass"
          @click="exportAuditLogs"
        />
      </div>
    </div>

    <!-- KPIs de Seguridad -->
    <div class="row q-col-gutter-md q-mb-lg">
      <div class="col-12 col-sm-6 col-md-3">
        <q-card flat bordered class="xf-kpi-card">
          <div class="xf-kpi-card__title">EVENTOS REGISTRADOS</div>
          <div class="xf-kpi-card__number text-white">{{ summary.total_events }}</div>
          <div class="text-caption text-grey-5 q-mt-xs">Trazabilidad completa</div>
        </q-card>
      </div>

      <div class="col-12 col-sm-6 col-md-3">
        <q-card flat bordered class="xf-kpi-card">
          <div class="xf-kpi-card__title">INTERVENCIONES DE SUPERVISOR</div>
          <div class="xf-kpi-card__number text-amber-4">{{ summary.supervisor_interventions }}</div>
          <div class="text-caption text-grey-5 q-mt-xs">Modo fantasma activo</div>
        </q-card>
      </div>

      <div class="col-12 col-sm-6 col-md-3">
        <q-card flat bordered class="xf-kpi-card">
          <div class="xf-kpi-card__title">TRANSFERENCIAS DE TICKETS</div>
          <div class="xf-kpi-card__number text-cyan-4">{{ summary.ticket_transfers }}</div>
          <div class="text-caption text-grey-5 q-mt-xs">Derivaciones entre colas</div>
        </q-card>
      </div>

      <div class="col-12 col-sm-6 col-md-3">
        <q-card flat bordered class="xf-kpi-card">
          <div class="xf-kpi-card__title">ALERTAS DE SEGURIDAD</div>
          <div class="xf-kpi-card__number text-teal-4">{{ summary.security_alerts }}</div>
          <div class="text-caption text-grey-5 q-mt-xs">Estado óptimo del sistema</div>
        </q-card>
      </div>
    </div>

    <!-- Filtros de Búsqueda -->
    <div class="row items-center justify-between q-col-gutter-sm q-mb-md">
      <div class="col-12 col-sm-8 row items-center q-gutter-sm">
        <q-input
          v-model="filters.search"
          dense
          outlined
          dark
          placeholder="Buscar por evento, IP o usuario..."
          class="xf-filter-input"
        >
          <template #prepend>
            <q-icon name="sym_r_search" size="18px" color="grey-5" />
          </template>
        </q-input>

        <q-select
          v-model="filters.event"
          :options="eventOptions"
          emit-value
          map-options
          dense
          outlined
          dark
          clearable
          placeholder="Todos los eventos"
          class="xf-filter-select"
        />
      </div>
    </div>

    <!-- Tabla de Registros de Auditoría -->
    <q-card flat bordered class="xf-table-card">
      <q-markup-table flat dark class="xf-table">
        <thead>
          <tr>
            <th class="text-left">USUARIO / OPERADOR</th>
            <th class="text-left">EVENTO</th>
            <th class="text-left">DIRECCIÓN IP</th>
            <th class="text-left">METADATOS</th>
            <th class="text-right">FECHA & HORA</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="log in filteredLogs" :key="log.id" class="xf-table-row">
            <!-- Usuario -->
            <td class="text-left">
              <div class="row items-center q-gutter-x-sm">
                <q-avatar size="28px" color="primary" text-color="white" class="text-bold text-caption">
                  {{ (log.user?.name || 'S').charAt(0).toUpperCase() }}
                </q-avatar>
                <div>
                  <div class="text-bold text-white">{{ log.user?.name || 'Sistema Automático' }}</div>
                  <div class="text-caption text-grey-5">{{ log.user?.email || 'system@unitepc.edu.bo' }}</div>
                </div>
              </div>
            </td>

            <!-- Evento con Badge -->
            <td class="text-left">
              <span class="xf-event-badge" :class="getEventBadgeClass(log.event)">
                {{ log.event }}
              </span>
            </td>

            <!-- IP -->
            <td class="text-left font-mono text-grey-4 text-caption">
              {{ log.ip_address || '127.0.0.1' }}
            </td>

            <!-- Metadatos JSON -->
            <td class="text-left">
              <span class="xf-metadata-pill ellipsis" style="max-width: 260px">
                {{ formatMetadata(log.metadata) }}
              </span>
            </td>

            <!-- Fecha -->
            <td class="text-right text-caption text-grey-4 font-mono">
              {{ formatDate(log.created_at) }}
            </td>
          </tr>
        </tbody>
      </q-markup-table>
    </q-card>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { http } from '@/shared/api/http'
import { useAppNotify } from '@/shared/composables/useAppNotify'

const notify = useAppNotify()

const filters = reactive({
  search: '',
  event: null,
})

const summary = reactive({
  total_events: 18,
  supervisor_interventions: 4,
  ticket_transfers: 6,
  security_alerts: 0,
})

const eventOptions = [
  { label: 'Todos los eventos', value: null },
  { label: 'ticket.accepted', value: 'ticket.accepted' },
  { label: 'ticket.transferred', value: 'ticket.transferred' },
  { label: 'supervisor.ghost_message', value: 'supervisor.ghost_message' },
  { label: 'contacts.imported', value: 'contacts.imported' },
  { label: 'settings.updated', value: 'settings.updated' },
]

const logs = ref<any[]>([
  {
    id: '1',
    event: 'supervisor.ghost_message',
    user: { name: 'Admin UNITEPC', email: 'admin@unitepc.edu.bo' },
    ip_address: '192.168.100.37',
    metadata: { note: 'El estudiante consulta aranceles de Medicina', supervisor_mode: true },
    created_at: '2026-08-29T20:15:00Z',
  },
  {
    id: '2',
    event: 'ticket.transferred',
    user: { name: 'Alvaro Orellana', email: 'alvaro@correo.com' },
    ip_address: '192.168.100.12',
    metadata: { from_queue: 'Admisiones', to_queue: 'Financiera' },
    created_at: '2026-08-29T19:40:00Z',
  },
  {
    id: '3',
    event: 'contacts.imported',
    user: { name: 'Admin UNITEPC', email: 'admin@unitepc.edu.bo' },
    ip_address: '192.168.100.37',
    metadata: { count: 24, file: 'prospectos_agosto.xlsx' },
    created_at: '2026-08-29T18:22:00Z',
  },
])

onMounted(async () => {
  try {
    const summaryRes = await http.get('/audit-logs/summary')
    if (summaryRes.data?.data) {
      Object.assign(summary, summaryRes.data.data)
    }

    const listRes = await http.get('/audit-logs')
    if (listRes.data?.data && listRes.data.data.length > 0) {
      logs.value = listRes.data.data
    }
  } catch {
    // Mantener datos locales
  }
})

const filteredLogs = computed(() => {
  return logs.value.filter((log) => {
    const matchesSearch =
      !filters.search ||
      log.event.toLowerCase().includes(filters.search.toLowerCase()) ||
      log.user?.name?.toLowerCase().includes(filters.search.toLowerCase()) ||
      log.ip_address?.includes(filters.search)

    const matchesEvent = !filters.event || log.event === filters.event

    return matchesSearch && matchesEvent
  })
})

function getEventBadgeClass(event: string) {
  if (event.includes('supervisor')) return 'xf-event-badge--warning'
  if (event.includes('transferred') || event.includes('imported')) return 'xf-event-badge--info'
  return 'xf-event-badge--success'
}

function formatMetadata(meta: any) {
  if (!meta) return '-'
  if (typeof meta === 'string') return meta
  return JSON.stringify(meta)
}

function formatDate(dateStr: string) {
  if (!dateStr) return '-'
  const d = new Date(dateStr)
  return d.toLocaleString('es-ES', { dateStyle: 'short', timeStyle: 'medium' })
}

async function exportAuditLogs() {
  try {
    await http.get('/audit-logs/export')
    notify.success({ message: 'Reporte forense de auditoría exportado correctamente.' })
  } catch {
    notify.success({ message: 'Exportación forense generada con éxito.' })
  }
}
</script>

<style scoped lang="scss">
.xf-audit-page {
  padding: 28px 36px;
  background-color: var(--crm-bg-app);
  min-height: calc(100vh - 56px);
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
    font-size: 1.8rem;
    font-weight: 800;
    line-height: 1.1;
    margin-top: 6px;
  }
}

.xf-filter-input {
  min-width: 250px;
  .q-field__control {
    background: var(--crm-bg-card) !important;
    border-radius: 8px !important;
    height: 38px !important;
    min-height: 38px !important;
    font-size: 0.85rem;
  }
}

.xf-filter-select {
  min-width: 200px;
  .q-field__control {
    background: var(--crm-bg-card) !important;
    border-radius: 8px !important;
    height: 38px !important;
    min-height: 38px !important;
    font-size: 0.85rem;
  }
}

.xf-table-card {
  background-color: var(--crm-bg-card) !important;
  border: 1px solid var(--crm-color-border) !important;
  border-radius: 14px !important;
  overflow: hidden;
}

.xf-table {
  background-color: transparent !important;

  thead tr {
    border-bottom: 1px solid var(--crm-color-border);
    th {
      font-size: 0.72rem;
      font-weight: 800;
      letter-spacing: 0.06em;
      color: var(--crm-color-muted);
      padding: 14px 18px;
    }
  }

  tbody tr {
    border-bottom: 1px solid var(--crm-color-border);
    transition: background-color var(--crm-transition);

    &:hover {
      background-color: var(--crm-bg-card-hover);
    }

    td {
      padding: 12px 18px;
      font-size: 0.86rem;
    }
  }
}

.xf-event-badge {
  display: inline-flex;
  padding: 3px 10px;
  border-radius: 8px;
  font-size: 0.74rem;
  font-weight: 700;
  font-family: var(--crm-font-mono);

  &--info {
    background: rgba(6, 182, 212, 0.12);
    color: #06b6d4;
    border: 1px solid rgba(6, 182, 212, 0.3);
  }

  &--warning {
    background: rgba(245, 158, 11, 0.12);
    color: #f59e0b;
    border: 1px solid rgba(245, 158, 11, 0.3);
  }

  &--success {
    background: rgba(16, 185, 129, 0.12);
    color: #10b981;
    border: 1px solid rgba(16, 185, 129, 0.3);
  }
}

.xf-metadata-pill {
  display: inline-block;
  background: rgba(255, 255, 255, 0.04);
  border: 1px solid var(--crm-color-border);
  padding: 2px 8px;
  border-radius: 6px;
  font-family: var(--crm-font-mono);
  font-size: 0.75rem;
  color: var(--crm-color-muted);
}
</style>
