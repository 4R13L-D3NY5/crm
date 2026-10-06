<template>
  <main class="inbox-chat-thread" @paste="handlePaste">
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
                {{ conversation.contact?.name || conversation.subject || 'Contacto' }}
              </span>
              <q-badge
                :style="{ background: getChannelColor(conversation.channel) + '22', color: getChannelColor(conversation.channel), border: '1px solid ' + getChannelColor(conversation.channel) + '55' }"
                rounded
                class="q-px-xs text-caption"
              >
                {{ getChannelLabel(conversation.channel) }}
              </q-badge>
              <q-badge
                :color="statusBadgeColor"
                rounded
                class="q-px-xs text-caption"
              >
                {{ statusBadgeLabel }}
              </q-badge>
            </div>
            <div class="text-caption text-grey-4 ellipsis font-mono" style="font-size: 0.72rem">
              {{ conversation.contact?.phone || `Canal ${getChannelLabel(conversation.channel)}` }}
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
        <div
          class="message-bubble"
          :class="{ 'message-bubble--sticker': isStickerMessage(msg) }"
        >
          <!-- Nota Interna Header -->
          <div v-if="msg.is_internal || msg.direction === 'internal'" class="row items-center q-gutter-x-xs text-caption text-amber-4 q-mb-xs">
            <q-icon name="sym_r_lock" size="13px" />
            <span class="text-weight-bold">Nota Interna (Sólo Equipo)</span>
            <span v-if="msg.user?.name" class="text-grey-4">• {{ msg.user.name }}</span>
          </div>

          <!-- Sticker Adjunto (Visualización real tipo WhatsApp Web) -->
          <div
            v-if="msg.media_url && (msg.media_type === 'sticker' || msg.message_type === 'sticker')"
            class="message-sticker-container relative-position q-mb-xs"
          >
            <img
              :src="resolveMediaUrl(msg.media_url)"
              alt="Sticker"
              class="message-sticker-img"
              loading="lazy"
              @click="openImageModal(resolveMediaUrl(msg.media_url))"
            />
            <div class="sticker-action-overlay row items-center q-gutter-x-xs">
              <q-btn
                round
                dense
                size="xs"
                color="teal-8"
                icon="sym_r_reply"
                @click.stop="reuseSticker(msg.media_url)"
              >
                <q-tooltip>Reenviar este sticker</q-tooltip>
              </q-btn>
              <q-btn
                round
                dense
                size="xs"
                color="grey-8"
                icon="sym_r_download"
                :href="resolveMediaUrl(msg.media_url)"
                download
                target="_blank"
                @click.stop
              >
                <q-tooltip>Descargar sticker (.webp)</q-tooltip>
              </q-btn>
            </div>
          </div>

          <!-- Imagen Adjunta -->
          <div v-else-if="msg.media_url && msg.media_type === 'image'" class="message-media-container q-mb-xs">
            <img
              :src="resolveMediaUrl(msg.media_url)"
              alt="Imagen adjunta"
              class="message-img-thumb"
              loading="lazy"
              @click="openImageModal(resolveMediaUrl(msg.media_url))"
            />
          </div>

          <!-- Audio / Nota de voz -->
          <div v-if="msg.media_url && (msg.media_type === 'audio' || msg.message_type === 'audio')" class="message-media-container q-mb-xs">
            <div class="row items-center q-gutter-x-xs q-mb-xs text-caption" style="opacity: 0.9">
              <q-icon name="sym_r_mic" size="14px" color="teal-3" />
              <span>Nota de voz</span>
              <span v-if="msg.media_duration_seconds" class="text-grey-4">• {{ formatDuration(msg.media_duration_seconds) }}</span>
            </div>
            <audio controls class="message-audio-player" preload="metadata">
              <source :src="resolveMediaUrl(msg.media_url)">
              Tu navegador no soporta el reproductor de audio.
            </audio>
          </div>

          <!-- Documento Adjunto -->
          <div v-if="msg.media_url && msg.media_type === 'document'" class="message-media-doc q-mb-xs">
            <a :href="resolveMediaUrl(msg.media_url)" target="_blank" download class="message-doc-link row items-center q-gutter-x-sm">
              <q-icon name="sym_r_description" size="20px" color="teal-4" />
              <span class="text-caption ellipsis text-white">{{ msg.body && !isPureMediaPlaceholder(msg) ? msg.body : 'Descargar documento' }}</span>
              <q-icon name="sym_r_download" size="15px" color="grey-4" />
            </a>
          </div>

          <!-- Texto / Caption -->
          <div
            v-if="msg.body && !isPureMediaPlaceholder(msg)"
            class="message-body"
          >
            <span v-if="msg.body === '[Sticker]' && !msg.media_url" class="row items-center q-gutter-x-xs text-grey-4">
              <q-icon name="sym_r_sentiment_satisfied" size="16px" color="teal-3" />
              <span>[Sticker recibido con versión anterior]</span>
            </span>
            <template v-else>
              {{ msg.body }}
            </template>
          </div>

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
    <footer class="inbox-composer q-pa-sm" @paste="handlePaste">
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

          <q-btn
            flat
            dense
            size="sm"
            color="teal-4"
            icon="sym_r_bolt"
            label="Respuestas Rápidas (/)"
            class="q-px-xs"
            @click="showQuickMessagesModal = true"
          >
            <q-tooltip>Atajos de respuesta rápida</q-tooltip>
          </q-btn>

          <!-- Selector de Emojis -->
          <q-btn
            flat
            dense
            size="sm"
            color="amber-4"
            icon="sym_r_sentiment_satisfied"
            label="Emojis"
            class="q-px-xs"
          >
            <q-tooltip>Insertar Emoji</q-tooltip>
            <q-menu anchor="top start" self="bottom start" :offset="[0, 8]" class="emoji-menu-popover">
              <div class="emoji-picker-container q-pa-sm">
                <div class="text-caption text-weight-bold text-grey-4 q-mb-xs">Emojis Frecuentes</div>
                <div class="emoji-grid">
                  <button
                    v-for="emoji in popularEmojis"
                    :key="emoji"
                    type="button"
                    class="emoji-grid-btn"
                    @click="insertEmoji(emoji)"
                  >
                    {{ emoji }}
                  </button>
                </div>
              </div>
            </q-menu>
          </q-btn>

          <!-- Adjuntar Archivo / Imagen -->
          <q-btn
            flat
            dense
            size="sm"
            color="light-blue-4"
            icon="sym_r_attach_file"
            label="Adjuntar"
            class="q-px-xs"
            @click="triggerFileInput"
          >
            <q-tooltip>Adjuntar imagen/archivo o pegar con Ctrl+V</q-tooltip>
          </q-btn>
          <input
            ref="fileInputRef"
            type="file"
            accept="image/*,audio/*,application/pdf"
            style="display: none"
            @change="handleFileSelected"
          />

          <!-- Grabar Nota de Voz -->
          <q-btn
            flat
            dense
            size="sm"
            :color="isRecordingVoice ? 'negative' : 'teal-3'"
            :icon="isRecordingVoice ? 'sym_r_stop_circle' : 'sym_r_mic'"
            :label="isRecordingVoice ? formatRecordTimer(recordingSeconds) : 'Audio'"
            class="q-px-xs"
            :class="{ 'pulse-recording': isRecordingVoice }"
            @click="toggleVoiceRecording"
          >
            <q-tooltip>{{ isRecordingVoice ? 'Detener grabación de audio' : 'Grabar nota de voz con micrófono' }}</q-tooltip>
          </q-btn>
        </div>

        <span class="text-caption text-grey-5" style="font-size: 0.7rem">
          Presiona <strong>Enter</strong> para enviar
        </span>
      </div>

      <!-- Barra en vivo de grabación de voz -->
      <div v-if="isRecordingVoice" class="voice-recording-banner row items-center justify-between q-pa-sm q-mb-xs">
        <div class="row items-center q-gutter-x-sm">
          <span class="recording-dot"></span>
          <span class="text-caption text-weight-bold text-negative">Grabando nota de voz...</span>
          <span class="text-caption font-mono text-white">{{ formatRecordTimer(recordingSeconds) }}</span>
        </div>
        <div class="row items-center q-gutter-x-xs">
          <q-btn
            flat
            dense
            size="sm"
            color="grey-4"
            icon="sym_r_delete"
            label="Descartar"
            @click="cancelVoiceRecording"
          />
          <q-btn
            unelevated
            dense
            size="sm"
            color="teal-7"
            icon="sym_r_check"
            label="Listo"
            @click="stopVoiceRecording(true)"
          />
        </div>
      </div>

      <!-- Chip / Preview de archivo adjunto pendiente de envío -->
      <div v-if="pendingFile" class="pending-media-chip row items-center justify-between q-pa-xs q-mb-xs">
        <div class="row items-center q-gutter-x-sm">
          <img
            v-if="pendingFilePreview && (pendingFileType === 'image' || pendingFileType === 'sticker')"
            :src="pendingFilePreview"
            alt="Preview"
            class="pending-thumb"
            :class="{ 'pending-thumb--sticker': pendingFileType === 'sticker' }"
          />
          <q-icon
            v-else
            :name="pendingFileType === 'audio' ? 'sym_r_mic' : 'sym_r_description'"
            size="24px"
            :color="pendingFileType === 'audio' ? 'teal-4' : 'blue-4'"
          />
          <div>
            <div class="row items-center q-gutter-x-xs">
              <span class="text-caption text-weight-bold text-white ellipsis" style="max-width: 200px">
                {{ pendingFile.name }}
              </span>
              <q-badge
                v-if="pendingFileType === 'sticker'"
                color="teal-8"
                label="Sticker"
                rounded
                class="text-caption text-weight-bold"
              />
            </div>
            <div class="text-caption text-grey-4" style="font-size: 0.7rem">
              {{ formatFileSize(pendingFile.size) }}
            </div>
          </div>
        </div>

        <div class="row items-center q-gutter-x-xs">
          <!-- Botón convertir a Sticker si es imagen -->
          <q-btn
            v-if="pendingFileType === 'image'"
            flat
            dense
            size="sm"
            color="teal-3"
            icon="sym_r_auto_awesome"
            label="Como Sticker"
            class="q-px-xs"
            @click="convertPendingImageToSticker"
          >
            <q-tooltip>Convertir y enviar como sticker de WhatsApp (512x512 WebP)</q-tooltip>
          </q-btn>

          <!-- Botón revertir a Imagen si es sticker -->
          <q-btn
            v-else-if="pendingFileType === 'sticker'"
            flat
            dense
            size="sm"
            color="amber-4"
            icon="sym_r_image"
            label="Como Foto"
            class="q-px-xs"
            @click="pendingFileType = 'image'"
          >
            <q-tooltip>Enviar como foto estándar en vez de sticker</q-tooltip>
          </q-btn>

          <q-btn
            flat
            round
            dense
            size="sm"
            icon="sym_r_close"
            color="grey-4"
            @click="clearPendingFile"
          >
            <q-tooltip>Quitar archivo adjunto</q-tooltip>
          </q-btn>
        </div>
      </div>

      <div class="row items-end q-gutter-x-sm">
        <q-input
          ref="composerInputRef"
          v-model="composerText"
          type="textarea"
          autogrow
          dense
          outlined
          dark
          :rows="1"
          :placeholder="composerMode === 'whatsapp' ? 'Escribe una respuesta para el cliente... (o pega imagen con Ctrl+V, / para atajos)' : 'Escribe una nota interna para los supervisores...'"
          class="composer-textarea col"
          @keydown.enter.exact.prevent="handleSend"
          @paste="handlePaste"
        />

        <q-btn
          unelevated
          dense
          round
          :color="composerMode === 'whatsapp' ? 'primary' : 'amber-8'"
          icon="sym_r_send"
          :loading="isSending"
          :disable="!composerText.trim() && !pendingFile"
          @click="handleSend"
        />
      </div>
    </footer>

    <!-- Modal Seleccionar Respuesta Rápida -->
    <q-dialog v-model="showQuickMessagesModal">
      <q-card style="min-width: 440px; max-width: 95vw" class="quick-messages-picker-card">
        <q-card-section class="q-pb-none">
          <div class="row items-center justify-between">
            <div class="text-subtitle1 text-weight-bold text-white row items-center q-gutter-x-xs">
              <q-icon name="sym_r_bolt" color="teal-4" size="20px" />
              <span>Respuestas Rápidas</span>
            </div>
            <q-btn flat round dense icon="sym_r_close" color="grey-4" v-close-popup />
          </div>
          <q-input
            v-model="quickSearch"
            dense
            outlined
            dark
            placeholder="Filtrar por atajo o mensaje..."
            class="q-mt-sm"
          >
            <template #prepend>
              <q-icon name="sym_r_search" size="18px" />
            </template>
          </q-input>
        </q-card-section>

        <q-card-section style="max-height: 340px" class="scroll q-py-sm">
          <div v-if="filteredQuickMessages.length === 0" class="text-center text-grey-5 q-py-md">
            No se encontraron atajos disponibles.
          </div>
          <q-list v-else separator dark>
            <q-item
              v-for="qm in filteredQuickMessages"
              :key="qm.id"
              clickable
              class="rounded-borders q-my-xs quick-message-item"
              @click="applyQuickMessage(qm.message)"
            >
              <q-item-section>
                <div class="row items-center q-gutter-x-sm">
                  <span class="text-weight-bold text-teal-4 font-mono">/{{ qm.shortcut.replace(/^\/+/, '') }}</span>
                  <q-badge v-if="qm.is_general" outline color="blue-6" size="xs">General</q-badge>
                </div>
                <div class="text-caption text-grey-3 q-mt-xs ellipsis-2-lines">
                  {{ qm.message }}
                </div>
              </q-item-section>
            </q-item>
          </q-list>
        </q-card-section>
      </q-card>
    </q-dialog>

    <!-- Modal Zoom de Imagen -->
    <q-dialog v-model="showImageModal">
      <q-card class="image-zoom-card">
        <div class="row items-center justify-between q-pa-sm image-zoom-header">
          <div class="row items-center q-gutter-x-xs text-caption text-white">
            <q-icon name="sym_r_image" size="18px" color="teal-4" />
            <span>Vista Previa de Imagen</span>
          </div>
          <div class="row items-center q-gutter-x-xs">
            <q-btn
              flat
              round
              dense
              icon="sym_r_download"
              color="grey-4"
              :href="activeImageUrl"
              download
              target="_blank"
            >
              <q-tooltip>Descargar original</q-tooltip>
            </q-btn>
            <q-btn flat round dense icon="sym_r_close" color="grey-4" v-close-popup />
          </div>
        </div>
        <div class="image-zoom-body flex flex-center q-pa-md">
          <img :src="activeImageUrl" alt="Visualización completa" class="image-zoom-img" />
        </div>
      </q-card>
    </q-dialog>
  </main>
