<template>
  <div class="xf-reports-page">
    <!-- Header Principal -->
    <div class="row items-center justify-between q-mb-lg">
      <div>
        <div class="row items-center q-gutter-x-sm">
          <h1 class="text-h5 text-bold text-white q-my-none">Reportes & Analítica Ejecutiva</h1>
          <q-badge color="teal-9" text-color="teal-2" rounded class="q-px-sm text-bold">
            Admin Suite
          </q-badge>
        </div>
        <p class="text-caption text-grey-4 q-mt-xs q-mb-none">
          Supervisión en tiempo real de SLAs, asesores, procedencia y demanda por Sedes y Carreras.
        </p>
      </div>

      <!-- Barra de Filtros de Período y Exportación -->
      <div class="row items-center q-gutter-sm">
        <!-- Selector de Presets de Fecha -->
        <q-btn-toggle
          v-model="selectedPreset"
          dense
          no-caps
          rounded
          toggle-color="primary"
          color="dark"
          text-color="grey-4"
          :options="[
            { label: 'Hoy', value: 'today' },
            { label: '7 Días', value: '7d' },
            { label: '30 Días', value: '30d' },
            { label: 'Histórico', value: 'all' },
          ]"
          @update:model-value="onPresetChange"
        />

        <!-- Botón Exportar CSV -->
        <q-btn
          unelevated
          no-caps
          color="positive"
          text-color="dark"
          icon="sym_r_download"
          label="Exportar CSV"
          class="text-bold q-px-md"
          :loading="isExporting"
          @click="handleExportCsv"
        >
          <q-tooltip>Descargar informe de la pestaña activa en formato Excel / CSV</q-tooltip>
        </q-btn>
      </div>
    </div>

    <!-- Pestañas de Navegación Ejecutiva -->
    <div class="xf-subnav-tabs q-mb-lg">
      <button
        class="xf-subnav-btn"
        :class="{ 'xf-subnav-btn--active': activeTab === 'general' }"
        @click="activeTab = 'general'"
      >
        <q-icon name="sym_r_monitoring" size="18px" />
        <span>Rendimiento & SLA General</span>
      </button>

      <button
        class="xf-subnav-btn"
        :class="{ 'xf-subnav-btn--active': activeTab === 'agents' }"
        @click="activeTab = 'agents'"
      >
        <q-icon name="sym_r_group" size="18px" />
        <span>Desempeño de Asesores</span>
      </button>

      <button
        class="xf-subnav-btn"
        :class="{ 'xf-subnav-btn--active': activeTab === 'categories' }"
        @click="activeTab = 'categories'"
      >
        <q-icon name="sym_r_school" size="18px" />
        <span>Sedes & Oferta Académica</span>
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

    <!-- ======================================================== -->
    <!-- PESTAÑA 1: RENDIMIENTO & SLA GENERAL                     -->
    <!-- ======================================================== -->
    <div v-if="activeTab === 'general'">
      <!-- 1. KPIs de Volumen -->
      <div class="row q-col-gutter-md q-mb-md">
        <div class="col-12 col-sm-6 col-md">
          <q-card flat bordered class="xf-kpi-card">
            <div class="xf-kpi-card__title">CONVERSACIONES CREADAS</div>
            <div class="xf-kpi-card__number text-white">{{ generalKpis.chats_created }}</div>
            <div class="text-caption text-grey-5 q-mt-xs">Total del período</div>
          </q-card>
        </div>

        <div class="col-12 col-sm-6 col-md">
          <q-card flat bordered class="xf-kpi-card">
            <div class="xf-kpi-card__title">RESUELTAS / CERRADAS</div>
            <div class="xf-kpi-card__number text-teal-4">{{ generalKpis.chats_resolved }}</div>
            <div class="text-caption text-grey-5 q-mt-xs">{{ generalKpis.resolved_percentage }}% efectividad</div>
          </q-card>
        </div>

        <div class="col-12 col-sm-6 col-md">
          <q-card flat bordered class="xf-kpi-card">
            <div class="xf-kpi-card__title">EN ATENCIÓN ACTIVA</div>
            <div class="xf-kpi-card__number text-cyan-4">{{ generalKpis.in_attention }}</div>
            <div class="text-caption text-grey-5 q-mt-xs">En curso por operadores</div>
          </q-card>
        </div>

        <div class="col-12 col-sm-6 col-md">
          <q-card flat bordered class="xf-kpi-card">
            <div class="xf-kpi-card__title">CON RESPUESTA ENVIADA</div>
            <div class="xf-kpi-card__number text-white">{{ generalKpis.with_replies }}</div>
            <div class="text-caption text-grey-5 q-mt-xs">Interacción humana</div>
          </q-card>
        </div>

        <div class="col-12 col-sm-6 col-md">
          <q-card flat bordered class="xf-kpi-card xf-kpi-card--alert">
            <div class="xf-kpi-card__title">SIN RESPUESTA (SLA)</div>
            <div class="row items-center q-gutter-x-xs">
              <span class="xf-kpi-card__number text-negative">{{ generalKpis.unanswered }}</span>
              <span v-if="generalKpis.unanswered > 0" class="xf-red-pulse-dot"></span>
            </div>
            <div class="text-caption text-negative text-bold q-mt-xs">
              {{ generalKpis.unanswered > 0 ? 'Requiere atención urgente' : 'Al día' }}
            </div>
          </q-card>
        </div>
      </div>

      <!-- 2. Métricas de Tiempo de Servicio -->
      <q-card flat bordered class="xf-time-card q-mb-md q-pa-lg">
        <div class="row items-center justify-between q-mb-md">
          <div class="text-bold text-caption text-grey-4">VELOCIDAD Y TIEMPOS MEDIOS DE SERVICIO</div>
          <div class="text-caption text-teal-4 text-bold">Monitoreo continuo de primera respuesta</div>
        </div>

        <div class="row q-col-gutter-lg">
          <div class="col-6 col-md-3">
            <div class="text-caption text-grey-4">Tiempo 1ra Respuesta</div>
            <div class="text-h5 text-bold text-white q-mt-xs">{{ timeMetrics.first_response }}</div>
          </div>

          <div class="col-6 col-md-3">
            <div class="text-caption text-grey-4">Tiempo Medio Resolución</div>
            <div class="text-h5 text-bold text-teal-3 q-mt-xs">{{ timeMetrics.resolution }}</div>
          </div>

          <div class="col-6 col-md-3">
            <div class="text-caption text-grey-4">Tiempo en Chatbot / Menú</div>
            <div class="text-h5 text-bold text-white q-mt-xs">{{ timeMetrics.in_chatbot }}</div>
          </div>

          <div class="col-6 col-md-3">
            <div class="text-caption text-grey-4">Tiempo Asesor Humano</div>
            <div class="text-h5 text-bold text-cyan-3 q-mt-xs">{{ timeMetrics.human_attention }}</div>
          </div>
        </div>
      </q-card>

      <!-- 3. Evolución Horaria de Tráfico -->
      <q-card flat bordered class="xf-chart-card q-pa-md">
        <div class="row items-center justify-between q-mb-md">
          <div class="text-bold text-caption text-white">EVOLUCIÓN HORARIA DE CONSULTAS (HORAS PICO)</div>
          <div class="row items-center q-gutter-x-sm text-caption text-grey-4">
            <span class="row items-center q-gutter-x-xs"><span class="xf-legend-line bg-teal-4"></span> Mensajes y Chats</span>
          </div>
        </div>

        <div class="row q-col-gutter-sm items-end" style="height: 140px">
          <div
            v-for="h in hourlyEvolution"
            :key="h.hour"
            class="col text-center flex column justify-end"
            style="height: 100%"
          >
            <div
              class="hourly-bar q-mx-auto"
              :style="{
                height: `${Math.max(10, Math.min(100, h.chats * 15))}%`,
                backgroundColor: h.chats > 0 ? '#10b981' : 'rgba(255,255,255,0.05)',
              }"
            >
              <q-tooltip>{{ h.hour }}: {{ h.chats }} chats registrados</q-tooltip>
            </div>
            <span class="text-caption text-grey-5 q-mt-xs">{{ h.hour }}</span>
          </div>
        </div>
      </q-card>
    </div>

    <!-- ======================================================== -->
    <!-- PESTAÑA 2: DESEMPEÑO DE ASESORES & CUMPLIMIENTO DE SLA   -->
    <!-- ======================================================== -->
    <div v-else-if="activeTab === 'agents'">
      <!-- Tarjetas Resumen de SLA de Agentes -->
      <div class="row q-col-gutter-md q-mb-md">
        <div class="col-12 col-sm-6 col-md-3">
          <q-card flat bordered class="xf-kpi-card">
            <div class="xf-kpi-card__title">CHATS ASIGNADOS</div>
            <div class="xf-kpi-card__number text-white">{{ agentsSummary.total_assigned }}</div>
            <div class="text-caption text-grey-5 q-mt-xs">{{ agentsList.length }} asesores evaluados</div>
          </q-card>
        </div>

        <div class="col-12 col-sm-6 col-md-3">
          <q-card flat bordered class="xf-kpi-card">
            <div class="xf-kpi-card__title">A TIEMPO (SLA &le; {{ slaTimeoutMinutes }}m)</div>
            <div class="xf-kpi-card__number text-positive">{{ agentsSummary.total_on_time }}</div>
            <div class="text-caption text-positive text-bold q-mt-xs">
              {{ agentsSummary.sla_compliance_rate }}% cumplimiento
            </div>
          </q-card>
        </div>

        <div class="col-12 col-sm-6 col-md-3">
          <q-card flat bordered class="xf-kpi-card">
            <div class="xf-kpi-card__title">FUERA DE PLAZO (SLA TARDÍO)</div>
            <div class="xf-kpi-card__number text-warning">{{ agentsSummary.total_delayed }}</div>
            <div class="text-caption text-warning q-mt-xs">Demora mayor a {{ slaTimeoutMinutes }} min</div>
          </q-card>
        </div>

        <div class="col-12 col-sm-6 col-md-3">
          <q-card flat bordered class="xf-kpi-card xf-kpi-card--alert">
            <div class="xf-kpi-card__title">SIN RESPUESTA PENDIENTES</div>
            <div class="row items-center q-gutter-x-xs">
              <span class="xf-kpi-card__number text-negative">{{ agentsSummary.total_unanswered }}</span>
              <span v-if="agentsSummary.total_unanswered > 0" class="xf-red-pulse-dot"></span>
            </div>
            <div class="text-caption text-negative text-bold q-mt-xs">En espera en bandeja</div>
          </q-card>
        </div>
      </div>

      <!-- Tabla Comparativa de Asesores -->
      <q-card flat bordered class="xf-table-card">
        <div class="row items-center justify-between q-pa-md">
          <div class="row items-center q-gutter-x-sm">
            <q-icon name="sym_r_badge" size="20px" color="teal-4" />
            <span class="text-subtitle1 text-bold text-white">Métricas Individuales de Operadores</span>
          </div>

          <q-input
            v-model="agentSearch"
            dense
            outlined
            dark
            placeholder="Buscar asesor..."
            style="width: 240px"
          >
            <template #prepend>
              <q-icon name="sym_r_search" size="16px" color="grey-5" />
            </template>
          </q-input>
        </div>

        <q-markup-table flat dark class="xf-table">
          <thead>
            <tr>
              <th class="text-left">ASESOR / OPERADOR</th>
              <th class="text-center">ASIGNADOS</th>
              <th class="text-center">RESUELTOS</th>
              <th class="text-center">A TIEMPO</th>
              <th class="text-center">CON RETRASO</th>
              <th class="text-center">SIN RESPUESTA</th>
              <th class="text-left" style="min-width: 140px">% CUMPLIMIENTO</th>
              <th class="text-center">1RA RESPUESTA</th>
              <th class="text-center">RESOLUCIÓN</th>
              <th class="text-center">CSAT</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="filteredAgents.length === 0">
              <td colspan="10" class="text-center q-pa-lg text-grey-5">
                No se registraron atenciones de asesores en el período seleccionado.
              </td>
            </tr>
            <tr v-for="agent in filteredAgents" :key="agent.user_id" class="xf-table-row">
              <!-- Asesor -->
              <td class="text-left">
                <div class="row items-center q-gutter-x-sm">
                  <q-avatar size="32px" color="teal-9" text-color="teal-2" class="text-bold text-caption">
                    {{ agent.name.charAt(0).toUpperCase() }}
                  </q-avatar>
                  <div>
                    <div class="text-bold text-white">{{ agent.name }}</div>
                    <div class="text-caption text-grey-5">{{ agent.email }}</div>
                  </div>
                </div>
              </td>

              <!-- Asignados -->
              <td class="text-center text-bold text-white font-mono">
                {{ agent.assigned_count }}
              </td>

              <!-- Resueltos -->
              <td class="text-center">
                <span class="text-teal-3 font-mono text-bold">{{ agent.resolved_count }}</span>
              </td>

              <!-- A Tiempo -->
              <td class="text-center">
                <q-badge color="positive" text-color="dark" class="text-bold font-mono">
                  {{ agent.sla_on_time }}
                </q-badge>
              </td>

              <!-- Con Retraso -->
              <td class="text-center">
                <q-badge v-if="agent.sla_delayed > 0" color="warning" text-color="dark" class="text-bold font-mono">
                  {{ agent.sla_delayed }}
                </q-badge>
                <span v-else class="text-grey-6">-</span>
              </td>

              <!-- Sin Respuesta -->
              <td class="text-center">
                <q-badge v-if="agent.unanswered > 0" color="negative" class="text-bold font-mono">
                  {{ agent.unanswered }}
                </q-badge>
                <span v-else class="text-grey-6">-</span>
              </td>

              <!-- % Cumplimiento SLA -->
              <td class="text-left">
                <div class="row items-center q-gutter-x-xs">
                  <q-linear-progress
                    :value="agent.sla_compliance_rate / 100"
                    rounded
                    size="6px"
                    :color="getComplianceColor(agent.sla_compliance_rate)"
                    track-color="dark"
                    class="col"
                  />
                  <span class="text-caption text-bold font-mono" :class="`text-${getComplianceColor(agent.sla_compliance_rate)}`">
                    {{ agent.sla_compliance_rate }}%
                  </span>
                </div>
              </td>

              <!-- Tiempo 1ra Respuesta -->
              <td class="text-center font-mono text-caption text-grey-3">
                {{ agent.avg_first_response_time }}
              </td>

              <!-- Tiempo Resolución -->
              <td class="text-center font-mono text-caption text-grey-3">
                {{ agent.avg_resolution_time }}
              </td>

              <!-- CSAT -->
              <td class="text-center">
                <div class="row items-center justify-center q-gutter-x-xs text-amber-4 text-bold">
                  <q-icon name="sym_r_star" size="14px" />
                  <span>{{ agent.avg_rating }}</span>
                </div>
              </td>
            </tr>
          </tbody>
        </q-markup-table>
      </q-card>
    </div>

    <!-- ======================================================== -->
    <!-- PESTAÑA 3: SEDES & OFERTA ACADÉMICA (CATEGORÍAS)         -->
    <!-- ======================================================== -->
    <div v-else-if="activeTab === 'categories'">
      <!-- Tarjetas Resumen Académico -->
      <div class="row q-col-gutter-md q-mb-md">
        <div class="col-12 col-sm-6 col-md-3">
          <q-card flat bordered class="xf-kpi-card">
            <div class="xf-kpi-card__title">CONTACTOS CATEGORIZADOS</div>
            <div class="xf-kpi-card__number text-white">{{ categoriesSummary.total_categorized_contacts }}</div>
            <div class="text-caption text-grey-5 q-mt-xs">Con sede o carrera asignada</div>
          </q-card>
        </div>

        <div class="col-12 col-sm-6 col-md-3">
          <q-card flat bordered class="xf-kpi-card">
            <div class="xf-kpi-card__title">CONVERSACIONES REGISTRADAS</div>
            <div class="xf-kpi-card__number text-cyan-4">{{ categoriesSummary.total_categorized_conversations }}</div>
            <div class="text-caption text-grey-5 q-mt-xs">Consultas académicas</div>
          </q-card>
        </div>

        <div class="col-12 col-sm-6 col-md-3">
          <q-card flat bordered class="xf-kpi-card">
            <div class="xf-kpi-card__title">SEDE CON MAYOR DEMANDA</div>
            <div class="text-h6 text-bold text-teal-3 ellipsis q-mt-xs">{{ categoriesSummary.top_campus }}</div>
            <div class="text-caption text-grey-5">Líder en volumen nacional</div>
          </q-card>
        </div>

        <div class="col-12 col-sm-6 col-md-3">
          <q-card flat bordered class="xf-kpi-card">
            <div class="xf-kpi-card__title">CARRERA MÁS CONSULTADA</div>
            <div class="text-h6 text-bold text-amber-3 ellipsis q-mt-xs">{{ categoriesSummary.top_career }}</div>
            <div class="text-caption text-grey-5">Mayor atracción de prospectos</div>
          </q-card>
        </div>
      </div>

      <!-- Ranking Nacional de Carreras Más Demandadas -->
      <q-card flat bordered class="xf-table-card q-mb-lg q-pa-md">
        <div class="row items-center justify-between q-mb-md">
          <div class="row items-center q-gutter-x-sm">
            <q-icon name="sym_r_trending_up" size="20px" color="amber-4" />
            <span class="text-subtitle1 text-bold text-white">Top Carreras con Mayor Atracción de Prospectos</span>
          </div>
          <span class="text-caption text-grey-5">Consolidado general</span>
        </div>

        <div class="row q-col-gutter-md">
          <div v-for="(career, idx) in topCareers" :key="career.name" class="col-12 col-sm-6 col-md-3">
            <div class="career-rank-pill q-pa-sm">
              <div class="row items-center justify-between no-wrap">
                <div class="row items-center q-gutter-x-xs ellipsis">
                  <span class="rank-badge">{{ idx + 1 }}</span>
                  <span class="text-body2 text-bold text-white ellipsis">{{ career.name }}</span>
                </div>
                <q-badge
                  v-if="career.code"
                  dense
                  :style="{ backgroundColor: career.color + '22', color: career.color, border: '1px solid ' + career.color }"
                  class="font-mono q-ml-xs"
                >
                  {{ career.code }}
                </q-badge>
              </div>

              <div class="row items-center justify-between q-mt-xs text-caption">
                <span class="text-grey-4">{{ career.contacts_count }} prospectos</span>
                <span class="text-teal-4 text-bold">{{ career.percentage }}%</span>
              </div>
              <q-linear-progress
                :value="career.percentage / 100"
                rounded
                size="4px"
                color="teal-4"
                track-color="dark"
                class="q-mt-xs"
              />
            </div>
          </div>
        </div>
      </q-card>

      <!-- Desglose por Sedes y Carreras -->
      <div class="row q-col-gutter-md">
        <div v-for="campus in campusList" :key="campus.id" class="col-12 col-lg-6">
          <q-card flat bordered class="campus-card q-pa-md">
            <!-- Header Sede -->
            <div class="row items-center justify-between q-mb-sm">
              <div class="row items-center q-gutter-x-sm">
                <q-avatar size="36px" :style="{ backgroundColor: campus.color + '22', border: '1px solid ' + campus.color }">
                  <q-icon :name="campus.icon || 'sym_r_location_city'" size="20px" :style="{ color: campus.color }" />
                </q-avatar>
                <div>
                  <div class="text-subtitle1 text-bold text-white">{{ campus.name }}</div>
                  <div class="text-caption text-grey-4">
                    {{ campus.contacts_count }} prospectos • {{ campus.conversations_count }} chats
                  </div>
                </div>
              </div>

              <div class="text-right">
                <q-badge color="positive" text-color="dark" class="text-bold q-px-sm">
                  {{ campus.conversion_rate }}% conversión
                </q-badge>
                <div class="text-caption text-grey-5 q-mt-xs">
                  {{ campus.won_leads }} matriculados
                </div>
              </div>
            </div>

            <q-separator dark class="q-my-sm" />

            <!-- Carreras de esta Sede -->
            <div v-if="campus.subcategories.length > 0" class="subcategories-table-wrap">
              <q-markup-table dense flat dark class="xf-subtable">
                <thead>
                  <tr>
                    <th class="text-left">CARRERA / PROGRAMA</th>
                    <th class="text-center">PROSPECTOS</th>
                    <th class="text-center">CONVERSACIONES</th>
                    <th class="text-right">MATRICULADOS</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="sub in campus.subcategories" :key="sub.id">
                    <td class="text-left">
                      <div class="row items-center q-gutter-x-xs">
                        <span class="subcat-dot" :style="{ backgroundColor: sub.color }"></span>
                        <span class="text-white text-body2">{{ sub.name }}</span>
                        <span v-if="sub.code" class="text-caption text-grey-5 font-mono">[{{ sub.code }}]</span>
                      </div>
                    </td>
                    <td class="text-center font-mono text-bold text-white">{{ sub.contacts_count }}</td>
                    <td class="text-center font-mono text-grey-4">{{ sub.conversations_count }}</td>
                    <td class="text-right">
                      <span class="text-positive text-bold font-mono">{{ sub.won_leads }}</span>
                    </td>
                  </tr>
                </tbody>
              </q-markup-table>
            </div>
            <div v-else class="text-caption text-grey-6 italic text-center q-pa-md">
              Sin subcategorías o carreras asignadas aún a esta sede.
            </div>
          </q-card>
        </div>
      </div>
    </div>

    <!-- ======================================================== -->
    <!-- PESTAÑA 4: SATISFACCIÓN CSAT                             -->
    <!-- ======================================================== -->
    <div v-else-if="activeTab === 'csat'" class="q-gutter-y-md" style="max-width: 750px">
      <q-card flat bordered class="xf-chart-card q-pa-xl text-center">
        <div class="text-h2 text-bold text-amber-4">
          {{ csatData.average_score }} <span class="text-h5 text-grey-4">/ 5.0</span>
        </div>
        <div class="text-h6 text-white q-mt-sm">
          {{ csatData.satisfaction_percentage }}% Índice de Satisfacción del Estudiante
        </div>
        <div class="text-caption text-grey-4 q-mt-xs">
          Basado en {{ csatData.total_responses }} encuestas de satisfacción completadas al cierre de ticket.
        </div>

        <div class="row justify-center q-gutter-x-xs q-mt-md">
          <q-icon
            v-for="star in 5"
            :key="star"
            name="sym_r_star"
            size="28px"
            :color="star <= Math.round(csatData.average_score) ? 'amber-4' : 'grey-7'"
          />
        </div>
      </q-card>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import {
  getAgentsReport,
  getAnalyticsReport,
  getCategoriesReport,
  getCsatReport,
  getExportUrl,
} from '../api/reports.api'
import { useAppNotify } from '@/shared/composables/useAppNotify'
import type {
  AgentReportItem,
  AgentReportSummary,
  CategoryCampusReportItem,
  CategoryReportSummary,
  CsatReportData,
  TopCareerReportItem,
} from '../types/report.types'

