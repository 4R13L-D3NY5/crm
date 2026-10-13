<template>
  <div class="scheduled-messages-page q-pa-lg">
    <!-- Header Principal -->
    <div class="row items-center justify-between q-mb-lg">
      <div>
        <div class="row items-center q-gutter-x-sm">
          <q-icon name="sym_r_schedule_send" size="28px" color="primary" />
          <h1 class="text-h5 text-weight-bold text-ink q-my-none">Envíos Programados</h1>
        </div>
        <p class="text-caption text-muted q-mt-xs q-mb-none">
          Automatiza y agenda recordatorios, notificaciones comerciales y mensajes diferidos por WhatsApp.
        </p>
      </div>

      <div class="row items-center q-gutter-sm">
        <q-btn
          flat
          dense
          round
          icon="sym_r_refresh"
          color="primary"
          :loading="isLoading"
          @click="loadScheduledMessages"
        >
          <q-tooltip>Actualizar lista</q-tooltip>
        </q-btn>
        <q-btn
          color="primary"
          icon="sym_r_add"
          label="Programar Mensaje"
          unelevated
          no-caps
          class="xf-btn-primary text-weight-bold"
          @click="openCreateDialog()"
        />
      </div>
    </div>

    <!-- Tarjetas KPI Superiores -->
    <div class="row q-col-gutter-md q-mb-lg">
      <div class="col-12 col-sm-6 col-md-3">
        <q-card flat class="xf-kpi-card">
          <div class="row items-center justify-between no-wrap">
            <div>
              <div class="text-caption text-muted text-uppercase text-weight-medium">Total Programados</div>
              <div class="text-h5 text-weight-bolder text-ink q-mt-xs">{{ stats.total }}</div>
            </div>
            <div class="xf-kpi-icon-wrap bg-primary-subtle text-primary">
              <q-icon name="sym_r_all_inbox" size="24px" />
            </div>
          </div>
        </q-card>
      </div>

      <div class="col-12 col-sm-6 col-md-3">
        <q-card flat class="xf-kpi-card">
          <div class="row items-center justify-between no-wrap">
            <div>
              <div class="text-caption text-muted text-uppercase text-weight-medium">Pendientes en Cola</div>
              <div class="text-h5 text-weight-bolder text-warning q-mt-xs">{{ stats.pending }}</div>
            </div>
            <div class="xf-kpi-icon-wrap bg-warning-subtle text-warning">
              <q-icon name="sym_r_hourglass_top" size="24px" />
            </div>
          </div>
        </q-card>
      </div>

      <div class="col-12 col-sm-6 col-md-3">
        <q-card flat class="xf-kpi-card">
          <div class="row items-center justify-between no-wrap">
            <div>
              <div class="text-caption text-muted text-uppercase text-weight-medium">Enviados con Éxito</div>
              <div class="text-h5 text-weight-bolder text-positive q-mt-xs">{{ stats.sent }}</div>
            </div>
            <div class="xf-kpi-icon-wrap bg-positive-subtle text-positive">
              <q-icon name="sym_r_check_circle" size="24px" />
            </div>
          </div>
        </q-card>
      </div>

      <div class="col-12 col-sm-6 col-md-3">
        <q-card flat class="xf-kpi-card">
          <div class="row items-center justify-between no-wrap">
            <div>
              <div class="text-caption text-muted text-uppercase text-weight-medium">Fallidos / Cancelados</div>
              <div class="text-h5 text-weight-bolder text-negative q-mt-xs">{{ stats.failed }}</div>
            </div>
            <div class="xf-kpi-icon-wrap bg-negative-subtle text-negative">
              <q-icon name="sym_r_error" size="24px" />
            </div>
          </div>
        </q-card>
      </div>
    </div>

    <!-- Barra de Búsqueda y Filtros -->
    <q-card flat class="xf-filter-card q-pa-md q-mb-md">
      <div class="row items-center justify-between q-col-gutter-md">
        <div class="col-12 col-md-4">
          <q-input
            v-model="searchQuery"
            dense
            outlined
            clearable
            placeholder="Buscar contacto, teléfono o mensaje..."
            class="xf-input"
            @update:model-value="applyFilters"
          >
            <template #prepend>
              <q-icon name="sym_r_search" size="18px" class="text-muted" />
            </template>
          </q-input>
        </div>

        <div class="col-12 col-sm-6 col-md-3">
          <q-select
            v-model="filterStatus"
            :options="statusOptions"
            emit-value
            map-options
            dense
            outlined
            class="xf-input"
            @update:model-value="loadScheduledMessages"
          >
            <template #prepend>
              <q-icon name="sym_r_filter_list" size="18px" class="text-muted" />
            </template>
          </q-select>
        </div>

        <div class="col-12 col-sm-6 col-md-3">
          <q-input
            v-model="filterDate"
            dense
            outlined
            clearable
            type="date"
            class="xf-input"
            @update:model-value="loadScheduledMessages"
          >
            <template #prepend>
              <q-icon name="sym_r_calendar_today" size="18px" class="text-muted" />
            </template>
          </q-input>
        </div>

        <div class="col-12 col-md-2 text-right">
          <div class="text-caption text-muted">
            {{ filteredMessages.length }} {{ filteredMessages.length === 1 ? 'registro' : 'registros' }}
          </div>
        </div>
      </div>
    </q-card>

    <!-- Estado Vacío -->
    <q-card v-if="filteredMessages.length === 0 && !isLoading" flat class="xf-empty-card q-pa-xl text-center">
      <div class="xf-empty-icon-wrap q-mx-auto q-mb-md">
        <q-icon name="sym_r_schedule" size="48px" color="primary" />
      </div>
      <div class="text-h6 text-weight-bold text-ink">No hay envíos programados</div>
      <p class="text-caption text-muted q-mt-xs q-mb-md" style="max-width: 420px; margin-inline: auto;">
        No se encontraron mensajes programados que coincidan con los filtros actuales. Agenda un nuevo recordatorio para comenzar.
      </p>
      <q-btn
        color="primary"
        label="Programar Nuevo Envío"
        icon="sym_r_add"
        unelevated
        no-caps
        class="xf-btn-primary"
        @click="openCreateDialog()"
      />
    </q-card>

    <!-- Tabla Moderna de Mensajes Programados -->
    <q-card v-else flat class="xf-table-card">
      <q-inner-loading :showing="isLoading">
        <q-spinner-dots size="40px" color="primary" />
      </q-inner-loading>

      <q-markup-table flat class="xf-table">
        <thead>
          <tr>
            <th class="text-left">DESTINATARIO</th>
            <th class="text-left">MENSAJE PROGRAMADO</th>
            <th class="text-left">PROGRAMADO PARA</th>
            <th class="text-left">CANAL</th>
            <th class="text-center">ESTADO</th>
            <th class="text-right">ACCIONES</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in filteredMessages" :key="item.id" class="xf-table-row">
            <!-- Destinatario -->
            <td class="text-left">
              <div class="row items-center no-wrap q-gutter-x-sm">
                <q-avatar size="34px" color="primary" text-color="white" class="text-weight-bold text-caption">
                  {{ (item.contact?.first_name || item.contact_name || item.recipient_phone).charAt(0).toUpperCase() }}
                </q-avatar>
                <div>
                  <div class="text-weight-bold text-ink ellipsis" style="max-width: 170px;">
                    {{ item.contact?.first_name ? `${item.contact.first_name} ${item.contact.last_name || ''}` : (item.contact_name || 'Contacto') }}
                  </div>
                  <div class="text-caption text-muted font-mono">{{ item.recipient_phone }}</div>
                </div>
              </div>
            </td>

            <!-- Mensaje -->
            <td class="text-left" style="max-width: 320px;">
              <div class="text-ink ellipsis-2-lines text-body2">
                {{ item.body }}
              </div>
              <div v-if="item.error_message" class="text-negative text-caption q-mt-xs ellipsis">
                <q-icon name="sym_r_error" size="14px" /> {{ item.error_message }}
              </div>
            </td>

            <!-- Fecha y Hora -->
            <td class="text-left">
              <div class="text-weight-medium text-ink">
                {{ formatDate(item.scheduled_at) }}
              </div>
              <div class="text-caption text-muted row items-center q-gutter-x-xs q-mt-xs">
                <q-icon name="sym_r_schedule" size="13px" />
                <span>{{ formatRelativeTime(item.scheduled_at, item.status) }}</span>
              </div>
            </td>

            <!-- Canal -->
            <td class="text-left">
              <div class="row items-center no-wrap q-gutter-x-xs">
                <q-icon name="sym_r_chat" size="16px" color="positive" />
                <span class="text-caption text-ink">
                  {{ item.whatsapp_account?.name || 'WhatsApp Principal' }}
                </span>
              </div>
            </td>

            <!-- Estado -->
            <td class="text-center">
              <q-badge
                :color="getStatusBadgeColor(item.status)"
                :label="getStatusLabel(item.status)"
                rounded
                class="text-bold q-px-sm q-py-xs"
              />
            </td>

            <!-- Acciones -->
            <td class="text-right">
              <div class="row items-center justify-end no-wrap q-gutter-x-xs">
                <!-- Enviar Ahora -->
                <q-btn
                  v-if="item.status === 'pending'"
                  flat
                  round
                  dense
                  icon="sym_r_send"
                  color="positive"
                  size="sm"
                  :loading="isSendingNowId === item.id"
                  @click="sendNow(item)"
                >
                  <q-tooltip>Enviar ahora de inmediato</q-tooltip>
                </q-btn>

                <!-- Eliminar / Cancelar -->
                <q-btn
                  flat
                  round
                  dense
                  icon="sym_r_delete"
                  color="negative"
                  size="sm"
                  @click="confirmDelete(item)"
                >
                  <q-tooltip>Cancelar y eliminar</q-tooltip>
                </q-btn>
              </div>
            </td>
          </tr>
        </tbody>
      </q-markup-table>
    </q-card>

    <!-- Modal: Programar Mensaje -->
    <q-dialog v-model="isCreateDialogOpen" persistent>
      <q-card style="width: 540px; max-width: 95vw;" class="xf-dialog-card q-pa-md">
        <q-card-section class="row items-center justify-between q-pb-none">
          <div class="row items-center q-gutter-x-sm">
            <q-avatar size="36px" color="primary-subtle" text-color="primary">
              <q-icon name="sym_r_schedule_send" size="20px" />
            </q-avatar>
            <div>
              <div class="text-h6 text-weight-bold text-ink">Programar Mensaje</div>
              <div class="text-caption text-muted">Elige destinatario, fecha y hora de entrega diferida.</div>
            </div>
          </div>
          <q-btn flat round dense icon="sym_r_close" color="grey-5" v-close-popup />
        </q-card-section>

        <q-card-section class="q-gutter-y-md q-pt-md">
          <!-- Destinatario -->
          <div>
            <div class="text-caption text-weight-bold text-ink q-mb-xs">Destinatario WhatsApp *</div>
            <q-input
              v-model="createForm.phone"
              outlined
              dense
              placeholder="Ej: +59171234567"
              class="xf-input"
              :rules="[val => !!val || 'El teléfono es obligatorio']"
            >
              <template #prepend>
                <q-icon name="sym_r_call" size="18px" class="text-muted" />
              </template>
            </q-input>
          </div>

          <!-- Nombre opcional -->
          <div>
            <div class="text-caption text-weight-bold text-ink q-mb-xs">Nombre del Contacto (opcional)</div>
            <q-input
              v-model="createForm.contact_name"
              outlined
              dense
              placeholder="Ej: David Salazar"
              class="xf-input"
            >
              <template #prepend>
                <q-icon name="sym_r_person" size="18px" class="text-muted" />
              </template>
            </q-input>
          </div>

          <!-- Fecha y Hora -->
          <div>
            <div class="text-caption text-weight-bold text-ink q-mb-xs">Fecha y Hora de Envío *</div>
            <q-input
              v-model="createForm.scheduled_at"
              outlined
              dense
              type="datetime-local"
              class="xf-input"
              :rules="[val => !!val || 'La fecha y hora son obligatorias']"
            >
              <template #prepend>
                <q-icon name="sym_r_event" size="18px" class="text-muted" />
              </template>
            </q-input>
          </div>

          <!-- Mensaje -->
          <div>
            <div class="row items-center justify-between q-mb-xs">
              <div class="text-caption text-weight-bold text-ink">Contenido del Mensaje *</div>
              <!-- Snippets rápidos -->
              <q-btn
                flat
                dense
                size="xs"
                color="primary"
                icon="sym_r_bolt"
                label="Insertar plantilla"
                no-caps
                @click="showSnippetSelector = true"
              />
            </div>
            <q-input
              v-model="createForm.body"
              outlined
              type="textarea"
              rows="4"
              placeholder="Escribe el mensaje que se enviará automáticamente..."
              class="xf-input"
              counter
              maxlength="4000"
              :rules="[val => !!val || 'El mensaje no puede estar vacío']"
            />
          </div>
        </q-card-section>

        <q-card-actions align="right" class="q-pt-none q-px-md">
          <q-btn flat label="Cancelar" color="grey-6" no-caps v-close-popup />
          <q-btn
            unelevated
            label="Programar Envío"
            color="primary"
            icon="sym_r_schedule_send"
            no-caps
            class="xf-btn-primary"
            :loading="isSaving"
            @click="submitCreate"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Dialog de Inserción de Plantillas de Respuestas Rápidas -->
    <q-dialog v-model="showSnippetSelector">
      <q-card style="width: 440px; max-width: 90vw;" class="xf-dialog-card q-pa-md">
        <q-card-section class="q-pb-xs">
          <div class="text-subtitle1 text-weight-bold text-ink">Seleccionar Respuesta Rápida</div>
          <div class="text-caption text-muted">Haz clic en una plantilla para copiarla al mensaje.</div>
        </q-card-section>

        <q-card-section class="q-pt-sm" style="max-height: 350px; overflow-y: auto;">
          <div v-if="quickMessagesList.length === 0" class="text-center text-muted q-pa-md">
            No tienes respuestas rápidas configuradas aún.
          </div>
          <q-list v-else separator>
            <q-item
              v-for="qm in quickMessagesList"
              :key="qm.id"
              clickable
              v-ripple
              class="rounded-borders q-my-xs xf-snippet-item"
              @click="selectSnippet(qm.message)"
            >
              <q-item-section avatar style="min-width: 32px;">
                <q-icon name="sym_r_bolt" color="primary" size="18px" />
              </q-item-section>
              <q-item-section>
                <q-item-label class="text-ink text-weight-medium">/{{ qm.shortcut }}</q-item-label>
                <q-item-label caption class="text-muted ellipsis-2-lines">{{ qm.message }}</q-item-label>
              </q-item-section>
            </q-item>
          </q-list>
        </q-card-section>

        <q-card-actions align="right">
          <q-btn flat label="Cerrar" color="grey-6" no-caps v-close-popup />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { http } from '@/shared/api/http'