</template>

<script setup lang="ts">
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import { useQuickMessages } from '@/modules/quick-messages/composables/useQuickMessages'
import type { Conversation, ConversationMessage } from '../types/conversation.types'

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
  (e: 'send-message', payload: {
    body: string
    is_internal: boolean
    file?: File | null
    media_type?: string | null
  }): void
}>()

const composerInputRef = ref<any>(null)
const fileInputRef = ref<HTMLInputElement | null>(null)
const pendingFile = ref<File | null>(null)
const pendingFileType = ref<'image' | 'audio' | 'document' | 'sticker' | null>(null)
const pendingFilePreview = ref<string>('')

function isStickerMessage(msg: ConversationMessage): boolean {
  return Boolean(
    msg.media_url &&
    (msg.media_type === 'sticker' || msg.message_type === 'sticker') &&
    isPureMediaPlaceholder(msg)
  )
}

function setPendingFile(file: File, type: 'image' | 'audio' | 'document' | 'sticker') {
  if (pendingFilePreview.value) {
    URL.revokeObjectURL(pendingFilePreview.value)
  }
  pendingFile.value = file
  pendingFileType.value = type
  if (type === 'image' || type === 'sticker') {
    pendingFilePreview.value = URL.createObjectURL(file)
  } else {
    pendingFilePreview.value = ''
  }
}