const notify = useAppNotify()

const activeTab = ref<'general' | 'agents' | 'categories' | 'csat'>('general')
const selectedPreset = ref<'today' | '7d' | '30d' | 'all'>('30d')
const isExporting = ref(false)
const agentSearch = ref('')

// Fechas activas
const dateFilters = reactive({
  start_date: '' as string | undefined,
  end_date: '' as string | undefined,
})

// KPIs Generales
const generalKpis = reactive({
  chats_created: 0,
  chats_resolved: 0,
  resolved_percentage: 0.0,
  in_attention: 0,
  with_replies: 0,
  unanswered: 0,
})

const timeMetrics = reactive({
  first_response: '0s',
  resolution: '0s',
  in_chatbot: '1s',
  human_attention: '0s',
})

const hourlyEvolution = ref<Array<{ hour: string; chats: number; new_contacts: number }>>([])

// Reporte de Agentes
const slaTimeoutMinutes = ref(10)
const agentsList = ref<AgentReportItem[]>([])
const agentsSummary = reactive<AgentReportSummary>({
  total_assigned: 0,
  total_resolved: 0,
  total_on_time: 0,
  total_delayed: 0,
  total_unanswered: 0,
  sla_compliance_rate: 100.0,
  avg_first_response_time: '0s',
  avg_resolution_time: '0s',
})

