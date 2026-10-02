<template>
  <main class="inbox-chat-thread">
    <!-- Top Action Bar del Chat -->
    <header class="inbox-chat-header q-px-md q-py-sm">
      <div class="row items-center justify-between no-wrap">
        <!-- Info del Cliente -->
        <div class="row items-center q-gutter-x-sm ellipsis">
          <q-avatar size="36px" class="chat-header-avatar">
            {{ getInitials(conversation.contact?.name || conversation.subject || 'W') }}
          </q-avatar>

          <div class="ellipsis">
            <div class="row items-center q-gutter-x-xs no-wrap">
              <span class="text-subtitle2 text-weight-bold text-white ellipsis">
                {{ conversation.contact?.name || conversation.subject || 'Cliente WhatsApp' }}
              </span>
              <q-badge
                :color="statusBadgeColor"
                rounded
                class="q-px-xs text-caption"
              >
                {{ statusBadgeLabel }}
              </q-badge>
            </div>
            <div class="text-caption text-grey-4 ellipsis font-mono" style="font-size: 0.72rem">
              {{ conversation.contact?.phone || 'Canal WhatsApp' }}
            </div>
          </div>
        </div>

        <!-- Acciones del Ticket -->
        <div class="row items-center q-gutter-x-xs no-wrap">
          <q-btn
            v-if="conversation.status === 'pending'"
            unelevated
            dense
            size="sm"
            color="positive"
            icon="sym_r_check_circle"
            label="Aceptar"
            class="q-px-sm"
            :loading="isActionLoading"
            @click="emit('accept')"
          />

          <q-btn
            v-if="conversation.status !== 'closed'"
            flat
            dense
            size="sm"
            color="grey-4"
            icon="sym_r_swap_horiz"
            label="Transferir"
            class="q-px-sm"
            @click="emit('transfer')"
          />

          <q-btn
            v-if="conversation.status !== 'closed'"
            flat
            dense
            size="sm"
            color="negative"
            icon="sym_r_done_all"
            label="Resolver"
            class="q-px-sm"
            :loading="isActionLoading"
            @click="emit('close')"
          />

          <q-btn
            flat
            round
            dense
            icon="sym_r_dock_to_left"
            color="grey-4"
            size="sm"
            @click="emit('toggle-profile')"
          >
            <q-tooltip>Ficha del Cliente</q-tooltip>
          </q-btn>
        </div>
      </div>
    </header>

    <!-- Flujo de Mensajes (Chat Stream) -->
    <div ref="messagesContainer" class="inbox-messages-stream scroll q-pa-md">
      <div v-if="conversation.messages.length === 0" class="column items-center justify-center q-py-xl text-grey-5 text-center">
        <q-icon name="sym_r_chat" size="32px" class="q-mb-xs" />
        <div class="text-caption">Sin mensajes en este ticket. Sé el primero en escribir.</div>
      </div>

      <div
        v-for="msg in conversation.messages"
        :key="msg.id"
        class="message-row"
        :class="{
          'message-row--inbound': msg.direction === 'inbound',
          'message-row--outbound': msg.direction === 'outbound' && !msg.is_internal,
          'message-row--internal': msg.is_internal || msg.direction === 'internal',
        }"
      >
        <div class="message-bubble">
          <!-- Nota Interna Header -->
          <div v-if="msg.is_internal || msg.direction === 'internal'" class="row items-center q-gutter-x-xs text-caption text-amber-4 q-mb-xs">
            <q-icon name="sym_r_lock" size="13px" />
            <span class="text-weight-bold">Nota Interna (Sólo Equipo)</span>
            <span v-if="msg.user?.name" class="text-grey-4">• {{ msg.user.name }}</span>
          </div>

          <div class="message-body">{{ msg.body }}</div>

          <div class="message-footer row items-center justify-end q-gutter-x-xs q-mt-xs">
            <span class="message-time">{{ formatMessageTime(msg.sent_at || msg.created_at) }}</span>
            <q-icon
              v-if="msg.direction === 'outbound' && !msg.is_internal"
              name="sym_r_done_all"
              size="13px"
              color="teal-3"
            />
          </div>
        </div>
      </div>
    </div>

    <!-- Redactor de Respuestas (Composer) -->
    <footer class="inbox-composer q-pa-sm">
      <div class="composer-mode-bar row items-center justify-between q-mb-xs">
        <div class="row items-center q-gutter-x-xs">
          <button
            type="button"
            class="composer-mode-btn"
            :class="{ 'composer-mode-btn--active': composerMode === 'whatsapp' }"
            @click="composerMode = 'whatsapp'"
          >
            <q-icon name="sym_r_chat" size="14px" />
            <span>WhatsApp</span>
          </button>

          <button
            type="button"
            class="composer-mode-btn composer-mode-btn--note"
            :class="{ 'composer-mode-btn--active-note': composerMode === 'internal' }"
            @click="composerMode = 'internal'"
          >
            <q-icon name="sym_r_lock" size="14px" />
            <span>Nota Interna</span>
          </button>
        </div>

        <span class="text-caption text-grey-5" style="font-size: 0.7rem">
          Presiona <strong>Enter</strong> para enviar
        </span>
      </div>

      <div class="row items-end q-gutter-x-sm">
        <q-input
          v-model="composerText"
          type="textarea"
          autogrow
          dense
          outlined
          dark
          :rows="1"
          :placeholder="composerMode === 'whatsapp' ? 'Escribe una respuesta para el cliente...' : 'Escribe una nota interna para los supervisores...'"
          class="composer-textarea col"
          @keydown.enter.exact.prevent="handleSend"
        />

        <q-btn
          unelevated
          dense
          round
          :color="composerMode === 'whatsapp' ? 'primary' : 'amber-8'"
          icon="sym_r_send"
          :loading="isSending"
          :disable="!composerText.trim()"
          @click="handleSend"
        />
      </div>
    </footer>
  </main>
