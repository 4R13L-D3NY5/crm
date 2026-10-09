<template>
  <div class="time-alert-rules-tab q-gutter-y-lg" style="max-width: 1100px">
    <!-- Selector de Sub-sección: Reglas de Inactividad vs Horario Laboral -->
    <div class="row items-center q-gutter-sm q-mb-md">
      <q-btn
        :unelevated="subTab === 'inactivity'"
        :outline="subTab !== 'inactivity'"
        :color="subTab === 'inactivity' ? 'primary' : 'grey-5'"
        icon="sym_r_rule"
        label="Reglas de Inactividad (SLA)"
        no-caps
        class="xf-subtab-btn q-px-md"
        @click="subTab = 'inactivity'"
      />

      <q-btn
        :unelevated="subTab === 'business_hours'"
        :outline="subTab !== 'business_hours'"
        :color="subTab === 'business_hours' ? 'primary' : 'grey-5'"
        icon="sym_r_schedule"
        label="Horario Laboral & Auto-respuesta"
        no-caps
        class="xf-subtab-btn q-px-md"
        @click="subTab = 'business_hours'"
      >
        <q-badge color="amber-9" text-color="amber-2" class="q-ml-xs text-bold">
          Configurable
        </q-badge>
      </q-btn>
    </div>

    <!-- SECCIÓN 1: REGLAS DE INACTIVIDAD (SLA) -->
    <div v-if="subTab === 'inactivity'" class="q-gutter-y-lg">
      <!-- Banner Explicativo -->
      <q-banner rounded class="xf-time-banner q-pa-md">
        <template #avatar>
          <q-icon name="sym_r_timer" color="amber-4" size="28px" />
        </template>
        <div class="text-subtitle2 text-bold text-white">
          ⏱️ Reglas de Tiempo & Alertas de Inactividad (SLA Omnicanal)
        </div>
        <div class="text-caption text-grey-4 q-mt-xs">
          Configura reglas automáticas de detección de inactividad. Establece alertas cuando un asesor tarda en responder a un cliente o cuando un cliente no responde tras el último mensaje, segmentado por los estados del lead.
        </div>
      </q-banner>

    <!-- KPI Summary Cards -->
    <div class="row q-col-gutter-md">
      <div class="col-12 col-sm-6 col-md-3">
        <q-card flat bordered class="xf-kpi-card">
          <div class="row items-center justify-between no-wrap">
            <div>
              <div class="text-caption text-grey-4 text-bold">REGLAS ACTIVAS</div>
              <div class="text-h5 text-bold text-white q-mt-xs">
                {{ activeRulesCount }} <span class="text-caption text-grey-5 font-normal">/ {{ rules.length }}</span>
              </div>
            </div>
            <q-avatar size="38px" color="teal-9" text-color="teal-2">
              <q-icon name="sym_r_rule" size="22px" />
            </q-avatar>
          </div>
          <div class="text-caption text-teal-3 q-mt-xs">
            Monitoreo en tiempo real
          </div>
        </q-card>
      </div>

      <div class="col-12 col-sm-6 col-md-3">
        <q-card flat bordered class="xf-kpi-card">
          <div class="row items-center justify-between no-wrap">
            <div>
              <div class="text-caption text-grey-4 text-bold">ESPERA ASESOR (BASE)</div>
              <div class="text-h5 text-bold text-amber-3 q-mt-xs">
                {{ defaultUserTimeout }} min
              </div>
            </div>
            <q-avatar size="38px" color="amber-9" text-color="amber-2">
              <q-icon name="sym_r_support_agent" size="22px" />
            </q-avatar>
          </div>
          <div class="text-caption text-grey-4 q-mt-xs">
            Alerta si asesor no responde
          </div>
        </q-card>
      </div>

      <div class="col-12 col-sm-6 col-md-3">
        <q-card flat bordered class="xf-kpi-card">
          <div class="row items-center justify-between no-wrap">
            <div>
              <div class="text-caption text-grey-4 text-bold">ESPERA CLIENTE (BASE)</div>
              <div class="text-h5 text-bold text-cyan-3 q-mt-xs">
                {{ defaultClientTimeout }} min
              </div>
            </div>
            <q-avatar size="38px" color="cyan-9" text-color="cyan-2">
              <q-icon name="sym_r_person" size="22px" />
            </q-avatar>
          </div>
          <div class="text-caption text-grey-4 q-mt-xs">
            {{ formatMinutes(defaultClientTimeout) }} máx para el cliente
          </div>
        </q-card>
      </div>

      <div class="col-12 col-sm-6 col-md-3">
        <q-card flat bordered class="xf-kpi-card">
          <div class="row items-center justify-between no-wrap">
            <div>
              <div class="text-caption text-grey-4 text-bold">ESTADOS CUBIERTOS</div>
              <div class="text-h5 text-bold text-white q-mt-xs">
                {{ coveredStatusesSummary }}
              </div>
            </div>
            <q-avatar size="38px" color="purple-9" text-color="purple-2">
              <q-icon name="sym_r_checklist" size="22px" />
            </q-avatar>
          </div>
          <div class="text-caption text-grey-4 q-mt-xs">
            Etapas del embudo protegidas
          </div>
        </q-card>
      </div>
    </div>

    <!-- Barra de Acciones de Cabecera -->
    <div class="row items-center justify-between q-col-gutter-sm">
      <div class="col-12 col-sm-6">
        <div class="text-subtitle1 text-bold text-white">Directorio de Reglas de Inactividad</div>
        <div class="text-caption text-grey-4">
          Personaliza los tiempos de espera y las condiciones de alerta por etapa comercial.
        </div>
      </div>

      <div class="col-12 col-sm-6 row justify-end items-center q-gutter-sm">
        <q-btn
          outline
          dense
          color="amber-4"
          icon="sym_r_restore"
          label="Restablecer Regla Base"
          no-caps
          class="q-px-sm"
          @click="seedDefaultRule"
        >
          <q-tooltip>Genera una regla recomendada con 20m para usuario y 120m para cliente aplicable a todos los estados</q-tooltip>
        </q-btn>

        <q-btn
          unelevated
          color="primary"
          icon="sym_r_add"
          label="+ Nueva Regla de Tiempo"
          no-caps
          class="xf-btn-primary q-px-md"
          @click="openCreateDialog"
        />
      </div>
    </div>

    <!-- Spinner de Carga -->
    <div v-if="loading" class="text-center q-pa-xl text-grey-4">
      <q-spinner-dots size="36px" color="primary" />
      <div class="q-mt-sm">Cargando reglas de tiempo...</div>
    </div>

    <!-- Estado Vacío -->
    <q-card v-else-if="rules.length === 0" flat bordered class="xf-empty-card q-pa-xl text-center">
      <q-icon name="sym_r_schedule" size="56px" color="amber-4" class="q-mb-md" />
      <div class="text-h6 text-white text-bold">No hay reglas de inactividad configuradas</div>
      <p class="text-caption text-grey-4 q-mt-sm" style="max-width: 520px; margin: 8px auto 20px">
        Puedes crear una regla personalizada o generar automáticamente la regla estándar por defecto (Usuario: 20 min, Cliente: 120 min, aplicable a todos los estados).
      </p>
      <q-btn
        unelevated
        color="primary"
        icon="sym_r_auto_fix_high"
        label="Generar Regla Estándar Automática"
        no-caps
        class="xf-btn-primary q-px-lg"
        @click="seedDefaultRule"
      />
    </q-card>

    <!-- Lista de Reglas Configuradas -->
    <div v-else class="row q-col-gutter-md">
      <div v-for="rule in rules" :key="rule.id" class="col-12">
        <q-card flat bordered class="xf-rule-card q-pa-md" :class="{ 'xf-rule-card--inactive': !rule.is_active }">
          <div class="row items-start justify-between no-wrap q-mb-sm">
            <div class="row items-center q-gutter-x-sm">
              <q-avatar size="36px" :color="getSeverityColor(rule.severity) + '-9'" :text-color="getSeverityColor(rule.severity) + '-2'">
                <q-icon :name="getSeverityIcon(rule.severity)" size="20px" />
              </q-avatar>
              <div>
                <div class="row items-center q-gutter-x-sm">
                  <span class="text-subtitle1 text-bold text-white">{{ rule.name }}</span>
                  <q-badge :color="getSeverityBadgeColor(rule.severity)" class="text-caption text-bold">
                    {{ formatSeverity(rule.severity) }}
                  </q-badge>
                  <q-badge v-if="!rule.is_active" color="grey-8" text-color="grey-4" class="text-caption">
                    Pausada
                  </q-badge>
                </div>
                <div class="text-caption text-grey-4 q-mt-xs">
                  {{ rule.description || 'Sin descripción adicional.' }}
                </div>
              </div>
            </div>

            <!-- Switch de Activación Rápida & Acciones -->
            <div class="row items-center q-gutter-x-sm">
              <div class="row items-center q-gutter-x-xs q-mr-sm">
                <span class="text-caption" :class="rule.is_active ? 'text-teal-3' : 'text-grey-5'">
                  {{ rule.is_active ? 'Activa' : 'Inactiva' }}
                </span>
                <q-toggle
                  :model-value="rule.is_active"
                  dense
                  color="teal-4"
                  @update:model-value="toggleRuleActive(rule)"
                />
              </div>

              <q-btn
                flat
                round
                dense
                size="sm"
                icon="sym_r_edit"
                color="cyan-4"
                @click="openEditDialog(rule)"
              >
                <q-tooltip>Editar Regla</q-tooltip>
              </q-btn>

              <q-btn
                flat
                round
                dense
                size="sm"
                icon="sym_r_delete"
                color="negative"
                @click="confirmDelete(rule)"
              >
                <q-tooltip>Eliminar Regla</q-tooltip>
              </q-btn>
            </div>
          </div>

          <q-separator dark class="q-my-sm" />

          <!-- Parámetros de Tiempo y Estados Aplicables -->
          <div class="row q-col-gutter-sm items-center q-mt-xs">
            <!-- 1. Tiempo de No Respuesta del Asesor / Usuario -->
            <div class="col-12 col-md-4">
              <div class="xf-param-pill">
                <div class="row items-center q-gutter-x-xs text-caption text-grey-4">
                  <q-icon name="sym_r_support_agent" size="16px" color="amber-4" />
                  <span class="text-bold">Sin respuesta del Asesor:</span>
                </div>
                <div v-if="rule.notify_user_inactivity" class="row items-center q-gutter-x-xs q-mt-xs">
                  <q-badge color="amber-9" text-color="amber-2" class="text-caption text-bold font-mono">
                    {{ rule.user_timeout_minutes }} min
                  </q-badge>
                  <span class="text-caption text-grey-4">
                    ({{ formatMinutes(rule.user_timeout_minutes) }})
                  </span>
                </div>
                <div v-else class="text-caption text-grey-6 italic q-mt-xs">
                  Alerta de asesor desactivada
                </div>
              </div>
            </div>

            <!-- 2. Tiempo de No Respuesta del Cliente / Postulante -->
            <div class="col-12 col-md-4">
              <div class="xf-param-pill">
                <div class="row items-center q-gutter-x-xs text-caption text-grey-4">
                  <q-icon name="sym_r_person" size="16px" color="cyan-4" />
                  <span class="text-bold">Sin respuesta del Cliente:</span>
                </div>
                <div v-if="rule.notify_client_inactivity" class="row items-center q-gutter-x-xs q-mt-xs">
                  <q-badge color="cyan-9" text-color="cyan-2" class="text-caption text-bold font-mono">
                    {{ rule.client_timeout_minutes }} min
                  </q-badge>
                  <span class="text-caption text-grey-4">
                    ({{ formatMinutes(rule.client_timeout_minutes) }})
                  </span>
                </div>
                <div v-else class="text-caption text-grey-6 italic q-mt-xs">
                  Alerta de cliente desactivada
                </div>
              </div>
            </div>

            <!-- 3. Estados a los que Aplica -->
            <div class="col-12 col-md-4">
              <div class="xf-param-pill">
                <div class="row items-center q-gutter-x-xs text-caption text-grey-4">
                  <q-icon name="sym_r_flag" size="16px" color="teal-4" />
                  <span class="text-bold">Estados aplicables:</span>
                </div>
                <div v-if="rule.apply_to_all_statuses" class="q-mt-xs">
                  <q-badge color="teal-9" text-color="teal-2" class="text-caption text-weight-medium">
                    ✓ Aplica a TODOS los estados (Global)
                  </q-badge>
                </div>
                <div v-else-if="rule.assigned_statuses && rule.assigned_statuses.length > 0" class="row q-gutter-xs items-center q-mt-xs">
                  <q-badge
                    v-for="st in rule.assigned_statuses"
                    :key="st.id"
                    :style="{
                      backgroundColor: (st.color || '#10b981') + '22',
                      color: st.color || '#10b981',
                      border: '1px solid ' + (st.color || '#10b981'),
                    }"
                    class="text-caption q-px-xs"
                  >
                    {{ st.name }}
                  </q-badge>
                </div>
                <div v-else class="text-caption text-grey-6 italic q-mt-xs">
                  Sin estados vinculados (inactiva)
                </div>
              </div>
            </div>
          </div>
        </q-card>
      </div>
    </div>
    </div>

    <!-- SECCIÓN 2: HORARIO LABORAL & AUTO-RESPUESTA -->
    <BusinessHoursSection v-else-if="subTab === 'business_hours'" />

    <!-- DIÁLOGO: CREAR / EDITAR REGLA DE TIEMPO -->
    <q-dialog v-model="isDialogOpen" persistent>
      <q-card class="xf-modal-card" style="min-width: 540px; max-width: 660px">
        <q-card-section>
          <div class="row items-center q-gutter-x-sm">
            <q-icon name="sym_r_timer" color="amber-4" size="24px" />
            <div class="text-h6 text-white text-bold">
              {{ selectedRule ? 'Editar Regla de Inactividad' : 'Nueva Regla de Inactividad' }}
            </div>
          </div>
          <div class="text-caption text-grey-4 q-mt-xs">
            Parametriza las alertas de tiempo de no respuesta para asesores y clientes de acuerdo a los estados.
          </div>
        </q-card-section>

        <q-card-section class="q-gutter-y-md">
          <!-- Nombre y Descripción -->
          <div class="row q-col-gutter-sm">
            <div class="col-12 col-md-8">
              <q-input
                v-model="form.name"
                label="Nombre de la Regla *"
                outlined
                dark
                dense
                placeholder="ej: Alerta General de Respuesta, Seguimiento Caliente..."
                :rules="[val => !!val || 'El nombre es obligatorio']"
              />
            </div>
            <div class="col-12 col-md-4">
              <q-select
                v-model="form.severity"
                :options="severityOptions"
                emit-value
                map-options
                label="Severidad"
                outlined
                dark
                dense
              />
            </div>
          </div>

          <q-input
            v-model="form.description"
            label="Descripción o Propósito (Opcional)"
            type="textarea"
            rows="2"
            outlined
            dark
            dense
            placeholder="Explica cuándo y por qué se dispara esta regla..."
          />

          <!-- SECCIÓN 1: FALTA DE RESPUESTA DEL ASESOR / USUARIO -->
          <div class="xf-section-box">
            <div class="row items-center justify-between">
              <div class="row items-center q-gutter-x-xs">
                <q-icon name="sym_r_support_agent" size="18px" color="amber-4" />
                <span class="text-subtitle2 text-bold text-white">Alerta de Inactividad del Asesor / Usuario</span>
              </div>
              <q-toggle
                v-model="form.notify_user_inactivity"
                dense
                color="amber-4"
                label="Habilitar"
                class="text-caption"
              />
            </div>
            <p class="text-caption text-grey-5 q-mt-xs q-mb-sm">
              Se dispara cuando el cliente envió el último mensaje y el asesor no ha contestado dentro del tiempo establecido.
            </p>

            <div v-if="form.notify_user_inactivity" class="row items-center q-gutter-sm">
              <q-input
                v-model.number="form.user_timeout_minutes"
                label="Tiempo Máx. Espera (Minutos) *"
                type="number"
                outlined
                dark
                dense
                style="width: 170px"
                min="1"
                max="10080"
              >
                <template #append>
                  <span class="text-caption text-grey-5">min</span>
                </template>
              </q-input>

              <!-- Presets rápidos -->
              <div class="row items-center q-gutter-xs">
                <q-chip
                  v-for="preset in [5, 10, 15, 20, 30, 60]"
                  :key="preset"
                  clickable
                  dense
                  dark
                  size="sm"
                  :color="form.user_timeout_minutes === preset ? 'amber-9' : 'grey-9'"
                  :text-color="form.user_timeout_minutes === preset ? 'amber-2' : 'grey-4'"
                  @click="form.user_timeout_minutes = preset"
                >
                  {{ preset }}m {{ preset === 20 ? '(Default)' : '' }}
                </q-chip>
              </div>
            </div>
          </div>

          <!-- SECCIÓN 2: FALTA DE RESPUESTA DEL CLIENTE -->
          <div class="xf-section-box">
            <div class="row items-center justify-between">
              <div class="row items-center q-gutter-x-xs">
                <q-icon name="sym_r_person" size="18px" color="cyan-4" />
                <span class="text-subtitle2 text-bold text-white">Alerta de Inactividad del Cliente / Postulante</span>
              </div>
              <q-toggle
                v-model="form.notify_client_inactivity"
                dense
                color="cyan-4"
                label="Habilitar"
                class="text-caption"
              />
            </div>
            <p class="text-caption text-grey-5 q-mt-xs q-mb-sm">
              Se dispara cuando el asesor envió el último mensaje y el cliente no ha respondido dentro del tiempo establecido.
            </p>

            <div v-if="form.notify_client_inactivity" class="row items-center q-gutter-sm">
              <q-input
                v-model.number="form.client_timeout_minutes"
                label="Tiempo Máx. Espera (Minutos) *"
                type="number"
                outlined
                dark
                dense
                style="width: 170px"
                min="1"
                max="10080"
              >
                <template #append>
                  <span class="text-caption text-grey-5">min</span>
                </template>
              </q-input>

              <!-- Presets rápidos -->
              <div class="row items-center q-gutter-xs">
                <q-chip
                  v-for="preset in [30, 60, 120, 240, 720, 1440]"
                  :key="preset"
                  clickable
                  dense
                  dark
                  size="sm"
                  :color="form.client_timeout_minutes === preset ? 'cyan-9' : 'grey-9'"
                  :text-color="form.client_timeout_minutes === preset ? 'cyan-2' : 'grey-4'"
                  @click="form.client_timeout_minutes = preset"
                >
                  {{ preset >= 60 ? (preset / 60) + 'h' : preset + 'm' }} {{ preset === 120 ? '(Default)' : '' }}
                </q-chip>
              </div>
            </div>
          </div>

          <!-- SECCIÓN 3: ESTADOS DEL LEAD APLICABLES (CHECKEABLES) -->
          <div class="xf-section-box">
            <div class="row items-center justify-between">
              <div class="row items-center q-gutter-x-xs">
                <q-icon name="sym_r_tune" size="18px" color="teal-4" />
                <span class="text-subtitle2 text-bold text-white">Estados del Lead Aplicables</span>
              </div>
              <q-checkbox
                v-model="form.apply_to_all_statuses"
                dark
                color="teal-4"
                label="Todos los estados (Default)"
              />
            </div>
            <p class="text-caption text-grey-5 q-mt-xs q-mb-sm">
              {{ form.apply_to_all_statuses 
                ? 'La regla se aplicará automáticamente a todas las etapas y estados del ciclo de vida del lead.' 
                : 'Selecciona puntualmente las etapas en las que esta regla de tiempo debe estar vigente.' }}
            </p>

            <!-- Lista checkeable con buscador si no aplica a todos -->
            <div v-if="!form.apply_to_all_statuses" class="q-mt-sm">
              <div class="row items-center justify-between q-mb-xs">
                <q-input
                  v-model="statusFilterText"
                  dense
                  outlined
                  dark
                  placeholder="Buscar estado..."
                  style="width: 220px"
                  clearable
                >
                  <template #prepend>
                    <q-icon name="sym_r_search" size="16px" color="grey-5" />
                  </template>
                </q-input>

                <div class="row items-center q-gutter-x-xs">
                  <q-btn flat dense no-caps size="xs" color="teal-3" label="Marcar todos" @click="selectAllStatuses" />
                  <span class="text-grey-6">|</span>
                  <q-btn flat dense no-caps size="xs" color="grey-4" label="Desmarcar todos" @click="deselectAllStatuses" />
                </div>
              </div>

              <!-- Lista de Estados Checkeables -->
              <div class="xf-status-checklist q-pa-xs">
                <div
                  v-for="st in filteredStatuses"
                  :key="st.id"
                  class="xf-status-check-item row items-center justify-between q-pa-xs cursor-pointer"
                  @click="toggleStatusSelection(st.id)"
                >
                  <div class="row items-center q-gutter-x-xs">
                    <q-checkbox
                      :model-value="form.custom_status_ids.includes(st.id)"
                      dense
                      dark
                      color="teal-4"
                      @update:model-value="toggleStatusSelection(st.id)"
                    />
                    <span class="xf-dot-indicator q-mr-xs" :style="{ backgroundColor: st.color || '#10b981' }"></span>
                    <span class="text-body2 text-white">{{ st.name }}</span>
                  </div>

                  <q-badge
                    :color="getStageColor(st.stage_type)"
                    class="text-caption"
                  >
                    {{ formatStage(st.stage_type) }}
                  </q-badge>
                </div>

                <div v-if="filteredStatuses.length === 0" class="text-caption text-grey-5 text-center q-pa-sm italic">
                  No se encontraron estados coincidentes.
                </div>
              </div>

              <div class="text-caption text-grey-4 q-mt-xs">
                Estados seleccionados: <strong>{{ form.custom_status_ids.length }}</strong> de {{ customStatuses.length }}
              </div>
            </div>
          </div>

          <!-- Toggle Regla Activa -->
          <q-toggle
            v-model="form.is_active"
            dark
            color="primary"
            label="Regla activa y monitoreando inmediatamente tras guardar"
          />
        </q-card-section>

        <q-card-actions align="right" class="q-pa-md">
          <q-btn flat label="Cancelar" color="grey-4" no-caps v-close-popup />
          <q-btn
            unelevated
            label="Guardar Regla"
            color="primary"
            no-caps
            class="xf-btn-primary"
            :loading="saving"
            @click="saveRule"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- DIÁLOGO: CONFIRMAR ELIMINACIÓN -->
    <AppConfirmDialog
      v-model="isConfirmDeleteOpen"
      title="Eliminar regla de tiempo"
      :message="`¿Estás seguro de que deseas eliminar la regla '${ruleToDelete?.name}'?`"
      confirm-label="Eliminar"
      confirm-color="negative"
      :loading="deleting"
      @confirm="executeDelete"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { http } from '@/shared/api/http'