// Reporte de Categorías (Sedes / Carreras)
const campusList = ref<CategoryCampusReportItem[]>([])
const topCareers = ref<TopCareerReportItem[]>([])
const categoriesSummary = reactive<CategoryReportSummary>({
  total_categorized_contacts: 0,
  total_uncategorized_contacts: 0,
  total_categorized_conversations: 0,
  top_campus: 'N/A',
  top_career: 'N/A',
})

// Reporte CSAT
const csatData = reactive<CsatReportData>({
  average_score: 5.0,
  satisfaction_percentage: 100.0,
  total_responses: 0,
  stars_breakdown: {
    '5_stars': 0,
    '4_stars': 0,
    '3_stars': 0,
    '2_stars': 0,
    '1_star': 0,
  },
})

// Filtrado de agentes por texto de búsqueda
const filteredAgents = computed(() => {
  const needle = agentSearch.value.toLowerCase().trim()
  if (!needle) return agentsList.value
  return agentsList.value.filter(
    (a) => a.name.toLowerCase().includes(needle) || a.email.toLowerCase().includes(needle),
  )
})

function getComplianceColor(rate: number): string {
  if (rate >= 80) return 'positive'
  if (rate >= 60) return 'warning'
  return 'negative'
}

function computePresetDates(preset: 'today' | '7d' | '30d' | 'all') {
  const now = new Date()
  const formatDate = (d: Date) => d.toISOString().split('T')[0]

  if (preset === 'today') {
    dateFilters.start_date = formatDate(now)
    dateFilters.end_date = formatDate(now)
  } else if (preset === '7d') {
    const past = new Date(now.getTime() - 7 * 24 * 60 * 60 * 1000)
    dateFilters.start_date = formatDate(past)
    dateFilters.end_date = formatDate(now)
  } else if (preset === '30d') {
    const past = new Date(now.getTime() - 30 * 24 * 60 * 60 * 1000)
    dateFilters.start_date = formatDate(past)
    dateFilters.end_date = formatDate(now)
  } else {
    dateFilters.start_date = undefined
    dateFilters.end_date = undefined
  }
}