import { useAppNotify } from '@/shared/composables/useAppNotify'
import { useQuickMessages } from '@/modules/quick-messages/composables/useQuickMessages'

const notify = useAppNotify()
const quickMessagesQuery = useQuickMessages()

interface ScheduledItem {
  id: string
  organization_id: string
  contact_id?: string
  contact_name?: string
  recipient_phone: string
  body: string
  scheduled_at: string
  status: 'pending' | 'sent' | 'failed' | 'cancelled'
  sent_at?: string
  error_message?: string
  contact?: {
    id: string
    first_name: string
    last_name?: string
    phone: string
  }
  whatsapp_account?: {
    id: string
    name: string
  }
}

const scheduledList = ref<ScheduledItem[]>([])
const isLoading = ref(false)
const isSaving = ref(false)
const isSendingNowId = ref<string | null>(null)

const stats = reactive({
  total: 0,
  pending: 0,
  sent: 0,
  failed: 0,
})

const searchQuery = ref('')
const filterStatus = ref('all')
const filterDate = ref('')

const statusOptions = [
  { label: 'Todos los estados', value: 'all' },
  { label: 'Pendientes', value: 'pending' },
  { label: 'Enviados', value: 'sent' },
  { label: 'Fallidos / Cancelados', value: 'failed' },
]