import { useAppNotify } from '@/shared/composables/useAppNotify'
import AppConfirmDialog from '@/shared/components/AppConfirmDialog.vue'
import BusinessHoursSection from './BusinessHoursSection.vue'

interface CustomStatusItem {
  id: string
  name: string
  color: string
  icon?: string
  stage_type?: string
}

interface TimeAlertRuleItem {
  id: string
  organization_id: string
  name: string
  description?: string
  is_active: boolean
  user_timeout_minutes: number
  client_timeout_minutes: number
  notify_user_inactivity: boolean
  notify_client_inactivity: boolean
  apply_to_all_statuses: boolean
  custom_status_ids?: string[]
  assigned_statuses?: CustomStatusItem[]
  severity: 'info' | 'warning' | 'critical'
  action_type: string
  created_at: string
}

const props = withDefaults(
  defineProps<{
    customStatuses?: CustomStatusItem[]
  }>(),
  {
    customStatuses: () => [],
  },
)

const notify = useAppNotify()

const subTab = ref<'inactivity' | 'business_hours'>('inactivity')
const rules = ref<TimeAlertRuleItem[]>([])
const loading = ref(false)
const saving = ref(false)
const deleting = ref(false)

const isDialogOpen = ref(false)
const isConfirmDeleteOpen = ref(false)
const selectedRule = ref<TimeAlertRuleItem | null>(null)
const ruleToDelete = ref<TimeAlertRuleItem | null>(null)

