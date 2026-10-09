<template>
  <q-dialog
    :model-value="modelValue"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <q-card dark class="ticket-quick-info-card shadow-24" style="min-width: 440px; max-width: 520px; border-radius: 14px;">
      <!-- Barra Superior con Canal de Origen -->
      <q-card-section class="q-pb-none">
        <div class="row items-center justify-between no-wrap">
          <div class="row items-center q-gutter-x-sm">
            <SocialChannelBadge
              :channel="conversation?.channel"
              :account-name="conversation?.channel_account?.name"
              size="md"
            />
            <span class="text-caption text-grey-4 font-mono">
              #{{ conversation?.id?.slice(-6) }}
            </span>
          </div>

          <q-btn
            flat
            round
            dense
            icon="sym_r_close"
            color="grey-4"
            v-close-popup
          />
        </div>

        <div class="text-h6 text-weight-bold text-white q-mt-sm">
          Información del Chat & Procedencia
        </div>
        <div class="text-caption text-grey-4">
          Detalles de recepción y contacto sin necesidad de ingresar al chat.
        </div>
      </q-card-section>

      <q-separator dark class="q-my-md" />

      <!-- Cuerpo Principal del Detalle -->
      <q-card-section class="q-py-none q-gutter-y-md">
        <!-- 1. Tarjeta de Origen / De dónde llega el mensaje -->
        <div class="ticket-info-block origin-block">
          <div class="text-caption text-weight-bold text-grey-3 q-mb-xs flex items-center q-gutter-x-xs">
            <q-icon name="sym_r_hub" size="16px" color="teal-4" />
            <span>ORIGEN DEL MENSAJE & RED SOCIAL</span>
          </div>

          <div class="row items-center justify-between q-mt-xs">
            <div>
              <div class="text-body2 text-weight-medium text-white">
                {{ getChannelTitle(conversation?.channel) }}
              </div>
              <div class="text-caption text-grey-4 q-mt-2">
                <span v-if="conversation?.channel_account?.name">
                  Conexión: <strong>{{ conversation.channel_account.name }}</strong>
                </span>
                <span v-else-if="conversation?.channel_account?.display_phone_number">
                  Línea: <strong>{{ conversation.channel_account.display_phone_number }}</strong>
                </span>
                <span v-else>
                  Canal directo integrado
                </span>
              </div>
            </div>

            <!-- Icono Grande de la Red Social -->
            <div class="social-large-icon-wrap" :class="`social-large-icon-wrap--${conversation?.channel || 'whatsapp'}`">
              <SocialChannelBadge
                :channel="conversation?.channel"
                :show-label="false"
                size="md"
              />
            </div>
          </div>
        </div>

        <!-- 2. Información del Contacto -->
        <div class="ticket-info-block">
          <div class="text-caption text-weight-bold text-grey-3 q-mb-sm flex items-center q-gutter-x-xs">
            <q-icon name="sym_r_person" size="16px" color="teal-4" />
            <span>DATOS DEL CONTACTO</span>
          </div>

          <div class="row items-center q-gutter-x-md q-mb-sm">
            <q-avatar size="44px" color="teal-9" text-color="teal-2" class="text-weight-bold">
              {{ getInitials(conversation?.contact?.name || conversation?.subject || 'C') }}
            </q-avatar>

            <div class="col ellipsis">
              <div class="text-subtitle2 text-weight-bold text-white ellipsis">
                {{ conversation?.contact?.name || conversation?.subject || 'Contacto sin nombre' }}
              </div>
              <div v-if="conversation?.company?.name" class="text-caption text-grey-4 ellipsis">
                🏢 {{ conversation.company.name }}
              </div>
            </div>
          </div>

          <div class="q-gutter-y-xs text-caption">
            <div v-if="conversation?.contact?.phone" class="row items-center justify-between bg-dark-subtle q-px-sm q-py-xs rounded-borders">
              <span class="text-grey-4">Teléfono / WhatsApp:</span>
              <div class="row items-center q-gutter-x-xs">
                <span class="font-mono text-weight-medium text-grey-2">{{ conversation.contact.phone }}</span>
                <q-btn
                  flat
                  round
                  dense
                  size="xs"
                  icon="sym_r_content_copy"
                  color="grey-4"
                  @click="copyText(conversation.contact.phone)"
                >
                  <q-tooltip>Copiar número</q-tooltip>
                </q-btn>
              </div>
            </div>

            <div v-if="conversation?.contact?.email" class="row items-center justify-between bg-dark-subtle q-px-sm q-py-xs rounded-borders">
              <span class="text-grey-4">Correo:</span>
              <span class="text-grey-2">{{ conversation.contact.email }}</span>
            </div>
          </div>
        </div>

        <!-- 3. Clasificación & Estado del Ticket -->
        <div class="ticket-info-block">
          <div class="text-caption text-weight-bold text-grey-3 q-mb-xs flex items-center q-gutter-x-xs">
            <q-icon name="sym_r_tune" size="16px" color="teal-4" />
            <span>ESTADO, ASIGNACIÓN & CATEGORÍAS</span>
          </div>

          <div class="row q-col-gutter-xs items-center q-mt-xs">
            <!-- Estado General -->
            <div class="col-auto">
              <q-badge
                :color="getStatusBadgeColor(conversation?.status)"
                class="q-px-sm q-py-xs text-capitalize font-weight-bold"
              >
                {{ formatStatus(conversation?.status) }}
              </q-badge>
            </div>

            <!-- Estado Personalizado (Custom Status) -->
            <div v-if="conversation?.custom_status" class="col-auto">
              <q-badge
                :style="{
                  backgroundColor: conversation.custom_status.color + '22',
                  color: conversation.custom_status.color,
                  border: '1px solid ' + conversation.custom_status.color,
                }"
                class="q-px-sm q-py-xs"
              >
                <q-icon :name="conversation.custom_status.icon || 'sym_r_flag'" size="12px" class="q-mr-xs" />
                {{ conversation.custom_status.name }}
              </q-badge>
            </div>

            <!-- Asignado a -->
            <div class="col-auto">
              <span v-if="conversation?.assignee?.name" class="ticket-assignee-tag">
                👤 Asignado: <strong>{{ conversation.assignee.name }}</strong>
              </span>
              <span v-else class="ticket-assignee-tag text-grey-5">
                👤 Sin asignar
              </span>
            </div>
          </div>

          <!-- Categorías Multicarrera -->
          <div v-if="conversation?.categories?.length" class="row q-gutter-xs q-mt-sm">
            <q-chip
              v-for="cat in conversation.categories"
              :key="cat.id"
              dense
              dark
              size="xs"
              :style="{
                backgroundColor: (cat.color || '#06b6d4') + '22',
                borderColor: cat.color || '#06b6d4',
                border: '1px solid',
              }"
            >
              <q-icon :name="cat.icon || 'sym_r_label'" size="11px" class="q-mr-xs" :style="{ color: cat.color || '#06b6d4' }" />
              {{ cat.code ? `[${cat.code}] ${cat.name}` : cat.name }}
            </q-chip>
          </div>

          <!-- Tags del Contacto -->
          <div v-if="conversation?.contact?.tags?.length" class="row q-gutter-xs q-mt-xs">
            <q-badge
              v-for="t in conversation.contact.tags"
              :key="t.id"
              color="grey-9"
              text-color="grey-3"
              class="text-caption"
            >
              #{{ t.name }}
            </q-badge>
          </div>
        </div>

        <!-- 4. Resumen del Último Mensaje -->
        <div v-if="conversation?.latest_message?.body" class="ticket-info-block message-snippet-block">
          <div class="row items-center justify-between text-caption text-grey-4 q-mb-xs">
            <span class="text-weight-bold text-grey-3 flex items-center q-gutter-x-xs">
              <q-icon name="sym_r_chat" size="14px" color="teal-4" />
              <span>ÚLTIMO MENSAJE</span>
            </span>
            <span>{{ formatDateTime(conversation?.last_message_at) }}</span>
          </div>

          <div class="text-caption text-grey-2 bg-dark q-pa-sm rounded-borders message-preview-box">
            "{{ conversation.latest_message.body }}"
          </div>
        </div>
      </q-card-section>

      <q-separator dark class="q-my-md" />

      <!-- Acciones del Modal -->
      <q-card-actions align="between" class="q-px-md q-pb-md">
        <q-btn
          flat
          dense
          no-caps
          color="grey-4"
          label="Cerrar"
          v-close-popup
        />

        <q-btn
          unelevated
          dense
          no-caps
          color="positive"
          icon="sym_r_forum"
          label="Entrar al Chat"
          class="q-px-md"
          @click="handleOpenChat"
        />
      </q-card-actions>
    </q-card>
  </q-dialog>