// Modal Crear
const isCreateDialogOpen = ref(false)
const showSnippetSelector = ref(false)

const createForm = reactive({
  phone: '',
  contact_name: '',
  scheduled_at: '',
  body: '',
  contact_id: null as string | null,
})

const quickMessagesList = computed(() => quickMessagesQuery.data.value ?? [])

// Cargar mensajes programados
async function loadScheduledMessages() {
  isLoading.value = true
  try {
    const params: Record<string, string> = {}
    if (filterStatus.value !== 'all') params.status = filterStatus.value
    if (filterDate.value) params.date = filterDate.value
    if (searchQuery.value.trim()) params.search = searchQuery.value.trim()

    const res = await http.get('/scheduled-messages', { params })
    scheduledList.value = res.data?.data ?? []

    if (res.data?.stats) {
      stats.total = res.data.stats.total
      stats.pending = res.data.stats.pending
      stats.sent = res.data.stats.sent
      stats.failed = res.data.stats.failed
    } else {
      // Recalcular localmente
      stats.total = scheduledList.value.length
      stats.pending = scheduledList.value.filter(s => s.status === 'pending').length
      stats.sent = scheduledList.value.filter(s => s.status === 'sent').length
      stats.failed = scheduledList.value.filter(s => s.status === 'failed' || s.status === 'cancelled').length
    }
  } catch (error: any) {
    notify.error({ message: 'No se pudieron cargar los mensajes programados.' })
  } finally {
    isLoading.value = false
  }
}