const statusFilterText = ref('')

const form = reactive({
  name: '',
  description: '',
  user_timeout_minutes: 20,
  client_timeout_minutes: 120,
  notify_user_inactivity: true,
  notify_client_inactivity: true,
  apply_to_all_statuses: true,
  custom_status_ids: [] as string[],
  severity: 'warning' as 'info' | 'warning' | 'critical',
  is_active: true,
})

const severityOptions = [
  { label: 'Informativa (Azul)', value: 'info' },
  { label: 'Advertencia (Ámbar)', value: 'warning' },
  { label: 'Crítica (Rojo)', value: 'critical' },
]

// KPIs
const activeRulesCount = computed(() => rules.value.filter((r) => r.is_active).length)
const defaultUserTimeout = computed(() => {
  const activeRule = rules.value.find((r) => r.is_active && r.notify_user_inactivity)
  return activeRule ? activeRule.user_timeout_minutes : 20
})
const defaultClientTimeout = computed(() => {
  const activeRule = rules.value.find((r) => r.is_active && r.notify_client_inactivity)
  return activeRule ? activeRule.client_timeout_minutes : 120
})
const coveredStatusesSummary = computed(() => {
  const hasGlobal = rules.value.some((r) => r.is_active && r.apply_to_all_statuses)
  if (hasGlobal) return '100% (Todos)'
  const coveredIds = new Set<string>()
  rules.value.filter((r) => r.is_active).forEach((r) => {
    r.custom_status_ids?.forEach((id) => coveredIds.add(id))
  })
  return `${coveredIds.size} / ${props.customStatuses.length || 0}`
})

