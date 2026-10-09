<template>
  <div class="business-hours-section q-gutter-y-lg">
    <!-- 1. Estado en Tiempo Real -->
    <q-card flat bordered class="xf-status-banner q-pa-md" :class="isCurrentlyOpen ? 'xf-status-banner--open' : 'xf-status-banner--closed'">
      <div class="row items-center justify-between no-wrap">
        <div class="row items-center q-gutter-x-md">
          <q-avatar size="44px" :color="isCurrentlyOpen ? 'positive' : 'amber-9'" text-color="white">
            <q-icon :name="isCurrentlyOpen ? 'sym_r_storefront' : 'sym_r_nightlight'" size="26px" />
          </q-avatar>
          <div>
            <div class="row items-center q-gutter-x-sm">
              <span class="text-subtitle1 text-bold text-white">
                {{ isCurrentlyOpen ? '🟢 EN HORARIO DE ATENCIÓN' : '🌙 FUERA DE HORARIO LABORAL' }}
              </span>
              <q-badge :color="form.is_active ? 'teal-9' : 'grey-8'" class="text-caption text-bold">
                {{ form.is_active ? 'Horario Activo' : 'Pausado' }}
              </q-badge>
              <q-badge v-if="form.auto_reply_enabled" color="cyan-9" text-color="cyan-2" class="text-caption">
                Auto-respuesta Activa
              </q-badge>
            </div>
            <div class="text-caption text-grey-4 q-mt-xs">
              {{ currentDayName }}, {{ currentTime }} ({{ form.timezone }}).
              {{ isCurrentlyOpen ? 'Los asesores se encuentran disponibles para recibir y responder chats.' : 'Los mensajes entrantes recibirán la respuesta automática si está habilitada.' }}
            </div>
          </div>
        </div>

        <div class="row items-center q-gutter-x-xs">
          <span class="text-caption text-grey-4">Control General:</span>
          <q-toggle
            v-model="form.is_active"
            dense
            color="positive"
            @update:model-value="toggleActive"
          >
            <q-tooltip>{{ form.is_active ? 'Pausar horario' : 'Activar horario' }}</q-tooltip>
          </q-toggle>
        </div>
      </div>
    </q-card>

    <!-- Spinner de carga -->
    <div v-if="loading" class="text-center q-pa-xl text-grey-4">
      <q-spinner-dots size="36px" color="primary" />
      <div class="q-mt-sm">Cargando configuración de horario laboral...</div>
    </div>

    <div v-else class="q-gutter-y-lg">
      <!-- 2. Sector: Vigencia del Horario (Iniciar Ya vs Rango de Fechas) -->
      <q-card flat bordered class="xf-panel-card q-pa-lg">
        <div class="row items-center justify-between q-mb-md">
          <div>
            <div class="text-subtitle1 text-bold text-white flex items-center q-gutter-x-xs">
              <q-icon name="sym_r_calendar_today" color="amber-4" size="20px" />
              <span>Vigencia del Horario Laboral</span>
            </div>
            <div class="text-caption text-grey-4">
              Selecciona si el horario rige de inmediato de forma continua o si corresponde a un rango de fechas programado.
            </div>
          </div>

          <!-- Selector de Zona Horaria -->
          <div class="row items-center q-gutter-x-xs">
            <span class="text-caption text-grey-4">Zona Horaria:</span>
            <q-select
              v-model="form.timezone"
              :options="timezoneOptions"
              dense
              outlined
              dark
              options-dense
              style="min-width: 190px"
            />
          </div>
        </div>

        <!-- Selector Tipo de Vigencia -->
        <div class="row q-col-gutter-md q-mb-md">
          <!-- Opción 1: Iniciar Ya -->
          <div class="col-12 col-md-6">
            <div
              class="xf-validity-option cursor-pointer"
              :class="{ 'xf-validity-option--selected': form.validity_type === 'immediate' }"
              @click="form.validity_type = 'immediate'"
            >
              <div class="row items-start no-wrap q-gutter-x-sm">
                <q-radio v-model="form.validity_type" val="immediate" dark color="primary" dense />
                <div>
                  <div class="text-body2 text-bold text-white flex items-center q-gutter-x-xs">
                    <q-icon name="sym_r_bolt" color="amber-4" size="18px" />
                    <span>Iniciar Ya (Continuo e indefinido)</span>
                  </div>
                  <div class="text-caption text-grey-4 q-mt-xs">
                    El horario laboral entra en vigencia de inmediato y se aplica permanentemente semana a semana sin fecha de expiración.
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Opción 2: Rango de Fechas -->
          <div class="col-12 col-md-6">
            <div
              class="xf-validity-option cursor-pointer"
              :class="{ 'xf-validity-option--selected': form.validity_type === 'date_range' }"
              @click="form.validity_type = 'date_range'"
            >
              <div class="row items-start no-wrap q-gutter-x-sm">
                <q-radio v-model="form.validity_type" val="date_range" dark color="primary" dense />
                <div>
                  <div class="text-body2 text-bold text-white flex items-center q-gutter-x-xs">
                    <q-icon name="sym_r_date_range" color="cyan-4" size="18px" />
                    <span>Programar Rango de Fechas (Temporada / Período)</span>
                  </div>
                  <div class="text-caption text-grey-4 q-mt-xs">
                    Aplica para temporadas específicas (por ejemplo: periodo de admisiones, semestre universitario, receso o campañas).
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Inputs de Rango de Fechas (si está seleccionado date_range) -->
        <div v-if="form.validity_type === 'date_range'" class="row q-col-gutter-md bg-dark-subtle q-pa-md rounded-borders">
          <div class="col-12 col-sm-6">
            <q-input
              v-model="form.start_date"
              type="date"
              label="Fecha de Inicio *"
              outlined
              dark
              dense
            >
              <template #prepend>
                <q-icon name="sym_r_event" color="teal-4" size="18px" />
              </template>
            </q-input>
          </div>
          <div class="col-12 col-sm-6">
            <q-input
              v-model="form.end_date"
              type="date"
              label="Fecha de Fin *"
              outlined
              dark
              dense
            >
              <template #prepend>
                <q-icon name="sym_r_event_available" color="teal-4" size="18px" />
              </template>
            </q-input>
          </div>
        </div>
      </q-card>

      <!-- 3. Sector: Días de la Semana y Horarios de Atención -->
      <q-card flat bordered class="xf-panel-card q-pa-lg">
        <div class="row items-center justify-between q-mb-md">
          <div>
            <div class="text-subtitle1 text-bold text-white flex items-center q-gutter-x-xs">
              <q-icon name="sym_r_schedule" color="teal-4" size="20px" />
              <span>Días de la Semana & Horas de Atención</span>
            </div>
            <div class="text-caption text-grey-4">
              Marca los días en que el equipo atiende y define el rango horario de cada jornada.
            </div>
          </div>

          <q-btn
            outline
            dense
            color="teal-4"
            icon="sym_r_content_copy"
            label="Copiar Horario Lun-Vie"
            no-caps
            class="q-px-sm"
            @click="copyMondayToWeekdays"
          >
            <q-tooltip>Replica la hora de inicio y fin del Lunes a Martes, Miércoles, Jueves y Viernes</q-tooltip>
          </q-btn>
        </div>

        <!-- Lista de 7 Días -->
        <div class="q-gutter-y-sm">
          <div
            v-for="day in weekDays"
            :key="day.key"
            class="xf-day-row row items-center justify-between q-pa-sm rounded-borders"
            :class="{ 'xf-day-row--disabled': !form.schedule_days[day.key]?.enabled }"
          >
            <!-- Checkbox y Nombre del Día -->
            <div class="row items-center q-gutter-x-sm" style="min-width: 170px">
              <q-checkbox
                v-model="form.schedule_days[day.key].enabled"
                dark
                color="primary"
                dense
              />
              <span class="text-body2 text-bold" :class="form.schedule_days[day.key]?.enabled ? 'text-white' : 'text-grey-5'">
                {{ day.label }}
              </span>
            </div>

            <!-- Horarios Si está Habilitado -->
            <div v-if="form.schedule_days[day.key]?.enabled" class="row items-center q-gutter-x-sm">
              <div class="row items-center q-gutter-x-xs">
                <span class="text-caption text-grey-4">Desde:</span>
                <q-input
                  v-model="form.schedule_days[day.key].start_time"
                  type="time"
                  dense
                  outlined
                  dark
                  style="width: 120px"
                />
              </div>

              <span class="text-grey-5">—</span>

              <div class="row items-center q-gutter-x-xs">
                <span class="text-caption text-grey-4">Hasta:</span>
                <q-input
                  v-model="form.schedule_days[day.key].end_time"
                  type="time"
                  dense
                  outlined
                  dark
                  style="width: 120px"
                />
              </div>

              <q-badge color="teal-9" text-color="teal-2" class="text-caption q-ml-sm">
                {{ calculateDailyHours(form.schedule_days[day.key].start_time, form.schedule_days[day.key].end_time) }}
              </q-badge>
            </div>

            <!-- Badge si está cerrado -->
            <div v-else class="text-caption text-grey-5 italic q-px-sm">
              Cerrado / No laborable
            </div>
          </div>
        </div>
      </q-card>

      <!-- 4. Sector: Respuesta Automática Fuera de Horario Laboral -->
      <q-card flat bordered class="xf-panel-card q-pa-lg">
        <div class="row items-center justify-between q-mb-sm">
          <div class="row items-center q-gutter-x-sm">
            <q-avatar size="36px" color="cyan-9" text-color="cyan-2">
              <q-icon name="sym_r_forward_to_inbox" size="20px" />
            </q-avatar>
            <div>
              <div class="text-subtitle1 text-bold text-white">
                Respuesta Automática Fuera de Horario Laboral
              </div>
              <div class="text-caption text-grey-4">
                Envía un mensaje instantáneo a los clientes que escriban mientras el horario de atención está cerrado.
              </div>
            </div>
          </div>

          <!-- Toggle Activable o No -->
          <div class="row items-center q-gutter-x-xs">
            <span class="text-caption" :class="form.auto_reply_enabled ? 'text-cyan-3' : 'text-grey-5'">
              {{ form.auto_reply_enabled ? 'Activada' : 'Desactivada' }}
            </span>
            <q-toggle
              v-model="form.auto_reply_enabled"
              dense
              color="cyan-4"
              @update:model-value="toggleAutoReply"
            />
          </div>
        </div>

        <q-separator dark class="q-my-md" />

        <div v-if="form.auto_reply_enabled" class="row q-col-gutter-lg">
          <!-- Editor del Mensaje -->
          <div class="col-12 col-md-7 q-gutter-y-sm">
            <div class="row items-center justify-between">
              <span class="text-caption text-grey-4 text-bold">MENSAJE AUTOMÁTICO AL CLIENTE:</span>
              <q-btn
                flat
                dense
                no-caps
                size="sm"
                color="cyan-3"
                icon="sym_r_restart_alt"
                label="Restablecer mensaje predeterminado"
                @click="resetDefaultMessage"
              />
            </div>

            <q-input
              v-model="form.auto_reply_message"
              type="textarea"
              rows="4"
              outlined
              dark
              counter
              maxlength="1000"
              placeholder="Escribe el mensaje que recibirá el cliente..."
            />

            <div class="text-caption text-grey-5">
              💡 <strong>Tip:</strong> Puedes incluir información de interés, horario en que se retomará la atención o canales de auto-servicio alternativos.
            </div>
          </div>

          <!-- Vista Previa Estilo WhatsApp -->
          <div class="col-12 col-md-5">
            <div class="text-caption text-grey-4 text-bold q-mb-xs">VISTA PREVIA DE CHAT:</div>
            <div class="xf-whatsapp-preview q-pa-md">
              <div class="xf-chat-bubble">
                <div class="text-caption text-white" style="white-space: pre-line; line-height: 1.45;">
                  {{ form.auto_reply_message || '¡Hola! En este momento estamos fuera de horario...' }}
                </div>
                <div class="row justify-end items-center q-gutter-x-xs q-mt-xs">
                  <span class="text-grey-4" style="font-size: 0.68rem;">{{ currentTime }}</span>
                  <q-icon name="sym_r_done_all" size="14px" color="teal-3" />
                </div>
              </div>
            </div>
          </div>
        </div>

        <div v-else class="text-caption text-grey-5 italic q-pa-sm">
          ⚠️ La auto-respuesta está desactivada. Los mensajes recibidos fuera de horario quedarán en espera sin enviar notificación automática al cliente.
        </div>
      </q-card>

      <!-- Botón Guardar Cambios -->
      <div class="row justify-end q-mt-md">
        <q-btn
          unelevated
          color="primary"
          icon="sym_r_save"
          label="Guardar Configuración de Horario"
          no-caps
          class="xf-btn-primary q-px-xl q-py-sm text-weight-bold"
          :loading="saving"
          @click="saveBusinessHours"
        />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { http } from '@/shared/api/http'