// Filtros en cliente (búsqueda instantánea reactiva)
const filteredMessages = computed(() => {
  let list = scheduledList.value
  const query = searchQuery.value.trim().toLowerCase()

  if (query) {
    list = list.filter(m => {
      const name = (m.contact?.first_name || m.contact_name || '').toLowerCase()
      const phone = (m.recipient_phone || '').toLowerCase()
      const body = (m.body || '').toLowerCase()
      return name.includes(query) || phone.includes(query) || body.includes(query)
    })
  }

  return list
})

function applyFilters() {
  // Búsqueda instantánea por computed
}

// Abrir diálogo de creación
function openCreateDialog(preset?: { phone?: string; contact_name?: string; contact_id?: string; body?: string }) {
  const now = new Date()
  now.setMinutes(now.getMinutes() + 15) // Sugerir 15 minutos en adelante
  const tzOffset = now.getTimezoneOffset() * 60000
  const localISOTime = new Date(now.getTime() - tzOffset).toISOString().slice(0, 16)

  createForm.phone = preset?.phone || ''
  createForm.contact_name = preset?.contact_name || ''
  createForm.contact_id = preset?.contact_id || null
  createForm.body = preset?.body || ''
  createForm.scheduled_at = localISOTime

  isCreateDialogOpen.value = true
}