async function reuseSticker(url?: string | null) {
  if (!url) return
  try {
    const fullUrl = resolveMediaUrl(url)
    const res = await fetch(fullUrl)
    const blob = await res.blob()
    const stickerFile = new File([blob], `sticker_${Date.now()}.webp`, { type: 'image/webp' })
    setPendingFile(stickerFile, 'sticker')
    composerInputRef.value?.focus()
  } catch (err) {
    console.error('Error al reutilizar sticker:', err)
  }
}

function convertImageToStickerBlob(file: File): Promise<Blob> {
  return new Promise((resolve) => {
    const img = new Image()
    const url = URL.createObjectURL(file)
    img.onload = () => {
      URL.revokeObjectURL(url)
      const canvas = document.createElement('canvas')
      canvas.width = 512
      canvas.height = 512
      const ctx = canvas.getContext('2d')
      if (!ctx) {
        resolve(file)
        return
      }
      ctx.clearRect(0, 0, 512, 512)
      const scale = Math.min(512 / img.width, 512 / img.height)
      const w = img.width * scale
      const h = img.height * scale
      const x = (512 - w) / 2
      const y = (512 - h) / 2
      ctx.drawImage(img, x, y, w, h)
      canvas.toBlob((blob) => {
        if (blob) resolve(blob)
        else resolve(file)
      }, 'image/webp', 0.9)
    }
    img.onerror = () => {
      URL.revokeObjectURL(url)
      resolve(file)
    }
    img.src = url
  })
}

