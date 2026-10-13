<template>
  <q-dialog
    :model-value="modelValue"
    persistent
    @update:model-value="emit('update:modelValue', $event)"
  >
    <q-card style="width: 720px; max-width: 95vw" class="bulk-message-dialog">
      <!-- Encabezado con Gradiente WhatsApp -->
      <q-card-section class="bulk-message-dialog__header row items-center justify-between q-py-md">
        <div class="row items-center q-gutter-x-md">
          <q-avatar size="44px" color="teal-9" text-color="teal-2" class="shadow-2">
            <q-icon :name="sendMode === 'scheduled' ? 'sym_r_schedule_send' : 'sym_r_forward_to_inbox'" size="26px" />
          </q-avatar>
          <div>
            <div class="row items-center q-gutter-x-sm">
              <span class="text-h6 text-bold text-white">
                {{ sendMode === 'scheduled' ? 'Programar Envío Masivo' : 'Envío Masivo de Mensajes' }}
              </span>
              <q-badge :color="sendMode === 'scheduled' ? 'teal-4' : 'positive'" text-color="dark" class="text-bold q-px-sm">
                {{ sendMode === 'scheduled' ? 'Agendado' : 'WhatsApp Direct' }}
              </q-badge>
            </div>
            <div class="text-caption text-grey-4">
              {{ sendMode === 'scheduled' ? 'Agenda mensajes diferidos con fotos o videos para tus contactos' : 'Difusión personalizada en tiempo real a los contactos seleccionados' }}
            </div>
          </div>
        </div>
        <q-btn flat round dense icon="sym_r_close" color="grey-4" v-close-popup />
      </q-card-section>

      <q-separator dark class="bulk-message-dialog__separator" />

      <q-card-section class="q-gutter-y-md q-pt-md">
        <!-- Resumen de Destinatarios -->
        <div class="recipient-stats-card row items-center justify-between q-pa-sm">
          <div class="row items-center q-gutter-x-sm">
            <q-icon name="sym_r_group" size="20px" color="cyan-4" />
            <span class="text-body2 text-white text-bold">
              {{ selectedContactIds.length }} contactos seleccionados
            </span>
          </div>
          <div class="row items-center q-gutter-x-xs text-caption">
            <span class="text-positive text-bold">{{ contactsWithPhoneCount }} con teléfono</span>
            <span v-if="contactsWithoutPhoneCount > 0" class="text-amber-4 text-bold">
              • {{ contactsWithoutPhoneCount }} sin teléfono (serán omitidos)
            </span>
          </div>
        </div>

        <!-- Selector de Cuenta / Línea de WhatsApp -->
        <div>
          <div class="text-caption text-grey-4 q-mb-xs text-weight-medium">
            Línea de WhatsApp Emisora:
          </div>
          <q-select
            v-model="selectedAccountId"
            :options="whatsappAccountOptions"
            emit-value
            map-options
            outlined
            dense
            dark
            class="bulk-account-select"
          >
            <template #prepend>
              <q-icon name="sym_r_phone_iphone" size="18px" color="positive" />
            </template>
            <template #selected-item="{ opt }">
              <div class="row items-center q-gutter-x-sm text-body2 text-white">
                <span class="status-indicator status-indicator--connected"></span>
                <span class="text-bold">{{ opt.label }}</span>
                <span v-if="opt.phone" class="text-grey-4 font-mono text-caption">({{ opt.phone }})</span>
              </div>
            </template>
            <template #option="{ itemProps, opt }">
              <q-item v-bind="itemProps" dense dark class="cursor-pointer">
                <q-item-section avatar style="min-width: 24px">
                  <span
                    class="status-indicator"
                    :class="opt.connected ? 'status-indicator--connected' : 'status-indicator--disconnected'"
                  ></span>
                </q-item-section>
                <q-item-section>
                  <q-item-label class="text-body2 text-white">{{ opt.label }}</q-item-label>
                  <q-item-label v-if="opt.phone" caption class="text-grey-4 font-mono">
                    {{ opt.phone }}
                  </q-item-label>
                </q-item-section>
                <q-item-section side>
                  <q-badge
                    :color="opt.connected ? 'positive' : 'grey-7'"
                    :text-color="opt.connected ? 'dark' : 'white'"
                    class="text-caption"
                  >
                    {{ opt.connected ? 'Conectado' : 'Desconectado' }}
                  </q-badge>
                </q-item-section>
              </q-item>
            </template>
          </q-select>
        </div>

        <!-- Selector de Modalidad: Enviar Ahora vs Programar -->
        <div>
          <div class="text-caption text-grey-4 q-mb-xs text-weight-medium">
            ¿Cuándo deseas enviar el mensaje?
          </div>
          <div class="row q-col-gutter-sm">
            <div class="col-6">
              <q-card
                flat
                bordered
                class="cursor-pointer q-pa-sm text-center mode-select-card"
                :class="{ 'mode-select-card--active': sendMode === 'immediate' }"
                @click="sendMode = 'immediate'"
              >
                <div class="row items-center justify-center q-gutter-x-xs">
                  <q-icon name="sym_r_bolt" size="18px" :color="sendMode === 'immediate' ? 'positive' : 'grey-5'" />
                  <span class="text-caption text-bold" :class="sendMode === 'immediate' ? 'text-positive' : 'text-grey-4'">
                    Enviar de Inmediato
                  </span>
                </div>
              </q-card>
            </div>
            <div class="col-6">
              <q-card
                flat
                bordered
                class="cursor-pointer q-pa-sm text-center mode-select-card"
                :class="{ 'mode-select-card--active': sendMode === 'scheduled' }"
                @click="enableScheduledMode"
              >
                <div class="row items-center justify-center q-gutter-x-xs">
                  <q-icon name="sym_r_schedule_send" size="18px" :color="sendMode === 'scheduled' ? 'teal-4' : 'grey-5'" />
                  <span class="text-caption text-bold" :class="sendMode === 'scheduled' ? 'text-teal-3' : 'text-grey-4'">
                    Programar Fecha y Hora 📅
                  </span>
                </div>
              </q-card>
            </div>
          </div>
        </div>

        <!-- Bloque de Fecha y Hora Programada -->
        <transition name="q-transition--slide-down">
          <div v-if="sendMode === 'scheduled'" class="scheduled-box q-pa-sm">
            <div class="row items-center justify-between q-mb-xs">
              <span class="text-caption text-teal-3 text-weight-bold row items-center q-gutter-x-xs">
                <q-icon name="sym_r_event" size="16px" />
                <span>Fecha y Hora de Despacho *</span>
              </span>
              <!-- Atajos Rápidos -->
              <div class="row items-center q-gutter-x-xs">
                <q-btn flat dense no-caps size="xs" color="teal-3" label="+1 Hora" @click="setQuickSchedule(1)" />
                <q-btn flat dense no-caps size="xs" color="teal-3" label="Mañana 09:00" @click="setQuickScheduleTomorrow(9)" />
                <q-btn flat dense no-caps size="xs" color="teal-3" label="Mañana 15:00" @click="setQuickScheduleTomorrow(15)" />
              </div>
            </div>
            <q-input
              v-model="scheduledAt"
              type="datetime-local"
              outlined
              dense
              dark
              class="bulk-scheduled-input"
            />
            <div class="text-caption text-grey-4 q-mt-xs row items-center q-gutter-x-xs">
              <q-icon name="sym_r_info" size="14px" color="teal-4" />
              <span>Los mensajes se agendarán en <strong>Envíos Programados</strong> y saldrán de forma automática.</span>
            </div>
          </div>
        </transition>

        <!-- Editor de Mensaje y Adjunto Multimedia -->
        <div>
          <div class="row items-center justify-between q-mb-xs">
            <span class="text-caption text-grey-4 text-weight-medium">Mensaje / Leyenda:</span>
            <!-- Botón de Adjunto & Chips de Variables Dinámicas -->
            <div class="row items-center q-gutter-x-xs">
              <input
                ref="fileInputRef"
                type="file"
                accept="image/*,video/*,.pdf"
                class="hidden"
                @change="onFileSelected"
              />
              <q-btn
                outline
                dense
                no-caps
                size="sm"
                :color="attachedFile ? 'positive' : 'teal-4'"
                icon="sym_r_attach_file"
                :label="attachedFile ? 'Cambiar archivo' : 'Adjuntar Foto / Video'"
                class="variable-chip"
                @click="triggerFileInput"
              >
                <q-tooltip>Adjunta una imagen (.jpg, .png), video (.mp4) o documento (.pdf)</q-tooltip>
              </q-btn>
              <q-btn
                outline
                dense
                no-caps
                size="sm"
                color="cyan-4"
                label="{{nombre}}"
                class="variable-chip"
                @click="insertVariable('{{nombre}}')"
              >
                <q-tooltip>Inserta el nombre del contacto</q-tooltip>
              </q-btn>
              <q-btn
                outline
                dense
                no-caps
                size="sm"
                color="teal-4"
                label="{{telefono}}"
                class="variable-chip"
                @click="insertVariable('{{telefono}}')"
              >
                <q-tooltip>Inserta el teléfono del contacto</q-tooltip>
              </q-btn>
            </div>
          </div>

          <!-- Chip de Archivo Adjunto Actual -->
          <div v-if="attachedFile" class="attached-file-pill row items-center justify-between q-pa-xs q-px-sm q-mb-xs">
            <div class="row items-center q-gutter-x-sm ellipsis">
              <q-icon
                :name="attachedFileType === 'video' ? 'sym_r_videocam' : attachedFileType === 'image' ? 'sym_r_image' : 'sym_r_description'"
                size="18px"
                :color="attachedFileType === 'video' ? 'amber-4' : 'teal-4'"
              />
              <span class="text-caption text-white ellipsis text-bold">{{ attachedFile.name }}</span>
              <span class="text-caption text-grey-4 font-mono">({{ formatFileSize(attachedFile.size) }})</span>
            </div>
            <q-btn flat round dense icon="sym_r_close" size="xs" color="grey-4" @click="clearAttachedFile" />
          </div>

          <q-input
            v-model="messageText"
            type="textarea"
            rows="4"
            outlined
            dark
            :placeholder="attachedFile ? 'Escribe una leyenda opcional para la imagen o video...' : 'Hola {{nombre}}, te saludamos de UNITEPC para brindarte información importante...'"
            class="bulk-message-dialog__input"
          />
        </div>

        <!-- Vista Previa de WhatsApp (Burbuja de Chat) -->
        <div class="preview-section">
          <div class="text-caption text-grey-4 text-weight-medium q-mb-xs row items-center q-gutter-x-xs">
            <q-icon name="sym_r_visibility" size="14px" color="teal-4" />
            <span>Vista previa de entrega (WhatsApp):</span>
          </div>

          <div class="whatsapp-bubble-wrapper">
            <div class="whatsapp-bubble">
              <div class="whatsapp-bubble__recipient text-caption text-grey-4 q-mb-xs">
                Para: <span class="text-teal-3 text-bold">{{ previewContactName }}</span>
                <span v-if="previewContactPhone" class="font-mono text-grey-5"> ({{ previewContactPhone }})</span>
              </div>

              <!-- Vista Previa de Imagen dentro de la burbuja -->
              <div v-if="attachedFilePreview && attachedFileType === 'image'" class="bubble-media-preview q-mb-xs">
                <img :src="attachedFilePreview" alt="Vista previa adjunto" class="bubble-preview-img" />
              </div>

              <!-- Vista Previa de Video dentro de la burbuja -->
              <div v-else-if="attachedFile && attachedFileType === 'video'" class="bubble-video-placeholder row items-center justify-center q-pa-md q-mb-xs">
                <q-icon name="sym_r_play_circle" size="38px" color="white" />
                <div class="text-caption text-white text-bold q-ml-sm ellipsis">Video: {{ attachedFile.name }}</div>
              </div>

              <!-- Vista Previa de Documento dentro de la burbuja -->
              <div v-else-if="attachedFile && attachedFileType === 'document'" class="bubble-doc-placeholder row items-center q-gutter-x-sm q-pa-sm q-mb-xs">
                <q-icon name="sym_r_description" size="22px" color="teal-3" />
                <span class="text-caption text-white ellipsis">{{ attachedFile.name }}</span>
              </div>

              <div class="whatsapp-bubble__text text-body2">
                {{ previewRenderedMessage }}
              </div>
              <div class="whatsapp-bubble__footer row items-center justify-end q-gutter-x-xs q-mt-xs">
                <span v-if="sendMode === 'scheduled' && scheduledAt" class="text-caption text-teal-3 q-mr-xs text-bold">
                  📅 {{ formatScheduledDate(scheduledAt) }}
                </span>
                <span class="whatsapp-bubble__time text-caption text-grey-5">{{ currentTime }}</span>
                <q-icon :name="sendMode === 'scheduled' ? 'sym_r_schedule' : 'sym_r_done_all'" size="14px" :color="sendMode === 'scheduled' ? 'teal-3' : 'cyan-3'" />
              </div>
            </div>
          </div>
        </div>

        <!-- Aviso Informativo de Seguridad -->
        <div class="disclaimer-box row items-center q-gutter-x-sm q-pa-sm">
          <q-icon name="sym_r_shield_locked" size="20px" color="teal-4" />
          <div class="col text-caption text-grey-4">
            {{ sendMode === 'scheduled' ? 'Los mensajes programados respetan los horarios establecidos y se despachan de forma secuencial sin bloquear la línea.' : 'Los envíos se despachan en segundo plano a través de colas asíncronas para respetar los límites de la línea y evitar bloqueos.' }}
          </div>
        </div>
      </q-card-section>

      <q-separator dark class="bulk-message-dialog__separator" />

      <!-- Acciones del Modal -->
      <q-card-actions align="right" class="q-pa-md q-gutter-x-sm">
        <q-btn flat dark no-caps label="Cancelar" v-close-popup :disable="isSending" />
        <q-btn
          unelevated
          no-caps
          color="positive"
          text-color="dark"
          :icon="sendMode === 'scheduled' ? 'sym_r_schedule_send' : 'sym_r_send'"
          :label="sendMode === 'scheduled' ? `Programar para ${contactsWithPhoneCount} contactos` : `Enviar a ${contactsWithPhoneCount} contactos`"
          :loading="isSending"
          :disable="!canSend"
          class="bulk-send-btn text-bold"
          @click="handleSubmit"
        />
      </q-card-actions>
    </q-card>
  </q-dialog>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useWhatsAppAccounts } from '@/modules/whatsapp/composables/useWhatsApp'