const filteredStatuses = computed(() => {
  const needle = statusFilterText.value.toLowerCase().trim()
  if (!needle) return props.customStatuses
  return props.customStatuses.filter((s) => s.name.toLowerCase().includes(needle))
})

// Acciones API
async function fetchRules() {
  loading.value = true
  try {
    const res = await http.get('/time-alert-rules')
    rules.value = res.data.data || []
  } catch (err: any) {
    notify.error({ message: 'Error al cargar las reglas de tiempo.' })
  } finally {
    loading.value = false
  }
}

async function toggleRuleActive(rule: TimeAlertRuleItem) {
  try {
    const res = await http.patch(`/time-alert-rules/${rule.id}/toggle`)
    rule.is_active = res.data.data.is_active
    notify.success({
      message: `Regla ${rule.name} ${rule.is_active ? 'activada' : 'pausada'}.`,
    })
  } catch {
    notify.error({ message: 'No se pudo modificar el estado de la regla.' })
  }
}

function openCreateDialog() {
  selectedRule.value = null
  form.name = 'Alerta de Tiempo de Inactividad'
  form.description = ''
  form.user_timeout_minutes = 20
  form.client_timeout_minutes = 120
  form.notify_user_inactivity = true
  form.notify_client_inactivity = true
  form.apply_to_all_statuses = true
  form.custom_status_ids = props.customStatuses.map((s) => s.id)
  form.severity = 'warning'
  form.is_active = true
  statusFilterText.value = ''
  isDialogOpen.value = true
}