async function convertPendingImageToSticker() {
  if (!pendingFile.value) return
  try {
    const webpBlob = await convertImageToStickerBlob(pendingFile.value)
    const stickerFile = new File([webpBlob], `sticker_${Date.now()}.webp`, { type: 'image/webp' })
    setPendingFile(stickerFile, 'sticker')
  } catch (err) {
    console.error('Error convirtiendo imagen a sticker:', err)
  }
}

function handlePaste(event: ClipboardEvent) {
  if (event.defaultPrevented) return
  const clipboardData = event.clipboardData
  if (!clipboardData) return

  // 1. Archivos copiados desde el explorador de archivos (Ctrl+C en imagen)
  if (clipboardData.files && clipboardData.files.length > 0) {
    for (let i = 0; i < clipboardData.files.length; i++) {
      const file = clipboardData.files[i]
      if (file.type.startsWith('image/')) {
        event.preventDefault()
        setPendingFile(file, 'image')
        composerInputRef.value?.focus()
        return
      }
    }
  }

  // 2. Items del portapapeles (captura de pantalla Win+Shift+S / PrtScn / Copiar imagen en navegador)
  const items = clipboardData.items
  if (items && items.length > 0) {
    for (let i = 0; i < items.length; i++) {
      const item = items[i]
      if (item.type.startsWith('image/')) {
        const blob = item.getAsFile()
        if (blob) {
          event.preventDefault()
          const ext = item.type.split('/')[1]?.split(';')[0] || 'png'
          const defaultName = `captura_${Date.now()}.${ext}`
          const fileName = (blob.name && blob.name !== 'image.png') ? blob.name : defaultName
          const file = new File([blob], fileName, { type: item.type })
          setPendingFile(file, 'image')
          composerInputRef.value?.focus()
          return
        }
      }
    }
  }
}