import { sendBulkMessage, scheduleBulkMessage } from '../api/contacts.api'
import { useAppNotify } from '@/shared/composables/useAppNotify'
import type { Contact } from '../types/contact.types'

const props = withDefaults(
  defineProps<{
    modelValue: boolean
    selectedContactIds: string[]
    contacts: Contact[]
    initialMode?: 'immediate' | 'scheduled'
  }>(),
  {
    initialMode: 'immediate',
  },
)

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  sent: [result: any]
}>()

const notify = useAppNotify()
const { data: whatsappAccountsQuery } = useWhatsAppAccounts()

const selectedAccountId = ref<string | null>(null)
const messageText = ref('')
const isSending = ref(false)

// Modalidad: inmediato o programado
const sendMode = ref<'immediate' | 'scheduled'>('immediate')
const scheduledAt = ref<string>('')

// Archivos adjuntos (fotos / videos / pdf)
const fileInputRef = ref<HTMLInputElement | null>(null)
const attachedFile = ref<File | null>(null)
const attachedFileType = ref<'image' | 'video' | 'document' | null>(null)
const attachedFilePreview = ref<string>('')

// Opciones de cuentas de WhatsApp disponibles
const whatsappAccountOptions = computed(() => {
  const accounts = (whatsappAccountsQuery.value as any[]) || []
  const opts = accounts.map((acc: any) => ({
    label: acc.name,
    value: acc.id,
    phone: acc.display_phone_number,
    connected: acc.status === 'CONNECTED',
  }))

  if (opts.length === 0) {
    return [
      {
        label: 'Línea de WhatsApp Predeterminada',
        value: null,
        phone: null,
        connected: true,
      },
    ]
  }

  return opts
})

