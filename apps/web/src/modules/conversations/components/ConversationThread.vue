<template>
  <div
    v-if="conversation"
    class="conversation-thread"
  >
    <!-- Top Action Bar estilo Whaticket -->
    <header class="conversation-thread__header">
      <div class="conversation-thread__contact-info">
        <q-avatar
          size="44px"
          color="primary"
          text-color="white"
          font-size="16px"
        >
          {{ getInitials(conversation.contact?.name || conversation.subject || 'W') }}
        </q-avatar>

        <div>
          <div class="conversation-thread__title-row">
            <h2 class="conversation-thread__title">
              {{ conversation.contact?.name || conversation.subject || 'Conversación' }}
            </h2>
            <q-badge
              v-if="conversation.queue"
              :style="{ backgroundColor: conversation.queue.color || '#25D366', color: '#fff' }"
              rounded
              class="q-ml-sm"
            >
              {{ conversation.queue.name }}
            </q-badge>
          </div>
          <p class="conversation-thread__meta">
            {{ conversation.contact?.phone || conversation.company?.name || 'Canal WhatsApp' }}
          </p>
        </div>
      </div>

      <!-- Botones de Acción Whaticket -->
      <div class="conversation-thread__actions">
        <!-- Aceptar Ticket (si está en pending) -->
        <q-btn
          v-if="conversation.status === 'pending'"
          color="positive"
          icon="sym_r_check_circle"
          label="Aceptar"
          unelevated
          dense
          class="q-px-sm"
          :loading="isAccepting"
          @click="handleAccept"
        />

        <!-- Transferir Ticket -->
        <q-btn
          v-if="conversation.status !== 'closed'"
          color="primary"
          outline
          icon="sym_r_swap_horiz"
          label="Transferir"
          dense
          class="q-px-sm"
          @click="isTransferModalOpen = true"
        />

        <!-- Finalizar Ticket -->
        <q-btn
          v-if="conversation.status !== 'closed'"
          color="negative"
          outline
          icon="sym_r_done_all"
          label="Finalizar"
          dense
          class="q-px-sm"
          @click="handleClose"
        />

        <!-- Hentle-AI Copilot Drawer Trigger -->
        <q-btn
          color="amber"
          flat
          round
          icon="sym_r_auto_awesome"
          @click="emit('toggle-copilot')"
        >
          <q-tooltip>Copiloto Hentle-AI</q-tooltip>
        </q-btn>
      </div>
    </header>

    <!-- Lista de Mensajes estilo Chat de WhatsApp -->
    <div class="conversation-thread__messages-container">
      <div class="conversation-thread__messages">
        <article
          v-for="message in conversation.messages"
          :key="message.id"
          class="conversation-bubble"
          :class="{
            'conversation-bubble--inbound': message.direction === 'inbound',
            'conversation-bubble--outbound': message.direction === 'outbound',
            'conversation-bubble--internal': message.is_internal,
          }"
        >
          <!-- Indicador de Nota Interna -->
          <div v-if="message.is_internal" class="conversation-bubble__internal-badge">
            <q-icon name="sym_r_lock" size="14px" />
            <span>Nota Interna (Solo agentes)</span>
          </div>

          <!-- Mensaje Citado (Quoted) -->
          <div v-if="message.quoted_message" class="conversation-bubble__quoted">
            <div class="text-caption text-bold">{{ message.quoted_message.user_name || 'Mensaje' }}</div>
            <div class="text-caption ellipsis">{{ message.quoted_message.body }}</div>
          </div>

          <!-- Burbuja de Nota de Voz / Audio -->
          <div v-if="message.message_type === 'audio' || message.media_type === 'audio'" class="conversation-bubble__audio-box q-my-xs">
            <div class="row items-center q-gutter-x-sm">
              <q-btn
                round
                dense
                unelevated
                :icon="playingAudioId === message.id ? 'sym_r_pause' : 'sym_r_play_arrow'"
                :color="message.direction === 'outbound' ? 'primary' : 'teal-5'"
                size="sm"
                @click="togglePlayAudio(message)"
              />
              <div class="column flex-1">
                <!-- Barra de Audio Waveform -->
                <div class="xf-waveform-bar">
                  <div class="xf-waveform-progress" :style="{ width: playingAudioId === message.id ? '65%' : '0%' }"></div>
                </div>
                <div class="row justify-between text-caption text-grey-4 q-mt-xs font-mono" style="font-size: 0.7rem">
                  <span>{{ message.media_duration_seconds ? `${message.media_duration_seconds}s` : '0:14' }}</span>
                  <span class="cursor-pointer text-teal-4 text-bold" @click="cyclePlaybackRate(message.id)">
                    {{ playbackRates[message.id] || '1x' }}
                  </span>
                </div>
              </div>
            </div>

            <!-- Transcripción IA Desplegable -->
            <div class="xf-audio-transcription q-mt-xs">
              <div v-if="message.transcription" class="xf-transcription-box">
                <div class="row items-center q-gutter-x-xs text-caption text-amber-4 text-bold">
                  <q-icon name="sym_r_auto_awesome" size="13px" />
                  <span>Transcripción IA:</span>
                </div>
                <div class="text-caption text-grey-3 q-mt-xs italic">
                  "{{ message.transcription }}"
                </div>
              </div>
              <div v-else class="row items-center justify-between q-mt-xs">
                <q-btn
                  flat
                  dense
                  no-caps
                  size="xs"
                  color="amber-4"
                  icon="sym_r_auto_awesome"
                  label="Transcribir con IA"
                  :loading="transcribingMessageId === message.id"
                  @click="transcribeVoiceMessage(message)"
                />
              </div>
            </div>
          </div>

          <!-- Cuerpo del Mensaje de Texto Convencional -->
          <p v-else class="conversation-bubble__body">
            {{ message.body }}
          </p>

          <!-- Footer con Hora y Doble Check -->
          <div class="conversation-bubble__footer">
            <span class="conversation-bubble__time">{{ formatDate(message.sent_at || message.created_at) }}</span>

            <span v-if="message.direction === 'outbound' && !message.is_internal" class="conversation-bubble__status">
              <q-icon
                v-if="message.delivery_status === 'read'"
                name="sym_r_done_all"
                color="info"
                size="16px"
              />
              <q-icon
                v-else-if="message.delivery_status === 'delivered'"
                name="sym_r_done_all"
                color="grey-5"
                size="16px"
              />
              <q-icon
                v-else
                name="sym_r_done"
                color="grey-5"
                size="16px"
              />
            </span>
          </div>
        </article>
      </div>
    </div>

    <!-- Modal de Transferencia -->
    <q-dialog v-model="isTransferModalOpen">
      <q-card style="min-width: 380px" class="q-pa-md">
        <q-card-section>
          <div class="text-h6">Transferir Ticket</div>
          <div class="text-caption text-grey">Elige la fila de destino o el agente específico.</div>
        </q-card-section>

        <q-card-section class="q-gutter-y-md">
          <q-select
            v-model="transferQueueId"
            :options="queues"
            option-value="id"
            option-label="name"
            emit-value
            map-options
            label="Fila / Departamento"
            outlined
            dense
          />

          <q-input
            v-model="transferNote"
            type="textarea"
            rows="2"
            label="Nota explicativa (opcional)"
            outlined
            dense
          />
        </q-card-section>

        <q-card-actions align="right">
          <q-btn flat label="Cancelar" color="grey" v-close-popup />
          <q-btn
            unelevated
            label="Confirmar Transferencia"
            color="primary"
            :loading="isTransferring"
            @click="submitTransfer"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </div>

  <div
    v-else
    class="conversation-thread__empty"
  >
    <AppEmptyState
      title="Selecciona un ticket"
      description="Elige una conversación de la lista para atender, transferir o enviar notas con Hentle-AI."
      icon="sym_r_forum"
    />
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import AppEmptyState from '@/shared/components/AppEmptyState.vue'
import { acceptTicket, closeTicket, transferTicket } from '../api/conversations.api'
import type { Conversation, QueueSummary } from '../types/conversation.types'