const isRecordingVoice = ref(false)
const recordingSeconds = ref(0)
let recordingTimer: any = null
let mediaRecorder: MediaRecorder | null = null
let audioChunks: Blob[] = []

function triggerFileInput() {
  fileInputRef.value?.click()
}

function handleFileSelected(event: Event) {
  const target = event.target as HTMLInputElement
  if (!target.files || target.files.length === 0) return
  const file = target.files[0]

  if (file.type.startsWith('image/')) {
    setPendingFile(file, 'image')
  } else if (file.type.startsWith('audio/')) {
    setPendingFile(file, 'audio')
  } else {
    setPendingFile(file, 'document')
  }
  target.value = ''
}

function clearPendingFile() {
  if (pendingFilePreview.value) {
    URL.revokeObjectURL(pendingFilePreview.value)
  }
  pendingFile.value = null
  pendingFileType.value = null
  pendingFilePreview.value = ''
}

async function toggleVoiceRecording() {
  if (isRecordingVoice.value) {
    stopVoiceRecording(true)
  } else {
    await startVoiceRecording()
  }
}

async function startVoiceRecording() {
  try {
    const stream = await navigator.mediaDevices.getUserMedia({ audio: true })
    audioChunks = []

    let mimeType = 'audio/webm'
    if (MediaRecorder.isTypeSupported('audio/mp4')) {
      mimeType = 'audio/mp4'
    } else if (MediaRecorder.isTypeSupported('audio/ogg; codecs=opus')) {
      mimeType = 'audio/ogg; codecs=opus'
    } else if (MediaRecorder.isTypeSupported('audio/webm; codecs=opus')) {
      mimeType = 'audio/webm; codecs=opus'
    }

    mediaRecorder = new MediaRecorder(stream, { mimeType })
    mediaRecorder.ondataavailable = (e) => {
      if (e.data.size > 0) audioChunks.push(e.data)
    }
    mediaRecorder.start(100)
    isRecordingVoice.value = true
    recordingSeconds.value = 0
    recordingTimer = setInterval(() => {
      recordingSeconds.value++
    }, 1000)
  } catch (err: any) {
    console.error('Error accediendo al micrófono:', err)
  }
}