// Auto-seleccionar la primera cuenta conectada
watch(
  whatsappAccountOptions,
  (opts) => {
    if (!selectedAccountId.value && opts.length > 0) {
      const connected = opts.find((o) => o.connected)
      selectedAccountId.value = connected ? connected.value : opts[0].value
    }
  },
  { immediate: true },
)

// Contactos seleccionados completos
const selectedContactsList = computed(() => {
  return props.contacts.filter((c) => props.selectedContactIds.includes(c.id))
})

const contactsWithPhoneCount = computed(() => {
  return selectedContactsList.value.filter((c) => Boolean(c.phone && c.phone.trim())).length
})

const contactsWithoutPhoneCount = computed(() => {
  return props.selectedContactIds.length - contactsWithPhoneCount.value
})

const canSend = computed(() => {
  if (props.selectedContactIds.length === 0 || isSending.value) return false
  const hasTextOrFile = messageText.value.trim().length > 0 || Boolean(attachedFile.value)
  if (sendMode.value === 'scheduled') {
    return hasTextOrFile && Boolean(scheduledAt.value)
  }
  return hasTextOrFile
})

// Muestra de contacto para la vista previa
const sampleContact = computed<Contact | null>(() => {
  return (
    selectedContactsList.value.find((c) => Boolean(c.phone)) ||
    selectedContactsList.value[0] ||
    null
  )
})