function openEditDialog(rule: TimeAlertRuleItem) {
  selectedRule.value = rule
  form.name = rule.name
  form.description = rule.description || ''
  form.user_timeout_minutes = rule.user_timeout_minutes
  form.client_timeout_minutes = rule.client_timeout_minutes
  form.notify_user_inactivity = rule.notify_user_inactivity
  form.notify_client_inactivity = rule.notify_client_inactivity
  form.apply_to_all_statuses = rule.apply_to_all_statuses
  form.custom_status_ids = rule.custom_status_ids ? [...rule.custom_status_ids] : []
  form.severity = rule.severity
  form.is_active = rule.is_active
  statusFilterText.value = ''
  isDialogOpen.value = true
}

function toggleStatusSelection(id: string) {
  const idx = form.custom_status_ids.indexOf(id)
  if (idx > -1) {
    form.custom_status_ids.splice(idx, 1)
  } else {
    form.custom_status_ids.push(id)
  }
}

function selectAllStatuses() {
  form.custom_status_ids = props.customStatuses.map((s) => s.id)
}

function deselectAllStatuses() {
  form.custom_status_ids = []
}

async function saveRule() {
  if (!form.name.trim()) {
    notify.warning({ message: 'El nombre de la regla es obligatorio.' })
    return
  }

  saving.value = true
  try {
    const payload = {
      name: form.name.trim(),
      description: form.description.trim() || null,
      user_timeout_minutes: form.user_timeout_minutes,
      client_timeout_minutes: form.client_timeout_minutes,
      notify_user_inactivity: form.notify_user_inactivity,
      notify_client_inactivity: form.notify_client_inactivity,
      apply_to_all_statuses: form.apply_to_all_statuses,
      custom_status_ids: form.apply_to_all_statuses ? [] : form.custom_status_ids,
      severity: form.severity,
      action_type: 'visual_badge',
      is_active: form.is_active,
    }

    if (selectedRule.value) {
      await http.put(`/time-alert-rules/${selectedRule.value.id}`, payload)
      notify.success({ message: 'Regla de tiempo actualizada correctamente.' })
    } else {
      await http.post('/time-alert-rules', payload)
      notify.success({ message: 'Regla de tiempo creada exitosamente.' })
    }

    isDialogOpen.value = false
    await fetchRules()
  } catch (err: any) {
    notify.error({ message: err?.response?.data?.message || 'Error al guardar la regla.' })
  } finally {
    saving.value = false
  }
}