// Guardar mensaje programado
async function submitCreate() {
  if (!createForm.phone.trim() || !createForm.scheduled_at || !createForm.body.trim()) {
    notify.warning({ message: 'Por favor completa todos los campos requeridos.' })
    return
  }

  isSaving.value = true
  try {
    const scheduledIso = createForm.scheduled_at
      ? new Date(createForm.scheduled_at).toISOString()
      : createForm.scheduled_at

    await http.post('/scheduled-messages', {
      recipient_phone: createForm.phone.trim(),
      contact_name: createForm.contact_name.trim() || undefined,
      contact_id: createForm.contact_id || undefined,
      scheduled_at: scheduledIso,
      body: createForm.body.trim(),
    })

    notify.success({ message: 'Mensaje programado exitosamente.' })
    isCreateDialogOpen.value = false
    await loadScheduledMessages()
  } catch (error: any) {
    notify.error({ message: error.response?.data?.message || 'Error al programar el mensaje.' })
  } finally {
    isSaving.value = false
  }
}

// Enviar ahora de inmediato
async function sendNow(item: ScheduledItem) {
  isSendingNowId.value = item.id
  try {
    await http.post(`/scheduled-messages/${item.id}/send-now`)
    notify.success({ message: `Mensaje enviado de inmediato a ${item.recipient_phone}` })
    await loadScheduledMessages()
  } catch (error: any) {
    notify.error({ message: error.response?.data?.message || 'Error al enviar el mensaje de inmediato.' })
  } finally {
    isSendingNowId.value = null
  }
}

// Eliminar / Cancelar
async function confirmDelete(item: ScheduledItem) {
  try {
    await http.delete(`/scheduled-messages/${item.id}`)
    notify.info({ message: 'Mensaje programado cancelado y eliminado.' })
    await loadScheduledMessages()
  } catch (error: any) {
    notify.error({ message: 'Error al cancelar el mensaje programado.' })
  }
}

// Inserción de Snippet
function selectSnippet(text: string) {
  if (createForm.body) {
    createForm.body += '\n' + text
  } else {
    createForm.body = text
  }
  showSnippetSelector.value = false
}

// Formateadores
function formatDate(dateStr: string): string {
  if (!dateStr) return '-'
  try {
    const d = new Date(dateStr)
    return d.toLocaleString('es-BO', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    })
  } catch {
    return dateStr
  }
}