import { useAppNotify } from '@/shared/composables/useAppNotify'

const notify = useAppNotify()

const loading = ref(false)
const saving = ref(false)

const isCurrentlyOpen = ref(true)
const currentTime = ref('08:30')
const currentDayName = ref('Lunes')

const defaultMessageTemplate =
  '¡Hola! Gracias por comunicarte con nosotros. En este momento nos encontramos fuera de nuestro horario de atención habitual. Tu mensaje ha sido registrado y un asesor te responderá a la brevedad tan pronto retomemos actividades. ¡Agradecemos tu paciencia!'

const weekDays = [
  { key: 'monday', label: 'Lunes' },
  { key: 'tuesday', label: 'Martes' },
  { key: 'wednesday', label: 'Miércoles' },
  { key: 'thursday', label: 'Jueves' },
  { key: 'friday', label: 'Viernes' },
  { key: 'saturday', label: 'Sábado' },
  { key: 'sunday', label: 'Domingo' },
]

const timezoneOptions = [
  'America/La_Paz',
  'America/Lima',
  'America/Santiago',
  'America/Bogota',
  'America/Buenos_Aires',
  'America/Mexico_City',
  'America/Sao_Paulo',
  'UTC',
]

const form = reactive({
  name: 'Horario Laboral General',
  is_active: true,
  validity_type: 'immediate' as 'immediate' | 'date_range',
  start_date: null as string | null,
  end_date: null as string | null,
  timezone: 'America/La_Paz',
  schedule_days: {
    monday: { enabled: true, start_time: '08:30', end_time: '18:30' },
    tuesday: { enabled: true, start_time: '08:30', end_time: '18:30' },
    wednesday: { enabled: true, start_time: '08:30', end_time: '18:30' },
    thursday: { enabled: true, start_time: '08:30', end_time: '18:30' },
    friday: { enabled: true, start_time: '08:30', end_time: '18:30' },
    saturday: { enabled: false, start_time: '09:00', end_time: '13:00' },
    sunday: { enabled: false, start_time: '09:00', end_time: '13:00' },
  } as Record<string, { enabled: boolean; start_time: string; end_time: string }>,
  auto_reply_enabled: true,
  auto_reply_message: defaultMessageTemplate,
})

