<template>
  <AppDialog
    :model-value="modelValue"
    title="Iniciar Nueva Conversación"
    eyebrow="Bandeja Multicanal"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <q-banner
      v-if="readOnly"
      rounded
      class="conversation-form__banner bg-blue-1 text-primary q-mb-md"
    >
      Este formulario está en modo solo lectura.
    </q-banner>

    <div class="conversation-form q-gutter-y-md">
      <!-- 1. Línea / Canal de salida (OBLIGATORIO) -->
      <div>
        <label class="field-label block q-mb-xs">
          Línea / Canal de salida <span class="text-negative text-weight-bold">*</span>
        </label>
        <q-select
          v-model="selectedChannelOption"
          :options="channelOptions"
          outlined
          dense
          dark
          :disable="readOnly"
          :loading="whatsappAccountsQuery.isLoading.value"
        >
          <template #prepend>
            <q-icon
              :name="selectedChannelOption?.value === 'manual' ? 'sym_r_edit_note' : 'sym_r_chat'"
              size="18px"
              :color="selectedChannelOption?.isConnected ? 'teal-4' : 'grey-5'"
            />
          </template>

          <template #selected-item="scope">
            <div class="row items-center q-gutter-x-sm no-wrap ellipsis text-body2">
              <span
                class="status-indicator-dot"
                :class="scope.opt.isConnected ? 'bg-positive' : 'bg-grey-6'"
              ></span>
              <span class="text-white text-weight-medium ellipsis">{{ scope.opt.label }}</span>
              <q-badge
                v-if="scope.opt.sessionBadge"
                :color="scope.opt.sessionBadgeColor"
                text-color="white"
                size="xs"
                class="q-ml-xs"
              >
                {{ scope.opt.sessionBadge }}
              </q-badge>
            </div>
          </template>

          <template #option="{ itemProps, opt }">
            <q-item v-bind="itemProps" dense dark class="channel-option-item">
              <q-item-section avatar style="min-width: 28px;">
                <span
                  class="status-indicator-dot"
                  :class="opt.isConnected ? 'bg-positive' : 'bg-grey-6'"
                ></span>
              </q-item-section>
              <q-item-section>
                <q-item-label class="text-white text-weight-medium">{{ opt.label }}</q-item-label>
                <q-item-label caption class="text-grey-4">{{ opt.sublabel }}</q-item-label>
              </q-item-section>
              <q-item-section side>
                <q-badge
                  v-if="opt.sessionBadge"
                  :color="opt.sessionBadgeColor"
                  text-color="white"
                  class="text-caption"
                >
                  {{ opt.sessionBadge }}
                </q-badge>
              </q-item-section>
            </q-item>
          </template>
        </q-select>
      </div>

      <!-- 2. Destinatario (OBLIGATORIO): Modo Nuevo Número vs Contacto Existente -->
      <div>
        <div class="row items-center justify-between q-mb-xs">
          <label class="field-label block">
            Destinatario <span class="text-negative text-weight-bold">*</span>
          </label>
          <q-btn-toggle
            v-model="targetMode"
            dense
            rounded
            no-caps
            unelevated
            toggle-color="teal-8"
            toggle-text-color="white"
            color="dark"
            text-color="grey-4"
            size="sm"
            :options="[
              { label: 'Ingresar Número', value: 'phone', icon: 'sym_r_add_call' },
              { label: 'Contacto Registrado', value: 'contact', icon: 'sym_r_contacts' },
            ]"
          />
        </div>

        <!-- Modo A: Ingreso directo de teléfono -->
        <div v-if="targetMode === 'phone'" class="q-gutter-y-sm">
          <q-input
            v-model="directPhone"
            outlined
            dense
            dark
            placeholder="Ej: +591 63921086 (con código de país)"
            :disable="readOnly"
            :rules="[val => !!val?.trim() || 'El número de teléfono es obligatorio']"
            hide-bottom-space
          >
            <template #prepend>
              <q-icon name="sym_r_call" size="18px" color="teal-4" />
            </template>
            <template v-if="directPhone" #append>
              <q-icon
                name="sym_r_close"
                size="16px"
                class="cursor-pointer text-grey-5"
                @click="directPhone = ''"
              />
            </template>
          </q-input>

          <q-input
            v-model="contactName"
            outlined
            dense
            dark
            placeholder="Nombre del contacto (opcional, ej: Juan Pérez)"
            :disable="readOnly"
          >
            <template #prepend>
              <q-icon name="sym_r_person" size="18px" class="text-grey-5" />
            </template>
          </q-input>
          <p class="text-caption text-grey-5 q-mb-none" style="font-size: 0.72rem;">
            * El número se registrará automáticamente en tu agenda de contactos.
          </p>
        </div>

        <!-- Modo B: Búsqueda de Contacto Existente -->
        <div v-else>
          <q-select
            v-model="form.contact_id"
            :options="filteredContactOptions"
            emit-value
            map-options
            outlined
            dense
            dark
            use-input
            clearable
            placeholder="Buscar contacto por nombre o teléfono..."
            :disable="readOnly"
            :rules="[val => !!val || 'Debes seleccionar un contacto']"
            hide-bottom-space
            @filter="filterContacts"
          >
            <template #prepend>
              <q-icon name="sym_r_person_search" size="18px" color="teal-4" />
            </template>
            <template #no-option>
              <q-item dark dense>
                <q-item-section class="text-grey-5">
                  No se encontraron contactos coincidentes
                </q-item-section>
              </q-item>
            </template>
          </q-select>
        </div>
      </div>

      <!-- 3. Mensaje inicial (OBLIGATORIO) -->
      <div>
        <label class="field-label block q-mb-xs">
          Mensaje inicial <span class="text-negative text-weight-bold">*</span>
        </label>
        <q-input
          v-model="form.message"
          type="textarea"
          outlined
          dense
          dark
          autogrow
          rows="3"
          placeholder="Escribe aquí el mensaje que se enviará al cliente por WhatsApp..."
          :disable="readOnly"
          :rules="[val => !!val?.trim() || 'El mensaje es obligatorio']"
          hide-bottom-space
        >
          <template #prepend>
            <q-icon name="sym_r_send" size="18px" color="teal-4" />
          </template>
        </q-input>
      </div>

      <!-- 4. Opciones Avanzadas (Colapsable y 100% Opcional) -->
      <q-expansion-item
        v-model="showAdvanced"
        dense
        dark
        header-class="advanced-options-header text-caption text-grey-4"
        expand-icon="sym_r_expand_more"
        icon="sym_r_tune"
        label="Opciones adicionales (Opcionales)"
        caption="Asunto, Empresa, Operador asignado"
      >
        <div class="q-pt-sm q-gutter-y-sm">
          <q-input
            v-model="form.subject"
            label="Asunto (opcional)"
            outlined
            dense
            dark
            placeholder="Ej: Consulta comercial"
            :disable="readOnly"
          />

          <div class="row q-col-gutter-sm">
            <div class="col-12 col-sm-6">
              <q-select
                v-model="form.company_id"
                :options="companyOptions"
                emit-value
                map-options
                label="Empresa (opcional)"
                outlined
                dense
                dark
                clearable
                :disable="readOnly"
              />
            </div>
            <div class="col-12 col-sm-6">
              <q-select
                v-model="form.assigned_to_user_id"
                :options="userOptions"
                emit-value
                map-options
                label="Asignar a (opcional)"
                outlined
                dense
                dark
                clearable
                :disable="readOnly"
              />
            </div>
          </div>
        </div>
      </q-expansion-item>
    </div>

    <template #actions>
      <q-btn
        flat
        no-caps
        label="Cancelar"
        color="grey-4"
        @click="emit('update:modelValue', false)"
      />
      <q-btn
        v-if="!readOnly"
        color="teal-7"
        no-caps
        unelevated
        icon="sym_r_send"
        label="Enviar Mensaje e Iniciar"
        :loading="loading"
        :disable="!canSubmit"
        class="text-weight-bold"
        @click="handleSubmit"
      />
    </template>
  </AppDialog>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import type { Company } from '@/modules/companies/types/company.types'
