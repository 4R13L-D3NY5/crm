<template>
  <q-dialog
    :model-value="modelValue"
    persistent
    @update:model-value="emit('update:modelValue', $event)"
  >
    <q-card style="width: 680px; max-width: 95vw" class="bulk-message-dialog">
      <!-- Encabezado con Gradiente WhatsApp -->
      <q-card-section class="bulk-message-dialog__header row items-center justify-between q-py-md">
        <div class="row items-center q-gutter-x-md">
          <q-avatar size="44px" color="teal-9" text-color="teal-2" class="shadow-2">
            <q-icon name="sym_r_forward_to_inbox" size="26px" />
          </q-avatar>
          <div>
            <div class="row items-center q-gutter-x-sm">
              <span class="text-h6 text-bold text-white">Envío Masivo de Mensajes</span>
              <q-badge color="positive" text-color="dark" class="text-bold q-px-sm">
                WhatsApp Direct
              </q-badge>
            </div>
            <div class="text-caption text-grey-4">
              Difusión personalizada a los contactos seleccionados
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

        <!-- Editor de Mensaje -->
        <div>
          <div class="row items-center justify-between q-mb-xs">
            <span class="text-caption text-grey-4 text-weight-medium">Mensaje a enviar:</span>
            <!-- Chips de Variables Dinámicas -->
            <div class="row items-center q-gutter-x-xs">
              <span class="text-caption text-grey-5 q-mr-xs">Variables:</span>
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

          <q-input
            v-model="messageText"
            type="textarea"
            rows="5"
            outlined
            dark
            placeholder="Hola {{nombre}}, te saludamos de UNITEPC para brindarte información importante..."
            class="bulk-message-dialog__input"
          />
        </div>

        <!-- Vista Previa de WhatsApp (Burbuja de Chat) -->
        <div class="preview-section">
          <div class="text-caption text-grey-4 text-weight-medium q-mb-xs row items-center q-gutter-x-xs">
            <q-icon name="sym_r_visibility" size="14px" color="teal-4" />
            <span>Vista previa del mensaje (Ejemplo con contacto real):</span>
          </div>

          <div class="whatsapp-bubble-wrapper">
            <div class="whatsapp-bubble">
              <div class="whatsapp-bubble__recipient text-caption text-grey-4 q-mb-xs">
                Para: <span class="text-teal-3 text-bold">{{ previewContactName }}</span>
                <span v-if="previewContactPhone" class="font-mono text-grey-5"> ({{ previewContactPhone }})</span>
              </div>
              <div class="whatsapp-bubble__text text-body2">
                {{ previewRenderedMessage }}
              </div>
              <div class="whatsapp-bubble__footer row items-center justify-end q-gutter-x-xs q-mt-xs">
                <span class="whatsapp-bubble__time text-caption text-grey-5">{{ currentTime }}</span>
                <q-icon name="sym_r_done_all" size="14px" color="cyan-3" />
              </div>
            </div>
          </div>
        </div>

        <!-- Aviso Informativo de Seguridad -->
        <div class="disclaimer-box row items-center q-gutter-x-sm q-pa-sm">
          <q-icon name="sym_r_shield_locked" size="20px" color="teal-4" />
          <div class="col text-caption text-grey-4">
            Los envíos se despachan en segundo plano a través de colas asíncronas para respetar los límites de la línea y evitar bloqueos.
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
          icon="sym_r_send"
          :label="`Enviar a ${contactsWithPhoneCount} contactos`"
          :loading="isSending"
          :disable="!canSend"
          class="bulk-send-btn text-bold"
          @click="handleSendBulk"
        />
      </q-card-actions>
    </q-card>
  </q-dialog>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useWhatsAppAccounts } from '@/modules/whatsapp/composables/useWhatsApp'
import { sendBulkMessage } from '../api/contacts.api'
import { useAppNotify } from '@/shared/composables/useAppNotify'
import type { Contact } from '../types/contact.types'

const props = defineProps<{
  modelValue: boolean
  selectedContactIds: string[]
  contacts: Contact[]
}>()

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  sent: [result: { dispatched_count: number; skipped_count: number; total: number }]
}>()

const notify = useAppNotify()
const { data: whatsappAccountsQuery } = useWhatsAppAccounts()

const selectedAccountId = ref<string | null>(null)
const messageText = ref('')
const isSending = ref(false)

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
  return props.selectedContactIds.length > 0 && messageText.value.trim().length > 0 && !isSending.value
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
    return 'Escribe un mensaje para ver cómo lo recibirán tus contactos...'
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

async function handleSendBulk() {
  if (!canSend.value) return

  isSending.value = true
  try {
    const res = await sendBulkMessage({
      contact_ids: props.selectedContactIds,
      whatsapp_account_id: selectedAccountId.value,
      message: messageText.value.trim(),
    })

    notify.success({
      message: res.message || `Se enviaron ${res.data.dispatched_count} mensajes correctamente.`,
    })

    emit('sent', res.data)
    emit('update:modelValue', false)
    messageText.value = ''
  } catch (err: any) {
    const msg = err.response?.data?.message || 'Error al procesar el envío masivo.'
    notify.error({ message: msg })
  } finally {
    isSending.value = false
  }
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
  max-width: 85%;
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
    opacity: 0.8;
  }

  &__time {
    font-size: 0.7rem;
  }
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