async function loadAllReports() {
  const params = {
    start_date: dateFilters.start_date,
    end_date: dateFilters.end_date,
  }

  try {
    const [analyticsRes, agentsRes, categoriesRes, csatRes] = await Promise.all([
      getAnalyticsReport(params),
      getAgentsReport(params),
      getCategoriesReport(params),
      getCsatReport(params),
    ])

    if (analyticsRes) {
      Object.assign(generalKpis, analyticsRes.kpis)
      Object.assign(timeMetrics, analyticsRes.time_metrics)
      hourlyEvolution.value = analyticsRes.hourly_evolution || []
    }

    if (agentsRes) {
      slaTimeoutMinutes.value = agentsRes.sla_config?.timeout_minutes ?? 10
      Object.assign(agentsSummary, agentsRes.summary)
      agentsList.value = agentsRes.agents || []
    }

    if (categoriesRes) {
      Object.assign(categoriesSummary, categoriesRes.summary)
      campusList.value = categoriesRes.campuses || []
      topCareers.value = categoriesRes.top_careers || []
    }

    if (csatRes) {
      Object.assign(csatData, csatRes)
    }
  } catch (err) {
    console.error('Error cargando reportes:', err)
  }
}

function onPresetChange(val: 'today' | '7d' | '30d' | 'all') {
  computePresetDates(val)
  loadAllReports()
}