import type { Contact } from '@/modules/contacts/types/contact.types'
import AppDialog from '@/shared/components/AppDialog.vue'
import { useWhatsAppAccounts } from '@/modules/whatsapp/composables/useWhatsApp'
import type { CreateConversationPayload, UserOption } from '../types/conversation.types'

interface ChannelSelectOption {
  label: string
  sublabel: string
  value: string
  accountId?: string
  sessionBadge?: string
  sessionBadgeColor?: string
  isConnected: boolean
}

const props = defineProps<{
  modelValue: boolean
  contacts: Contact[]
  companies: Company[]
  users: UserOption[]
  loading?: boolean
  readOnly?: boolean
}>()

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  submit: [payload: CreateConversationPayload]
}>()

const whatsappAccountsQuery = useWhatsAppAccounts()

// Modo de destinatario: 'phone' (escribir número directo) o 'contact' (elegir de lista)
const targetMode = ref<'phone' | 'contact'>('phone')
const directPhone = ref('')
const contactName = ref('')
const showAdvanced = ref(false)

const form = reactive<{
  subject: string
  status: 'open' | 'pending' | 'resolved'
  contact_id: string | null
  company_id: string | null
  message: string
  assigned_to_user_id: string | null
}>({
  subject: '',
  status: 'open',
  contact_id: null,
  company_id: null,
  message: '',
  assigned_to_user_id: null,
})

