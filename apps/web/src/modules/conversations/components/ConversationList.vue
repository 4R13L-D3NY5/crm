<template>
  <div class="xf-chat-list">
    <article
      v-for="conversation in conversations"
      :key="conversation.id"
      class="xf-chat-item"
      :class="{ 'xf-chat-item--active': conversation.id === selectedId }"
      @click="emit('select', conversation.id)"
    >
      <!-- Avatar con círculo de color y micro-borde -->
      <div class="xf-chat-item__avatar-wrap">
        <div
          class="xf-chat-item__avatar"
          :style="{ backgroundColor: getAvatarColor(conversation.contact?.name || conversation.subject || 'W') }"
        >
          {{ getInitial(conversation.contact?.name || conversation.subject || 'W') }}
        </div>
      </div>

      <!-- Contenido Central del Item -->
      <div class="xf-chat-item__body">
        <div class="xf-chat-item__top-row">
          <span class="xf-chat-item__name">
            {{ conversation.contact?.name || conversation.subject || 'Contacto' }}
          </span>
          <span class="xf-chat-item__date">
            {{ formatItemDate(conversation.last_message_at) }}
          </span>
        </div>

        <div class="xf-chat-item__bottom-row">
          <span class="xf-chat-item__preview">
            <span v-if="conversation.latest_message?.media_type === 'image'" class="row items-center q-gutter-x-xs">
              <q-icon name="sym_r_image" size="14px" color="teal-4" />
              <span>Imagen</span>
            </span>
            <span v-else-if="conversation.latest_message?.media_type === 'document'" class="row items-center q-gutter-x-xs">
              <q-icon name="sym_r_description" size="14px" color="indigo-4" />
              <span>Documento</span>
            </span>
            <span v-else>
              {{ conversation.latest_message?.body || 'Sin mensajes todavía.' }}
            </span>
          </span>

          <!-- Iconos y Badges del Item -->
          <div class="xf-chat-item__meta-icons">
            <!-- Icono de Canal -->
            <q-icon
              v-if="conversation.channel === 'facebook'"
              name="sym_r_public"
              size="15px"
              color="blue-4"
              class="q-mr-xs"
            />
            <q-icon
              v-else-if="conversation.channel === 'instagram'"
              name="sym_r_photo_camera"
              size="15px"
              color="pink-4"
              class="q-mr-xs"
            />
            <svg v-else width="15" height="15" viewBox="0 0 24 24" fill="none" class="q-mr-xs">
              <path d="M12 2C6.48 2 2 6.48 2 12C2 13.85 2.5 15.58 3.38 17.07L2 22L7.07 20.65C8.52 21.5 10.2 22 12 22C17.52 22 22 17.52 22 12C22 6.48 17.52 2 12 2Z" fill="#10B981"/>
              <path d="M8 10L10.5 15L12 12L13.5 15L16 10" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>

            <!-- Badge verde de No Leídos -->
            <span v-if="(conversation.unread_count ?? 0) > 0" class="xf-unread-pill">
              {{ conversation.unread_count }}
            </span>
          </div>
        </div>
      </div>
    </article>

    <div v-if="conversations.length === 0" class="xf-empty-list">
      <q-icon name="sym_r_chat_bubble_outline" size="24px" color="grey-6" class="q-mb-xs" />
      <div>No hay conversaciones en esta sección.</div>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { Conversation } from '../types/conversation.types'

defineProps<{
  conversations: Conversation[]
  selectedId: string | null
}>()

const emit = defineEmits<{
  select: [conversationId: string]
}>()

function getInitial(name: string) {
  return name.trim().charAt(0).toUpperCase() || 'W'
}

function getAvatarColor(name: string) {
  const colors = [
    '#10b981', // Emerald
    '#0284c7', // Sky Blue
    '#d97706', // Amber
    '#059669', // Dark Emerald
    '#8b5cf6', // Violet
    '#ec4899', // Pink
    '#6366f1', // Indigo
    '#06b6d4', // Cyan
  ]
  let hash = 0
  for (let i = 0; i < name.length; i++) {
    hash = name.charCodeAt(i) + ((hash << 5) - hash)
  }
  return colors[Math.abs(hash) % colors.length]
}

function formatItemDate(dateStr?: string | null) {
  if (!dateStr) return ''
  const date = new Date(dateStr)
  if (isNaN(date.getTime())) return ''

  const now = new Date()
  const isToday =
    date.getDate() === now.getDate() &&
    date.getMonth() === now.getMonth() &&
    date.getFullYear() === now.getFullYear()

  const hours = date.getHours().toString().padStart(2, '0')
  const minutes = date.getMinutes().toString().padStart(2, '0')

  if (isToday) {
    return `${hours}:${minutes}`
  }

  const day = date.getDate().toString().padStart(2, '0')
  const month = (date.getMonth() + 1).toString().padStart(2, '0')
  return `${day}/${month}`
}
</script>

<style scoped lang="scss">
.xf-chat-list {
  display: flex;
  flex-direction: column;
}

.xf-chat-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 14px;
  background: transparent;
  cursor: pointer;
  border-bottom: 1px solid var(--crm-color-border);
  transition: all var(--crm-transition);

  &:hover {
    background-color: var(--crm-bg-card-hover);
  }

  &--active {
    background-color: var(--crm-bg-surface-elevated) !important;
    border-left: 3px solid var(--crm-color-primary);
  }

  &__avatar-wrap {
    flex-shrink: 0;
  }

  &__avatar {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #ffffff;
    font-weight: 700;
    font-size: 1rem;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.25);
  }

  &__body {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 4px;
  }

  &__top-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
  }

  &__name {
    font-size: 0.92rem;
    font-weight: 600;
    color: var(--crm-color-ink);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  &__date {
    font-size: 0.72rem;
    color: var(--crm-color-muted);
    flex-shrink: 0;
  }

  &__bottom-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
  }

  &__preview {
    font-size: 0.8rem;
    color: var(--crm-color-muted);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  &__meta-icons {
    display: flex;
    align-items: center;
    flex-shrink: 0;
  }
}

.xf-unread-pill {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  color: #ffffff;
  font-weight: 800;
  font-size: 0.68rem;
  min-width: 18px;
  height: 18px;
  padding: 0 5px;
  border-radius: 9px;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 0 8px rgba(16, 185, 129, 0.4);
}

.xf-empty-list {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 40px 16px;
  color: var(--crm-color-muted);
  font-size: 0.85rem;
}
</style>