function confirmDelete(rule: TimeAlertRuleItem) {
  ruleToDelete.value = rule
  isConfirmDeleteOpen.value = true
}

async function executeDelete() {
  if (!ruleToDelete.value) return
  deleting.value = true
  try {
    await http.delete(`/time-alert-rules/${ruleToDelete.value.id}`)
    notify.success({ message: 'Regla eliminada exitosamente.' })
    isConfirmDeleteOpen.value = false
    ruleToDelete.value = null
    await fetchRules()
  } catch {
    notify.error({ message: 'No se pudo eliminar la regla.' })
  } finally {
    deleting.value = false
  }
}

async function seedDefaultRule() {
  loading.value = true
  try {
    await http.post('/time-alert-rules/seed-default')
    notify.success({ message: 'Regla base recomendada generada con éxito.' })
    await fetchRules()
  } catch {
    notify.error({ message: 'Error al generar la regla estándar.' })
  } finally {
    loading.value = false
  }
}

// Formatters
function formatMinutes(mins: number): string {
  if (mins < 60) return `${mins} minutos`
  const hours = (mins / 60).toFixed(mins % 60 === 0 ? 0 : 1)
  return `${hours} horas (${mins} min)`
}

function formatSeverity(sev: string): string {
  switch (sev) {
    case 'info': return 'Informativa'
    case 'warning': return 'Advertencia'
    case 'critical': return 'Crítica'
    default: return sev
  }
}