function handleExportCsv() {
  const typeMap: Record<string, 'general' | 'agents' | 'categories' | 'analytics'> = {
    general: 'analytics',
    agents: 'agents',
    categories: 'categories',
    csat: 'analytics',
  }

  const exportType = (typeMap[activeTab.value] || 'analytics') as 'agents' | 'categories' | 'analytics'
  const url = getExportUrl(exportType, {
    start_date: dateFilters.start_date,
    end_date: dateFilters.end_date,
  })

  // Trigger download link
  const link = document.createElement('a')
  link.href = url
  link.setAttribute('download', '')
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)

  notify.success({
    message: `Descargando reporte de ${activeTab.value} en formato CSV...`,
  })
}

onMounted(() => {
  computePresetDates('30d')
  loadAllReports()
})
</script>

<style scoped lang="scss">
.xf-reports-page {
  padding: 24px 32px;
  background-color: var(--crm-bg-app, #080c14);
  min-height: calc(100vh - 56px);
}

.xf-subnav-tabs {
  display: flex;
  align-items: center;
  gap: 8px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
  padding-bottom: 8px;
}

.xf-subnav-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px;
  border-radius: 8px;
  background: transparent;
  border: 1px solid transparent;
  color: #94a3b8;
  font-size: 0.88rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;

  &:hover {
    color: #ffffff;
    background: rgba(255, 255, 255, 0.04);
  }

  &--active {
    background: rgba(16, 185, 129, 0.15) !important;
    border-color: rgba(16, 185, 129, 0.3) !important;
    color: #34d399 !important;
  }
}