</template>

<script setup lang="ts">
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import type { Conversation } from '../types/conversation.types'

const props = defineProps<{
  conversation: Conversation
  isActionLoading?: boolean
  isSending?: boolean
}>()

const emit = defineEmits<{
  (e: 'accept'): void
  (e: 'transfer'): void
  (e: 'close'): void
  (e: 'toggle-profile'): void
  (e: 'send-message', payload: { body: string; is_internal: boolean }): void
}>()

const messagesContainer = ref<HTMLElement | null>(null)
const composerMode = ref<'whatsapp' | 'internal'>('whatsapp')
const composerText = ref('')

const statusBadgeColor = computed(() => {
  switch (props.conversation.status) {
    case 'open':
      return 'teal-9'
    case 'pending':
      return 'amber-10'
    case 'closed':
    default:
      return 'grey-8'
  }
})

const statusBadgeLabel = computed(() => {
  switch (props.conversation.status) {
    case 'open':
      return 'Abierto'
    case 'pending':
      return 'En espera'
    case 'closed':
    default:
      return 'Resuelto'
  }
})

function getInitials(name: string): string {
  if (!name) return 'WA'
  const parts = name.trim().split(' ')
  if (parts.length >= 2) return (parts[0][0] + parts[1][0]).toUpperCase()
  return name.slice(0, 2).toUpperCase()
}

function formatMessageTime(dateStr: string | null): string {
  if (!dateStr) return ''
  try {
    const d = new Date(dateStr)
    return d.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' })
  } catch {
    return ''
  }
}

function handleSend() {
  if (!composerText.value.trim()) return
  emit('send-message', {
    body: composerText.value.trim(),
    is_internal: composerMode.value === 'internal',
  })
  composerText.value = ''
}

function scrollToBottom() {
  nextTick(() => {
    if (messagesContainer.value) {
      messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight
    }
  })
}

watch(
  () => props.conversation.messages.length,
  () => scrollToBottom(),
)

onMounted(() => scrollToBottom())
</script>

<style scoped lang="scss">
.inbox-chat-thread {
  flex: 1;
  display: flex;
  flex-direction: column;
  height: 100%;
  background: var(--crm-bg-app);
}

.inbox-chat-header {
  height: 54px;
  background: var(--crm-bg-sidebar);
  border-bottom: 1px solid var(--crm-color-border);
}

.chat-header-avatar {
  background: var(--crm-color-surface-elevated);
  color: var(--crm-color-primary);
  font-size: 0.75rem;
  font-weight: 700;
  border: 1px solid var(--crm-color-border);
}

.inbox-messages-stream {
  flex: 1;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.message-row {
  display: flex;
  width: 100%;

  &--inbound {
    justify-content: flex-start;
    .message-bubble {
      background: var(--crm-bg-surface-elevated);
      color: var(--crm-color-ink);
      border-bottom-left-radius: 2px;
    }
  }

  &--outbound {
    justify-content: flex-end;
    .message-bubble {
      background: #065f46;
      color: #ffffff;
      border-bottom-right-radius: 2px;
    }
  }

  &--internal {
    justify-content: center;
    .message-bubble {
      background: rgba(245, 158, 11, 0.08);
      border: 1px solid rgba(245, 158, 11, 0.25);
      color: #fef3c7;
      max-width: 80%;
      border-radius: 8px;
    }
  }
}

.message-bubble {
  max-width: 65%;
  padding: 8px 12px;
  border-radius: 12px;
  border: 1px solid var(--crm-color-border);
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
}

.message-body {
  font-size: 0.85rem;
  line-height: 1.45;
  word-break: break-word;
  white-space: pre-wrap;
}

.message-time {
  font-size: 0.65rem;
  opacity: 0.7;
}

.inbox-composer {
  background: var(--crm-bg-sidebar);
  border-top: 1px solid var(--crm-color-border);
}

.composer-mode-bar {
  padding: 0 4px;
}

.composer-mode-btn {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 3px 8px;
  background: transparent;
  border: 1px solid transparent;
  border-radius: 6px;
  color: var(--crm-color-muted);
  font-size: 0.72rem;
  font-weight: 500;
  cursor: pointer;
  transition: all var(--crm-transition-fast);

  &--active {
    background: var(--crm-color-primary-soft);
    color: var(--crm-color-primary);
    border-color: rgba(16, 185, 129, 0.2);
  }

  &--active-note {
    background: rgba(245, 158, 11, 0.12);
    color: #f59e0b;
    border-color: rgba(245, 158, 11, 0.3);
  }
}

.composer-textarea {
  :deep(.q-field__control) {
    border-radius: 8px;
    font-size: 0.85rem;
  }
}
</style>