function getSeverityColor(sev: string): string {
  switch (sev) {
    case 'info': return 'blue'
    case 'warning': return 'amber'
    case 'critical': return 'red'
    default: return 'amber'
  }
}

function getSeverityBadgeColor(sev: string): string {
  switch (sev) {
    case 'info': return 'blue-9'
    case 'warning': return 'amber-9'
    case 'critical': return 'negative'
    default: return 'amber-9'
  }
}

function getSeverityIcon(sev: string): string {
  switch (sev) {
    case 'info': return 'sym_r_info'
    case 'warning': return 'sym_r_warning'
    case 'critical': return 'sym_r_emergency_home'
    default: return 'sym_r_timer'
  }
}

function formatStage(stage?: string): string {
  switch (stage) {
    case 'initial': return 'Inicial'
    case 'in_progress': return 'En Proceso'
    case 'won': return 'Ganado'
    case 'lost': return 'Perdido'
    default: return stage || 'General'
  }
}

function getStageColor(stage?: string): string {
  switch (stage) {
    case 'initial': return 'blue-9'
    case 'in_progress': return 'cyan-9'
    case 'won': return 'positive'
    case 'lost': return 'negative'
    default: return 'grey-8'
  }
}

onMounted(() => {
  fetchRules()
})
</script>