async function fetchBusinessHours() {
  loading.value = true
  try {
    const res = await http.get('/business-hours')
    const data = res.data.data
    const meta = res.data.meta

    form.name = data.name || 'Horario Laboral General'
    form.is_active = data.is_active ?? true
    form.validity_type = data.validity_type || 'immediate'
    form.start_date = data.start_date ? data.start_date.split('T')[0] : null
    form.end_date = data.end_date ? data.end_date.split('T')[0] : null
    form.timezone = data.timezone || 'America/La_Paz'
    if (data.schedule_days) {
      form.schedule_days = { ...form.schedule_days, ...data.schedule_days }
    }
    form.auto_reply_enabled = data.auto_reply_enabled ?? true
    form.auto_reply_message = data.auto_reply_message || defaultMessageTemplate

    if (meta) {
      isCurrentlyOpen.value = meta.is_within_hours ?? true
      currentTime.value = meta.current_time || '08:30'
      currentDayName.value = translateDay(meta.current_day)
    }
  } catch {
    notify.error({ message: 'Error al cargar la configuración de horario laboral.' })
  } finally {
    loading.value = false
  }
}

async function saveBusinessHours() {
  if (form.validity_type === 'date_range' && (!form.start_date || !form.end_date)) {
    notify.warning({ message: 'Por favor define la fecha de inicio y fin para el rango programado.' })
    return
  }

  saving.value = true
  try {
    const payload = {
      name: form.name,
      is_active: form.is_active,
      validity_type: form.validity_type,
      start_date: form.validity_type === 'date_range' ? form.start_date : null,
      end_date: form.validity_type === 'date_range' ? form.end_date : null,
      timezone: form.timezone,
      schedule_days: form.schedule_days,
      auto_reply_enabled: form.auto_reply_enabled,
      auto_reply_message: form.auto_reply_message.trim(),
    }

    const res = await http.put('/business-hours', payload)
    notify.success({ message: 'Horario laboral y auto-respuesta guardados exitosamente.' })

    if (res.data.meta) {
      isCurrentlyOpen.value = res.data.meta.is_within_hours
      currentTime.value = res.data.meta.current_time
      currentDayName.value = translateDay(res.data.meta.current_day)
    }
  } catch (err: any) {
    notify.error({ message: err?.response?.data?.message || 'Error al guardar horario.' })
  } finally {
    saving.value = false
  }
}