</template>

<script setup lang="ts">
import { useQuasar } from 'quasar'
import SocialChannelBadge from '@/shared/components/SocialChannelBadge.vue'
import type { Conversation } from '../types/conversation.types'

const props = defineProps<{
  modelValue: boolean
  conversation: Conversation | null
}>()

const emit = defineEmits<{
  'update:modelValue': [val: boolean]
  'open-chat': [conversation: Conversation]
}>()

const $q = useQuasar()

function getChannelTitle(channel?: string): string {
  switch (channel) {
    case 'whatsapp':
      return 'WhatsApp (Oficial / Baileys)'
    case 'instagram':
      return 'Instagram Direct (Meta DM)'
    case 'facebook':
      return 'Facebook Messenger'
    case 'tiktok':
      return 'TikTok Business Chat'
    case 'email':
      return 'Correo Electrónico'
    default:
      return 'Bandeja Multicanal'
  }
}

function getInitials(name: string): string {
  if (!name) return 'C'
  const parts = name.trim().split(' ')
  if (parts.length >= 2) {
    return (parts[0][0] + parts[1][0]).toUpperCase()
  }
  return name.slice(0, 2).toUpperCase()
}

function getStatusBadgeColor(status?: string): string {
  switch (status) {
    case 'open':
      return 'positive'
    case 'pending':
      return 'warning'
    case 'closed':
      return 'grey-7'
    default:
      return 'grey-6'
  }
}