const previewContactName = computed(() => {
  if (!sampleContact.value) return 'Carlos Rodríguez'
  return sampleContact.value.name || `${sampleContact.value.first_name || ''} ${sampleContact.value.last_name || ''}`.trim() || 'Contacto'
})

const previewContactPhone = computed(() => {
  return sampleContact.value?.phone || '+591 70012345'
})

const previewRenderedMessage = computed(() => {
  if (!messageText.value.trim()) {
    return attachedFile.value ? 'Sin texto complementario.' : 'Escribe un mensaje para ver cómo lo recibirán tus contactos...'
  }
  return messageText.value
    .replace(/\{\{nombre\}\}/g, previewContactName.value)
    .replace(/\{\{telefono\}\}/g, previewContactPhone.value)
})

const currentTime = computed(() => {
  const now = new Date()
  return now.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' })
})

function insertVariable(variable: string) {
  messageText.value += (messageText.value ? ' ' : '') + variable + ' '
}

// Helpers de Programación de Fecha y Hora
function enableScheduledMode() {
  sendMode.value = 'scheduled'
  if (!scheduledAt.value) {
    setQuickSchedule(1)
  }
}

function setQuickSchedule(hoursAhead: number) {
  const d = new Date()
  d.setHours(d.getHours() + hoursAhead)
  d.setMinutes(0, 0, 0)
  scheduledAt.value = formatToLocalDateTimeInput(d)
}