.xf-kpi-card {
  background: var(--crm-bg-card, #111827);
  border-radius: 12px;
  padding: 16px;
  border: 1px solid rgba(255, 255, 255, 0.07);

  &__title {
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.05em;
    color: #94a3b8;
  }

  &__number {
    font-size: 1.8rem;
    font-weight: 800;
    line-height: 1.1;
    margin-top: 6px;
    font-family: monospace;
  }

  &--alert {
    border-color: rgba(239, 68, 68, 0.35);
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.05) 0%, #111827 100%);
  }
}

.xf-time-card,
.xf-chart-card,
.xf-table-card,
.campus-card {
  background: var(--crm-bg-card, #111827);
  border-radius: 14px;
  border: 1px solid rgba(255, 255, 255, 0.07);
}

.xf-table {
  background: transparent;

  thead tr th {
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.05em;
    color: #94a3b8;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    padding: 12px 14px;
  }

  tbody tr {
    border-bottom: 1px solid rgba(255, 255, 255, 0.04);
    transition: background 0.15s ease;

    &:hover {
      background: rgba(255, 255, 255, 0.03);
    }
  }

  tbody td {
    padding: 10px 14px;
  }
}

.hourly-bar {
  width: 14px;
  border-radius: 4px 4px 0 0;
  transition: all 0.3s ease;
}

.career-rank-pill {
  background: rgba(255, 255, 255, 0.03);
  border: 1px solid rgba(255, 255, 255, 0.06);
  border-radius: 8px;
}

.rank-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 18px;
  height: 18px;
  border-radius: 50%;
  background: rgba(251, 191, 36, 0.2);
  color: #fbbf24;
  font-size: 0.7rem;
  font-weight: 800;
}

.subcat-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  display: inline-block;
}

.xf-subtable {
  background: transparent;

  thead tr th {
    font-size: 0.7rem;
    color: #64748b;
    padding: 6px 8px;
  }

  tbody td {
    padding: 6px 8px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.02);
  }
}

.xf-red-pulse-dot {
  width: 10px;
  height: 10px;
  background-color: #ef4444;
  border-radius: 50%;
  display: inline-block;
  box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7);
  animation: pulse-red 1.8s infinite;
}

@keyframes pulse-red {
  0% {
    box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7);
  }
  70% {
    box-shadow: 0 0 0 8px rgba(239, 68, 68, 0);
  }
  100% {
    box-shadow: 0 0 0 0 rgba(239, 68, 68, 0);
  }
}
</style>