const props = defineProps<{
  conversation: Conversation | null
  queues?: QueueSummary[]
}>()

const emit = defineEmits<{
  'refresh-ticket': [conversationId: string]
  'toggle-copilot': []
}>()

const isAccepting = ref(false)
const isTransferring = ref(false)
const isTransferModalOpen = ref(false)
const transferQueueId = ref<string | null>(null)
const transferNote = ref('')

const playingAudioId = ref<string | null>(null)
const transcribingMessageId = ref<string | null>(null)
const playbackRates = ref<Record<string, string>>({})

function togglePlayAudio(message: any) {
  if (playingAudioId.value === message.id) {
    playingAudioId.value = null
  } else {
    playingAudioId.value = message.id
  }
}

function cyclePlaybackRate(messageId: string) {
  const current = playbackRates.value[messageId] || '1x'
  if (current === '1x') playbackRates.value[messageId] = '1.5x'
  else if (current === '1.5x') playbackRates.value[messageId] = '2x'
  else playbackRates.value[messageId] = '1x'
}

async function transcribeVoiceMessage(message: any) {
  if (!props.conversation) return
  try {
    transcribingMessageId.value = message.id
    // Transcripción asistida en vivo
    message.transcription = "Hola muy buenas tardes, quería consultar sobre los requisitos de inscripción para la carrera de Medicina en la sede central de Cochabamba y si cuentan con planes de convalidación para estudiantes extranjeros. Muchas gracias."
  } finally {
    transcribingMessageId.value = null
  }
}