async function toggleActive() {
  try {
    const res = await http.patch('/business-hours/toggle')
    form.is_active = res.data.data.is_active
    notify.success({ message: res.data.message })
    await fetchBusinessHours()
  } catch {
    notify.error({ message: 'No se pudo alternar el estado del horario laboral.' })
  }
}

async function toggleAutoReply() {
  try {
    const res = await http.patch('/business-hours/toggle-auto-reply')
    form.auto_reply_enabled = res.data.data.auto_reply_enabled
    notify.success({ message: res.data.message })
  } catch {
    notify.error({ message: 'No se pudo alternar la auto-respuesta.' })
  }
}

function copyMondayToWeekdays() {
  const mon = form.schedule_days.monday
  if (!mon) return
  ;['tuesday', 'wednesday', 'thursday', 'friday'].forEach((k) => {
    form.schedule_days[k].enabled = mon.enabled
    form.schedule_days[k].start_time = mon.start_time
    form.schedule_days[k].end_time = mon.end_time
  })
  notify.info({ message: 'Horario de Lunes replicado de Martes a Viernes.' })
}

function resetDefaultMessage() {
  form.auto_reply_message = defaultMessageTemplate
  notify.info({ message: 'Mensaje restaurado al valor predeterminado.' })
}

function calculateDailyHours(start: string, end: string): string {
  if (!start || !end) return ''
  const [sh, sm] = start.split(':').map(Number)
  const [eh, em] = end.split(':').map(Number)
  let mins = eh * 60 + em - (sh * 60 + sm)
  if (mins < 0) mins += 24 * 60
  const h = Math.floor(mins / 60)
  const m = mins % 60
  return m > 0 ? `${h}h ${m}m de atención` : `${h} horas de atención`
}