// Opciones de canal / línea de WhatsApp dinámicas
const channelOptions = computed<ChannelSelectOption[]>(() => {
  const accounts = whatsappAccountsQuery.data.value ?? []
  const opts: ChannelSelectOption[] = []

  if (accounts.length > 0) {
    accounts.forEach((acc) => {
      const isCloud = acc.session_type === 'meta_cloud'
      
      let badge = 'WhatsApp QR'
      let badgeColor = 'teal-9'
      let channelVal = 'whatsapp'

      if (isCloud) {
        badge = 'WhatsApp Cloud API'
        badgeColor = 'cyan-9'
        channelVal = 'whatsapp'
      } else if (acc.session_type === 'facebook') {
        badge = 'Facebook Messenger'
        badgeColor = 'blue-9'
        channelVal = 'facebook'
      } else if (acc.session_type === 'instagram') {
        badge = 'Instagram Direct'
        badgeColor = 'purple-9'
        channelVal = 'instagram'
      } else if (acc.session_type === 'tiktok') {
        badge = 'TikTok Business'
        badgeColor = 'grey-9'
        channelVal = 'tiktok'
      }

      opts.push({
        label: acc.name,
        sublabel: acc.display_phone_number || (isCloud ? 'Meta Cloud API' : 'Línea de atención'),
        value: channelVal,
        accountId: acc.id,
        sessionBadge: badge,
        sessionBadgeColor: badgeColor,
        isConnected: acc.status === 'CONNECTED',
      })
    })
  }

  // Opción de respaldo
  if (opts.length === 0) {
    opts.push({
      label: 'WhatsApp (Línea predeterminada)',
      sublabel: 'Envío automático por canal activo',
      value: 'whatsapp',
      sessionBadge: 'WhatsApp',
      sessionBadgeColor: 'teal-9',
      isConnected: true,
    })
  }

  // Opción manual interna
  opts.push({
    label: 'Registro Manual / Nota Interna',
    sublabel: 'No envía mensaje a ningún proveedor',
    value: 'manual',
    sessionBadge: 'Interno',
    sessionBadgeColor: 'grey-8',
    isConnected: true,
  })

  return opts
})

const selectedChannelOption = ref<ChannelSelectOption | null>(null)

// Auto-seleccionar la primera línea conectada
watch(channelOptions, (opts) => {
  if (!selectedChannelOption.value && opts.length > 0) {
    const connected = opts.find(o => o.isConnected && o.value === 'whatsapp')
    selectedChannelOption.value = connected || opts[0]
  }
}, { immediate: true })

// Reset form al abrir modal
watch(() => props.modelValue, (isOpen) => {
  if (isOpen) {
    directPhone.value = ''
    contactName.value = ''
    form.message = ''
    form.subject = ''
    form.status = 'open'
    form.contact_id = null
    form.company_id = null
    form.assigned_to_user_id = null
    if (!selectedChannelOption.value && channelOptions.value.length > 0) {
      const connected = channelOptions.value.find(o => o.isConnected && o.value === 'whatsapp')
      selectedChannelOption.value = connected || channelOptions.value[0]
    }
  }
})

// Filtro de contactos existentes
const contactSearch = ref('')
function filterContacts(val: string, update: (fn: () => void) => void) {
  update(() => {
    contactSearch.value = val.toLowerCase().trim()
  })
}

const filteredContactOptions = computed(() => {
  const needle = contactSearch.value
  const list = props.contacts.map((c) => ({
    label: `${c.name}${c.phone ? ' (' + c.phone + ')' : ''}`,
    value: c.id,
    phone: c.phone,
  }))
  if (!needle) return list.slice(0, 50)
  return list.filter(item => item.label.toLowerCase().includes(needle)).slice(0, 50)
})

const companyOptions = computed(() =>
  props.companies.map((company) => ({
    label: company.name,
    value: company.id,
  })),
)

const userOptions = computed(() =>
  props.users.map((user) => ({
    label: user.name,
    value: user.id,
  })),
)

const canSubmit = computed(() => {
  if (!selectedChannelOption.value) return false
  if (!form.message.trim()) return false
  if (targetMode.value === 'phone') {
    return !!directPhone.value.trim()
  } else {
    return !!form.contact_id
  }
})

function handleSubmit() {
  if (!canSubmit.value) return

  const payload: CreateConversationPayload = {
    channel: (selectedChannelOption.value?.value || 'whatsapp') as any,
    whatsapp_account_id: selectedChannelOption.value?.accountId || null,
    contact_id: targetMode.value === 'contact' ? form.contact_id : null,
    phone: targetMode.value === 'phone' ? directPhone.value.trim() : null,
    contact_name: targetMode.value === 'phone' && contactName.value.trim() ? contactName.value.trim() : null,
    message: form.message.trim(),
    subject: form.subject?.trim() || null,
    status: form.status,
    company_id: form.company_id || null,
    assigned_to_user_id: form.assigned_to_user_id || null,
  }

  emit('submit', payload)
}
</script>

<style scoped lang="scss">
.conversation-form {
  padding: 4px 0;
}

.field-label {
  font-size: 0.82rem;
  color: var(--crm-color-text-secondary, #cbd5e1);
  font-weight: 500;
}

.status-indicator-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  display: inline-block;
  flex-shrink: 0;
}

.channel-option-item {
  border-bottom: 1px solid rgba(255, 255, 255, 0.05);
  &:hover {
    background: rgba(255, 255, 255, 0.04);
  }
}

.advanced-options-header {
  border: 1px dashed rgba(255, 255, 255, 0.1);
  border-radius: 8px;
  background: rgba(255, 255, 255, 0.02);
}
</style>