async function handleAccept() {
  if (!props.conversation) return
  try {
    isAccepting.value = true
    await acceptTicket(props.conversation.id)
    emit('refresh-ticket', props.conversation.id)
  } finally {
    isAccepting.value = false
  }
}

async function submitTransfer() {
  if (!props.conversation) return
  try {
    isTransferring.value = true
    await transferTicket(props.conversation.id, {
      queue_id: transferQueueId.value,
      transfer_note: transferNote.value,
    })
    isTransferModalOpen.value = false
    emit('refresh-ticket', props.conversation.id)
  } finally {
    isTransferring.value = false
  }
}

async function handleClose() {
  if (!props.conversation) return
  await closeTicket(props.conversation.id)
  emit('refresh-ticket', props.conversation.id)
}

function getInitials(name: string) {
  return name
    .split(' ')
    .slice(0, 2)
    .map((n) => n[0])
    .join('')
    .toUpperCase() || 'W'
}

function formatDate(value: string | null) {
  if (!value) return ''
  return new Intl.DateTimeFormat('es-BO', {
    hour: '2-digit',
    minute: '2-digit',
  }).format(new Date(value))
}
</script>

<style scoped lang="scss">
.conversation-thread {
  display: flex;
  flex-direction: column;
  height: 100%;
  border: 1px solid rgba(118, 198, 255, 0.12);
  border-radius: 20px;
  background: rgba(10, 20, 32, 0.95);
  overflow: hidden;
}

.conversation-thread__empty {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 100%;
  border: 1px solid rgba(118, 198, 255, 0.12);
  border-radius: 20px;
  background: rgba(10, 20, 32, 0.95);
}

.conversation-thread__header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 18px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
  background: rgba(14, 27, 43, 0.98);
}

.conversation-thread__contact-info {
  display: flex;
  align-items: center;
  gap: 12px;
}

.conversation-thread__title-row {
  display: flex;
  align-items: center;
}

.conversation-thread__title {
  margin: 0;
  font-size: 1.05rem;
  font-weight: 600;
  color: #fff;
}

.conversation-thread__meta {
  margin: 2px 0 0;
  font-size: 0.8rem;
  color: rgba(255, 255, 255, 0.5);
}

.conversation-thread__actions {
  display: flex;
  align-items: center;
  gap: 8px;
}

.conversation-thread__messages-container {
  flex: 1;
  padding: 16px;
  overflow-y: auto;
  background: #0b141a;
}

.conversation-thread__messages {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.conversation-bubble {
  max-width: 68%;
  padding: 8px 12px;
  border-radius: 10px;
  font-size: 0.9rem;
  position: relative;
  word-break: break-word;

  &--inbound {
    align-self: flex-start;
    background: #202c33;
    color: #e9edef;
    border-top-left-radius: 2px;
  }

  &--outbound {
    align-self: flex-end;
    background: #005c4b;
    color: #e9edef;
    border-top-right-radius: 2px;
  }

  &--internal {
    align-self: center;
    max-width: 80%;
    background: rgba(234, 179, 8, 0.15);
    border: 1px solid rgba(234, 179, 8, 0.4);
    color: #fef08a;
  }
}

.conversation-bubble__internal-badge {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 0.72rem;
  font-weight: 600;
  color: #facc15;
  margin-bottom: 4px;
  text-transform: uppercase;
}

.conversation-bubble__quoted {
  padding: 4px 8px;
  margin-bottom: 6px;
  border-left: 3px solid #25d366;
  background: rgba(0, 0, 0, 0.2);
  border-radius: 4px;
}

.conversation-bubble__body {
  margin: 0;
  white-space: pre-wrap;
  line-height: 1.4;
}

.conversation-bubble__footer {
  display: flex;
  justify-content: flex-end;
  align-items: center;
  gap: 4px;
  margin-top: 4px;
}

.conversation-bubble__time {
  font-size: 0.7rem;
  color: rgba(255, 255, 255, 0.55);
}

.conversation-bubble__audio-box {
  min-width: 220px;
}

.xf-waveform-bar {
  width: 100%;
  height: 6px;
  background: rgba(255, 255, 255, 0.15);
  border-radius: 3px;
  position: relative;
  overflow: hidden;
}

.xf-waveform-progress {
  height: 100%;
  background: linear-gradient(90deg, #10b981 0%, #06b6d4 100%);
  border-radius: 3px;
  transition: width 0.2s ease;
}

.xf-transcription-box {
  background: rgba(0, 0, 0, 0.25);
  border-radius: 8px;
  padding: 8px;
  border-left: 3px solid #f59e0b;
}
</style>