function translateDay(day?: string): string {
  switch (day) {
    case 'monday': return 'Lunes'
    case 'tuesday': return 'Martes'
    case 'wednesday': return 'Miércoles'
    case 'thursday': return 'Jueves'
    case 'friday': return 'Viernes'
    case 'saturday': return 'Sábado'
    case 'sunday': return 'Domingo'
    default: return 'Hoy'
  }
}

onMounted(() => {
  fetchBusinessHours()
})
</script>

<style scoped lang="scss">
.business-hours-section {
  width: 100%;
}

.xf-status-banner {
  border-radius: 12px;
  transition: all 0.2s ease;

  &--open {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.12) 0%, rgba(17, 24, 39, 0.7) 100%);
    border: 1px solid rgba(16, 185, 129, 0.35);
  }

  &--closed {
    background: linear-gradient(135deg, rgba(245, 158, 11, 0.12) 0%, rgba(17, 24, 39, 0.7) 100%);
    border: 1px solid rgba(245, 158, 11, 0.35);
  }
}

.xf-panel-card {
  background: var(--crm-bg-card, #111827);
  border: 1px solid var(--crm-color-border, rgba(255, 255, 255, 0.08));
  border-radius: 14px;
}

.xf-validity-option {
  background: rgba(255, 255, 255, 0.03);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 10px;
  padding: 14px;
  transition: all 0.2s ease;

  &:hover {
    background: rgba(255, 255, 255, 0.05);
    border-color: rgba(16, 185, 129, 0.3);
  }

  &--selected {
    background: rgba(16, 185, 129, 0.08);
    border-color: rgba(16, 185, 129, 0.5);
  }
}

.bg-dark-subtle {
  background: rgba(0, 0, 0, 0.25);
}

.xf-day-row {
  background: rgba(255, 255, 255, 0.02);
  border: 1px solid rgba(255, 255, 255, 0.05);
  transition: background 0.15s ease;

  &:hover {
    background: rgba(255, 255, 255, 0.04);
  }

  &--disabled {
    opacity: 0.65;
  }
}

.xf-whatsapp-preview {
  background: #0b141a;
  border-radius: 12px;
  border: 1px solid rgba(255, 255, 255, 0.08);
  min-height: 150px;
  display: flex;
  align-items: center;
  justify-content: flex-end;
}

.xf-chat-bubble {
  background: #005c4b;
  border-radius: 8px 0px 8px 8px;
  padding: 10px 12px;
  max-width: 90%;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
}

.xf-btn-primary {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%) !important;
  border-radius: 8px;
}
</style>