function formatStatus(status?: string): string {
  switch (status) {
    case 'open':
      return 'Atendiendo'
    case 'pending':
      return 'En espera'
    case 'closed':
      return 'Cerrado'
    default:
      return status || 'Desconocido'
  }
}

function formatDateTime(dateStr?: string | null): string {
  if (!dateStr) return '-'
  try {
    const d = new Date(dateStr)
    return d.toLocaleString('es-ES', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    })
  } catch {
    return dateStr
  }
}

function copyText(val: string) {
  navigator.clipboard.writeText(val)
  $q.notify({
    message: 'Copiado al portapapeles',
    type: 'positive',
    timeout: 1500,
  })
}

function handleOpenChat() {
  if (props.conversation) {
    emit('open-chat', props.conversation)
  }
  emit('update:modelValue', false)
}
</script>

<style scoped lang="scss">
.ticket-quick-info-card {
  background: var(--crm-bg-sidebar, #111827);
  border: 1px solid var(--crm-color-border, #1f2937);
}

.ticket-info-block {
  background: rgba(255, 255, 255, 0.03);
  border: 1px solid rgba(255, 255, 255, 0.06);
  border-radius: 8px;
  padding: 10px 12px;
}

.origin-block {
  background: linear-gradient(135deg, rgba(16, 185, 129, 0.08) 0%, rgba(6, 182, 212, 0.04) 100%);
  border: 1px solid rgba(16, 185, 129, 0.2);
}

.social-large-icon-wrap {
  display: flex;
  align-items: center;
  justify-content: center;
}

.ticket-assignee-tag {
  font-size: 0.72rem;
  background: rgba(255, 255, 255, 0.05);
  padding: 3px 8px;
  border-radius: 4px;
  border: 1px solid rgba(255, 255, 255, 0.1);
  color: #d1d5db;
}

.bg-dark-subtle {
  background: rgba(0, 0, 0, 0.25);
}

.message-preview-box {
  border-left: 3px solid var(--crm-color-primary, #10b981);
  font-style: italic;
  line-height: 1.4;
}
</style>