function stopVoiceRecording(keepAudio = true) {
  if (!mediaRecorder) return
  if (recordingTimer) {
    clearInterval(recordingTimer)
    recordingTimer = null
  }
  isRecordingVoice.value = false

  mediaRecorder.onstop = () => {
    mediaRecorder?.stream.getTracks().forEach((t) => t.stop())
    if (keepAudio && audioChunks.length > 0) {
      const mime = mediaRecorder?.mimeType || 'audio/webm'
      const ext = mime.includes('mp4') ? 'm4a' : (mime.includes('ogg') ? 'ogg' : 'webm')
      const blob = new Blob(audioChunks, { type: mime })
      const voiceFile = new File([blob], `nota_de_voz_${Date.now()}.${ext}`, { type: mime })
      setPendingFile(voiceFile, 'audio')
    }
  }
  mediaRecorder.stop()
}

function cancelVoiceRecording() {
  stopVoiceRecording(false)
}

function formatFileSize(bytes: number): string {
  if (!bytes) return '0 B'
  const k = 1024
  const sizes = ['B', 'KB', 'MB', 'GB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return `${(bytes / Math.pow(k, i)).toFixed(1)} ${sizes[i]}`
}

function formatRecordTimer(sec: number): string {
  const m = Math.floor(sec / 60)
  const s = sec % 60
  return `${m < 10 ? '0' : ''}${m}:${s < 10 ? '0' : ''}${s}`
}

const quickMessagesQuery = useQuickMessages()
const showQuickMessagesModal = ref(false)
const quickSearch = ref('')

const showImageModal = ref(false)
const activeImageUrl = ref('')

const popularEmojis = [
  '😀', '😃', '😄', '😁', '😅', '😂', '🤣', '😊',
  '😇', '🙂', '😉', '😍', '🥰', '😘', '🤩', '🥳',
  '👍', '👎', '👌', '✌️', '🤞', '👏', '🙌', '🙏',
  '🤝', '💪', '🔥', '✨', '⭐', '❤️', '💯', '🎯',
  '💼', '💰', '📦', '🚀', '📞', '💬', '📍', '✅',
  '⚠️', '⏳', '📌', '🎉', '💡', '📝', '🔒', '👋',
]

function insertEmoji(emoji: string) {
  composerText.value += emoji
}

function openImageModal(url: string) {
  activeImageUrl.value = url
  showImageModal.value = true
}

function resolveMediaUrl(url?: string | null): string {
  if (!url) return ''
  if (url.startsWith('http://') || url.startsWith('https://')) {
    return url
  }
  const apiBase = (import.meta.env.VITE_API_BASE_URL ?? 'http://localhost:8010/api').replace(/\/api\/?$/, '')
  return `${apiBase}${url.startsWith('/') ? '' : '/'}${url}`
}

function formatDuration(seconds?: number | null): string {
  if (!seconds || seconds <= 0) return '0:00'
  const mins = Math.floor(seconds / 60)
  const secs = Math.floor(seconds % 60)
  return `${mins}:${secs < 10 ? '0' : ''}${secs}`
}

function isPureMediaPlaceholder(msg: ConversationMessage): boolean {
  if (!msg.media_url) return false
  const trimmed = (msg.body || '').trim()
  return (
    trimmed === '[Imagen]' ||
    trimmed === '[Nota de voz / Audio]' ||
    trimmed === '[Audio]' ||
    trimmed === '[Video]' ||
    trimmed === '[Documento]' ||
    trimmed === '[Sticker]' ||
    trimmed === '[Multimedia]'
  )
}

const messagesContainer = ref<HTMLElement | null>(null)
const composerMode = ref<'whatsapp' | 'internal'>('whatsapp')
const composerText = ref('')

const filteredQuickMessages = computed(() => {
  const list = quickMessagesQuery.data.value ?? []
  const query = quickSearch.value.trim().toLowerCase()
  if (!query) return list
  return list.filter(
    (item) =>
      item.shortcut.toLowerCase().includes(query) ||
      item.message.toLowerCase().includes(query),
  )
})

function applyQuickMessage(template: string) {
  const contactName = props.conversation.contact?.name || 'cliente'
  const contactPhone = props.conversation.contact?.phone || ''

  const result = template
    .replace(/\{\{\s*name\s*\}\}/gi, contactName)
    .replace(/\{\{\s*phone\s*\}\}/gi, contactPhone)
    .replace(/\{\{\s*greeting\s*\}\}/gi, 'Hola')

  if (composerText.value.startsWith('/')) {
    composerText.value = result
  } else if (composerText.value.trim()) {
    composerText.value += '\n' + result
  } else {
    composerText.value = result
  }

  showQuickMessagesModal.value = false
}

watch(
  () => composerText.value,
  (val) => {
    if (val === '/') {
      showQuickMessagesModal.value = true
    }
  },
)

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

function getChannelColor(channel?: string) {
  switch (channel) {
    case 'facebook': return '#1877f2'
    case 'instagram': return '#e1306c'
    case 'tiktok': return '#25f4ee'
    case 'whatsapp': default: return '#10b981'
  }
}

function getChannelLabel(channel?: string) {
  switch (channel) {
    case 'facebook': return 'Facebook'
    case 'instagram': return 'Instagram'
    case 'tiktok': return 'TikTok'
    case 'whatsapp': default: return 'WhatsApp'
  }
}

function handleSend() {
  if (!composerText.value.trim() && !pendingFile.value) return
  emit('send-message', {
    body: composerText.value.trim(),
    is_internal: composerMode.value === 'internal',
    file: pendingFile.value,
    media_type: pendingFileType.value,
  })
  composerText.value = ''
  clearPendingFile()
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

onUnmounted(() => {
  if (pendingFilePreview.value) {
    URL.revokeObjectURL(pendingFilePreview.value)
  }
  if (recordingTimer) {
    clearInterval(recordingTimer)
  }
  if (mediaRecorder && isRecordingVoice.value) {
    mediaRecorder.stream.getTracks().forEach((t) => t.stop())
  }
})
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

      &.message-bubble--sticker {
        background: transparent !important;
        border: none !important;
        box-shadow: none !important;
        padding: 2px !important;
      }
    }
  }

  &--outbound {
    justify-content: flex-end;
    .message-bubble {
      background: #065f46;
      color: #ffffff;
      border-bottom-right-radius: 2px;

      &.message-bubble--sticker {
        background: transparent !important;
        border: none !important;
        box-shadow: none !important;
        padding: 2px !important;
      }
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

.quick-messages-picker-card {
  background: var(--crm-bg-card, #101e2e) !important;
  border: 1px solid var(--crm-color-border, rgba(255, 255, 255, 0.1)) !important;
  border-radius: 16px !important;
}

.quick-message-item {
  transition: background-color var(--crm-transition-fast, 0.15s ease);
  &:hover {
    background-color: rgba(77, 208, 225, 0.08) !important;
  }
}

.message-img-thumb {
  max-width: 280px;
  max-height: 260px;
  border-radius: 8px;
  object-fit: cover;
  cursor: pointer;
  display: block;
  transition: transform var(--crm-transition-fast, 0.15s ease), filter var(--crm-transition-fast, 0.15s ease);
  border: 1px solid rgba(255, 255, 255, 0.1);

  &:hover {
    transform: scale(1.02);
    filter: brightness(1.08);
  }
}

.message-sticker-container {
  display: flex;
  position: relative;
  width: fit-content;

  &:hover .sticker-action-overlay {
    opacity: 1;
    pointer-events: auto;
  }
}

.message-sticker-img {
  max-width: 140px;
  max-height: 140px;
  width: auto;
  height: auto;
  object-fit: contain;
  cursor: pointer;
  filter: drop-shadow(0 2px 5px rgba(0, 0, 0, 0.4));
  transition: transform var(--crm-transition-fast, 0.15s ease);

  &:hover {
    transform: scale(1.05);
  }
}

.sticker-action-overlay {
  position: absolute;
  top: 4px;
  right: 4px;
  opacity: 0;
  pointer-events: none;
  transition: opacity var(--crm-transition-fast, 0.15s ease);
  background: rgba(15, 23, 42, 0.85);
  border-radius: 16px;
  padding: 2px 4px;
  backdrop-filter: blur(4px);
  z-index: 2;
}

.pending-thumb--sticker {
  border: 1px dashed var(--crm-color-primary) !important;
  border-radius: 6px;
}

.message-audio-player {
  width: 260px;
  max-width: 100%;
  height: 38px;
  border-radius: 20px;
  outline: none;
}

.message-media-doc {
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 8px;
  padding: 6px 10px;
}

.message-doc-link {
  text-decoration: none;
  cursor: pointer;

  &:hover {
    text-decoration: underline;
  }
}

.emoji-menu-popover {
  background: var(--crm-bg-surface-elevated, #1a2332) !important;
  border: 1px solid var(--crm-color-border, rgba(255, 255, 255, 0.1)) !important;
  border-radius: 12px !important;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.5) !important;
}

.emoji-picker-container {
  width: 280px;
  max-width: 90vw;
}

.emoji-grid {
  display: grid;
  grid-template-columns: repeat(8, 1fr);
  gap: 4px;
}

.emoji-grid-btn {
  background: transparent;
  border: none;
  font-size: 1.25rem;
  line-height: 1;
  padding: 6px 2px;
  border-radius: 6px;
  cursor: pointer;
  transition: background var(--crm-transition-fast, 0.15s ease), transform var(--crm-transition-fast, 0.15s ease);

  &:hover {
    background: rgba(255, 255, 255, 0.12);
    transform: scale(1.2);
  }
}

.image-zoom-card {
  background: #0b1120 !important;
  border: 1px solid var(--crm-color-border, rgba(255, 255, 255, 0.15));
  border-radius: 14px;
  max-width: 90vw;
  max-height: 90vh;
  overflow: hidden;
  display: flex;
  flex-direction: column;
}

.image-zoom-header {
  background: #070b14;
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.image-zoom-body {
  overflow: auto;
  max-height: calc(90vh - 54px);
}

.image-zoom-img {
  max-width: 100%;
  max-height: 75vh;
  object-fit: contain;
  border-radius: 8px;
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.6);
}

.pending-media-chip {
  background: var(--crm-bg-surface-elevated, #162032);
  border: 1px solid var(--crm-color-border, rgba(255, 255, 255, 0.15));
  border-radius: 8px;
}

.pending-thumb {
  width: 38px;
  height: 38px;
  border-radius: 6px;
  object-fit: cover;
  border: 1px solid rgba(255, 255, 255, 0.1);
}

.voice-recording-banner {
  background: rgba(239, 68, 68, 0.12);
  border: 1px solid rgba(239, 68, 68, 0.35);
  border-radius: 8px;
}

.recording-dot {
  width: 10px;
  height: 10px;
  background-color: #ef4444;
  border-radius: 50%;
  display: inline-block;
  animation: pulse-dot 1s infinite;
}

@keyframes pulse-dot {
  0% { transform: scale(0.95); opacity: 1; }
  50% { transform: scale(1.3); opacity: 0.5; }
  100% { transform: scale(0.95); opacity: 1; }
}

.pulse-recording {
  animation: pulse-border 1.5s infinite;
}

@keyframes pulse-border {
  0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.6); }
  70% { box-shadow: 0 0 0 6px rgba(239, 68, 68, 0); }
  100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
}
</style>