function setQuickScheduleTomorrow(hour: number) {
  const d = new Date()
  d.setDate(d.getDate() + 1)
  d.setHours(hour, 0, 0, 0)
  scheduledAt.value = formatToLocalDateTimeInput(d)
}

function formatToLocalDateTimeInput(date: Date): string {
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`
}

function formatScheduledDate(dateStr: string): string {
  if (!dateStr) return ''
  try {
    const d = new Date(dateStr)
    return d.toLocaleDateString('es-BO', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' })
  } catch {
    return dateStr
  }
}

// Manejo de Archivos Adjuntos
function triggerFileInput() {
  fileInputRef.value?.click()
}

function onFileSelected(event: Event) {
  const target = event.target as HTMLInputElement
  if (!target.files || target.files.length === 0) return
  const file = target.files[0]
  setAttachedFile(file)
}

function setAttachedFile(file: File) {
  if (attachedFilePreview.value) {
    URL.revokeObjectURL(attachedFilePreview.value)
  }
  attachedFile.value = file
  if (file.type.startsWith('image/')) {
    attachedFileType.value = 'image'
    attachedFilePreview.value = URL.createObjectURL(file)
  } else if (file.type.startsWith('video/')) {
    attachedFileType.value = 'video'
    attachedFilePreview.value = ''
  } else {
    attachedFileType.value = 'document'
    attachedFilePreview.value = ''
  }
}

function clearAttachedFile() {
  if (attachedFilePreview.value) {
    URL.revokeObjectURL(attachedFilePreview.value)
  }
  attachedFile.value = null
  attachedFileType.value = null
  attachedFilePreview.value = ''
  if (fileInputRef.value) {
    fileInputRef.value.value = ''
  }
}

function formatFileSize(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

async function handleSubmit() {
  if (!canSend.value) return

  isSending.value = true
  try {
    if (sendMode.value === 'scheduled') {
      const scheduledIso = scheduledAt.value
        ? new Date(scheduledAt.value).toISOString()
        : scheduledAt.value

      const res = await scheduleBulkMessage({
        contact_ids: props.selectedContactIds,
        whatsapp_account_id: selectedAccountId.value,
        body: messageText.value.trim(),
        scheduled_at: scheduledIso,
        file: attachedFile.value,
      })

      notify.success({
        message: res.message || `Se programaron ${res.data.scheduled_count} mensajes con éxito.`,
      })

      emit('sent', res.data)
      emit('update:modelValue', false)
      resetForm()
    } else {
      // Envío inmediato
      const res = await sendBulkMessage({
        contact_ids: props.selectedContactIds,
        whatsapp_account_id: selectedAccountId.value,
        message: messageText.value.trim(),
        file: attachedFile.value,
      })

      notify.success({
        message: res.message || `Se enviaron ${res.data.dispatched_count} mensajes correctamente.`,
      })

      emit('sent', res.data)
      emit('update:modelValue', false)
      resetForm()
    }
  } catch (err: any) {
    const msg = err.response?.data?.message || 'Error al procesar los mensajes.'
    notify.error({ message: msg })
  } finally {
    isSending.value = false
  }
}

watch(
  () => props.modelValue,
  (isOpen) => {
    if (isOpen) {
      sendMode.value = props.initialMode || 'immediate'
      if (sendMode.value === 'scheduled' && !scheduledAt.value) {
        setQuickSchedule(1)
      }
    }
  },
)

function resetForm() {
  messageText.value = ''
  clearAttachedFile()
  sendMode.value = props.initialMode || 'immediate'
  scheduledAt.value = ''
}
</script>

<style scoped lang="scss">
.bulk-message-dialog {
  background: var(--crm-bg-card, #111827);
  border-radius: 16px;
  overflow: hidden;
  border: 1px solid rgba(255, 255, 255, 0.08);
}

.bulk-message-dialog__header {
  background: linear-gradient(135deg, rgba(16, 185, 129, 0.12) 0%, rgba(6, 182, 212, 0.05) 100%);
}

.bulk-message-dialog__separator {
  background: rgba(255, 255, 255, 0.08);
}

.recipient-stats-card {
  background: rgba(255, 255, 255, 0.03);
  border: 1px solid rgba(255, 255, 255, 0.06);
  border-radius: 8px;
}

.mode-select-card {
  background: rgba(255, 255, 255, 0.02);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 8px;
  transition: all 0.2s ease;

  &:hover {
    background: rgba(255, 255, 255, 0.05);
  }

  &--active {
    background: rgba(16, 185, 129, 0.12) !important;
    border-color: #10b981 !important;
  }
}

.scheduled-box {
  background: rgba(16, 185, 129, 0.06);
  border: 1px solid rgba(16, 185, 129, 0.2);
  border-radius: 8px;
}

.attached-file-pill {
  background: rgba(16, 185, 129, 0.1);
  border: 1px solid rgba(16, 185, 129, 0.25);
  border-radius: 6px;
}

.status-indicator {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  display: inline-block;

  &--connected {
    background-color: #22c55e;
    box-shadow: 0 0 6px rgba(34, 197, 94, 0.6);
  }

  &--disconnected {
    background-color: #94a3b8;
  }
}

.variable-chip {
  border-radius: 6px;
  font-family: monospace;
  font-size: 0.75rem;
}

.bulk-message-dialog__input {
  :deep(.q-field__control) {
    background: rgba(255, 255, 255, 0.02);
  }
}

.whatsapp-bubble-wrapper {
  background: #0b141a;
  padding: 12px;
  border-radius: 10px;
  border: 1px solid rgba(255, 255, 255, 0.05);
}

.whatsapp-bubble {
  background: #005c4b;
  color: #e9edef;
  border-radius: 8px 8px 2px 8px;
  padding: 8px 12px;
  max-width: 88%;
  margin-left: auto;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.4);

  &__recipient {
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    padding-bottom: 4px;
  }

  &__text {
    white-space: pre-wrap;
    word-break: break-word;
    font-size: 0.88rem;
    line-height: 1.4;
  }

  &__footer {
    opacity: 0.85;
  }

  &__time {
    font-size: 0.7rem;
  }
}

.bubble-media-preview {
  border-radius: 6px;
  overflow: hidden;
  max-height: 180px;
}

.bubble-preview-img {
  width: 100%;
  height: 100%;
  max-height: 180px;
  object-fit: cover;
  border-radius: 6px;
}

.bubble-video-placeholder {
  background: rgba(0, 0, 0, 0.35);
  border-radius: 6px;
  border: 1px dashed rgba(255, 255, 255, 0.2);
}

.bubble-doc-placeholder {
  background: rgba(0, 0, 0, 0.25);
  border-radius: 6px;
}

.disclaimer-box {
  background: rgba(6, 182, 212, 0.06);
  border-left: 3px solid #06b6d4;
  border-radius: 4px;
}

.bulk-send-btn {
  background: #25d366 !important;
  color: #0b141a !important;
  border-radius: 8px;
  box-shadow: 0 4px 12px rgba(37, 211, 102, 0.3);
}
</style>