function formatRelativeTime(dateStr: string, status: string): string {
  if (status === 'sent') return 'Enviado'
  if (!dateStr) return '-'

  try {
    const target = new Date(dateStr).getTime()
    const now = Date.now()
    const diffMs = target - now
    const diffMins = Math.round(diffMs / 60000)

    if (diffMins < 0) return 'Listo para envío / Procesando'
    if (diffMins < 60) return `En ${diffMins} min`
    const diffHours = Math.round(diffMins / 60)
    if (diffHours < 24) return `En ${diffHours} h`
    const diffDays = Math.round(diffHours / 24)
    return `En ${diffDays} días`
  } catch {
    return '-'
  }
}

function getStatusBadgeColor(status: string): string {
  switch (status) {
    case 'sent': return 'positive'
    case 'pending': return 'warning'
    case 'failed': return 'negative'
    default: return 'grey-6'
  }
}

function getStatusLabel(status: string): string {
  switch (status) {
    case 'sent': return 'Enviado'
    case 'pending': return 'Pendiente'
    case 'failed': return 'Fallido'
    case 'cancelled': return 'Cancelado'
    default: return status
  }
}

onMounted(() => {
  loadScheduledMessages()
})
</script>

<style scoped lang="scss">
.scheduled-messages-page {
  max-width: 1400px;
  margin: 0 auto;
}

.text-ink {
  color: var(--crm-text-primary, #1e293b);
}

.text-muted {
  color: var(--crm-text-secondary, #64748b);
}

.bg-primary-subtle {
  background: rgba(13, 148, 136, 0.12);
}

.bg-warning-subtle {
  background: rgba(245, 158, 11, 0.12);
}

.bg-positive-subtle {
  background: rgba(16, 185, 129, 0.12);
}

.bg-negative-subtle {
  background: rgba(239, 68, 68, 0.12);
}

.xf-kpi-card {
  background: var(--crm-bg-card, rgba(255, 255, 255, 0.7));
  backdrop-filter: blur(12px);
  border: 1px solid var(--crm-border-color, rgba(255, 255, 255, 0.2));
  border-radius: 14px;
  padding: 18px 20px;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.04);
}

.xf-kpi-icon-wrap {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.xf-filter-card {
  background: var(--crm-bg-card, rgba(255, 255, 255, 0.7));
  backdrop-filter: blur(12px);
  border: 1px solid var(--crm-border-color, rgba(255, 255, 255, 0.2));
  border-radius: 14px;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.04);
}

.xf-empty-card {
  background: var(--crm-bg-card, rgba(255, 255, 255, 0.7));
  backdrop-filter: blur(12px);
  border: 1px dashed var(--crm-border-color, rgba(255, 255, 255, 0.3));
  border-radius: 16px;
}

.xf-empty-icon-wrap {
  width: 72px;
  height: 72px;
  border-radius: 50%;
  background: rgba(13, 148, 136, 0.1);
  display: flex;
  align-items: center;
  justify-content: center;
}

.xf-table-card {
  background: var(--crm-bg-card, rgba(255, 255, 255, 0.7));
  backdrop-filter: blur(12px);
  border: 1px solid var(--crm-border-color, rgba(255, 255, 255, 0.2));
  border-radius: 14px;
  overflow: hidden;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.04);
}

.xf-table {
  background: transparent;

  thead tr th {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.6px;
    color: var(--crm-text-secondary, #64748b);
    padding: 14px 16px;
    border-bottom: 1px solid var(--crm-border-color, rgba(255, 255, 255, 0.15));
  }

  tbody tr td {
    padding: 14px 16px;
    border-bottom: 1px solid var(--crm-border-color, rgba(255, 255, 255, 0.08));
  }

  .xf-table-row {
    transition: background-color 0.15s ease;
    &:hover {
      background: rgba(13, 148, 136, 0.04);
    }
  }
}

.xf-dialog-card {
  background: var(--crm-bg-surface, #ffffff);
  border: 1px solid var(--crm-border-color, rgba(255, 255, 255, 0.2));
  border-radius: 16px;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.18);
}

.xf-snippet-item {
  border: 1px solid var(--crm-border-color, rgba(255, 255, 255, 0.1));
  &:hover {
    background: rgba(13, 148, 136, 0.08);
  }
}
</style>