<style scoped lang="scss">
.time-alert-rules-tab {
  width: 100%;
}

.xf-time-banner {
  background: linear-gradient(135deg, rgba(245, 158, 11, 0.12) 0%, rgba(17, 24, 39, 0.7) 100%);
  border: 1px solid rgba(245, 158, 11, 0.3);
}

.xf-kpi-card {
  background: var(--crm-bg-card, #111827);
  border: 1px solid var(--crm-color-border, rgba(255, 255, 255, 0.08));
  border-radius: 12px;
  padding: 16px;
}

.xf-rule-card {
  background: var(--crm-bg-card, #111827);
  border: 1px solid var(--crm-color-border, rgba(255, 255, 255, 0.08));
  border-radius: 14px;
  transition: all 0.2s ease;

  &:hover {
    border-color: rgba(245, 158, 11, 0.35);
  }

  &--inactive {
    opacity: 0.75;
    filter: grayscale(0.2);
  }
}

.xf-param-pill {
  background: rgba(255, 255, 255, 0.03);
  border: 1px solid rgba(255, 255, 255, 0.06);
  border-radius: 8px;
  padding: 10px 12px;
  min-height: 72px;
}

.xf-section-box {
  background: rgba(255, 255, 255, 0.02);
  border: 1px solid rgba(255, 255, 255, 0.07);
  border-radius: 10px;
  padding: 14px;
}

.xf-status-checklist {
  max-height: 200px;
  overflow-y: auto;
  background: rgba(0, 0, 0, 0.2);
  border: 1px solid rgba(255, 255, 255, 0.06);
  border-radius: 8px;
}

.xf-status-check-item {
  border-bottom: 1px solid rgba(255, 255, 255, 0.04);
  border-radius: 6px;

  &:hover {
    background: rgba(255, 255, 255, 0.05);
  }

  &:last-child {
    border-bottom: none;
  }
}

.xf-empty-card {
  background: var(--crm-bg-card, #111827);
  border: 1px dashed rgba(255, 255, 255, 0.15);
  border-radius: 16px;
}

.xf-modal-card {
  background: var(--crm-bg-card, #111827);
  border: 1px solid var(--crm-color-border, rgba(255, 255, 255, 0.1));
  border-radius: 14px;
}

.xf-btn-primary {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%) !important;
  font-weight: 600;
  border-radius: 8px;
}

.xf-dot-indicator {
  width: 9px;
  height: 9px;
  border-radius: 50%;
  display: inline-block;
}
</style>
