<template>
  <aside class="inbox-ticket-list">
    <!-- Encabezado de la Columna 1 -->
    <div class="inbox-ticket-list__header q-pa-md">
      <div class="row items-center justify-between q-mb-sm">
        <div class="row items-center q-gutter-x-xs">
          <span class="text-subtitle1 text-weight-bold text-white">Bandeja</span>
          <q-badge color="teal-9" text-color="teal-2" rounded class="q-px-xs">
            {{ totalTickets }}
          </q-badge>
        </div>

        <q-btn
          flat
          round
          dense
          icon="sym_r_add_comment"
          color="teal-4"
          size="sm"
          @click="emit('create-ticket')"
        >
          <q-tooltip>Nueva conversación</q-tooltip>
        </q-btn>
      </div>

      <!-- Barra de Búsqueda -->
      <q-input
        v-model="searchModel"
        dense
        outlined
        dark
        placeholder="Buscar conversación..."
        class="inbox-search-input"
        debounce="200"
      >
        <template #prepend>
          <q-icon name="sym_r_search" size="16px" class="text-grey-5" />
        </template>
        <template v-if="searchModel" #append>
          <q-icon
            name="sym_r_close"
            size="14px"
            class="cursor-pointer text-grey-5"
            @click="searchModel = ''"
          />
        </template>
      </q-input>

      <!-- Tabs de Estado Minimalistas -->
      <div class="inbox-tabs q-mt-sm">
        <button
          type="button"
          class="inbox-tab"
          :class="{ 'inbox-tab--active': activeTab === 'attending' }"
          @click="emit('update:activeTab', 'attending')"
        >
          <span>Atendiendo</span>
          <span v-if="(attendingCount ?? 0) > 0" class="inbox-tab-count">{{ attendingCount }}</span>
        </button>

        <button
          type="button"
          class="inbox-tab"
          :class="{ 'inbox-tab--active': activeTab === 'pending' }"
          @click="emit('update:activeTab', 'pending')"
        >
          <span>En espera</span>
          <span v-if="(pendingCount ?? 0) > 0" class="inbox-tab-count inbox-tab-count--amber">{{ pendingCount }}</span>
        </button>


        <button
          type="button"
          class="inbox-tab"
          :class="{ 'inbox-tab--active': activeTab === 'closed' }"
          @click="emit('update:activeTab', 'closed')"
        >
          <span>Cerrados</span>
        </button>
      </div>
    </div>

    <!-- Lista de Conversaciones con Scroll -->
    <div class="inbox-ticket-list__items scroll">
      <div v-if="loading" class="row justify-center q-pa-lg">
        <q-spinner-dots size="32px" color="primary" />
      </div>

      <div
        v-else-if="conversations.length === 0"
        class="column items-center justify-center q-pa-xl text-center text-grey-5"
      >
        <q-icon name="sym_r_forum" size="36px" class="q-mb-xs text-grey-6" />
        <div class="text-caption text-weight-medium">Sin conversaciones en esta bandeja</div>
      </div>

      <div
        v-for="item in conversations"
        v-else
        :key="item.id"
        class="ticket-row"
        :class="{ 'ticket-row--active': selectedId === item.id }"
        @click="emit('select', item)"
      >
        <!-- Avatar del Contacto con Badge de Canal -->
        <div class="relative-position">
          <q-avatar size="38px" class="ticket-avatar">
            {{ getInitials(item.contact?.name || item.subject || 'C') }}
          </q-avatar>
          <q-badge
            floating
            rounded
            dense
            :style="{ background: getChannelColor(item.channel) }"
            class="channel-mini-badge"
          >
            <q-icon :name="getChannelIcon(item.channel)" size="10px" color="white" />
          </q-badge>
        </div>

        <!-- Información Central -->
        <div class="ticket-content">
          <div class="row items-center justify-between no-wrap">
            <span class="ticket-name ellipsis">{{ item.contact?.name || item.subject || 'Contacto' }}</span>
            <div class="row items-center q-gutter-x-xs no-wrap">
              <span class="ticket-time">{{ formatTime(item.last_message_at) }}</span>
              <!-- BOTÓN DE INFORMACIÓN RÁPIDA (WHATICKET) -->
              <q-btn
                flat
                round
                dense
                size="xs"
                icon="sym_r_info"
                color="teal-4"
                class="ticket-info-btn"
                @click.stop="openQuickInfo(item)"
              >
                <q-tooltip anchor="top middle" self="bottom middle">
                  Ver información y procedencia sin entrar al chat
                </q-tooltip>
              </q-btn>
            </div>
          </div>

          <div class="row items-center justify-between no-wrap q-mt-xs">
            <span class="ticket-snippet ellipsis col">
              {{ item.latest_message?.body || 'Sin mensajes recientes' }}
            </span>

            <div class="row items-center q-gutter-x-xs no-wrap q-ml-xs">
              <SocialChannelBadge
                :channel="item.channel"
                :account-name="item.channel_account?.name"
                size="xs"
              />
              <span
                v-if="item.assignee?.name"
                class="ticket-assignee-chip ellipsis"
                :title="`Asignado a: ${item.assignee.name}`"
              >
                👤 {{ item.assignee.name }}
              </span>
              <span v-if="item.unread_count && item.unread_count > 0" class="unread-badge">
                {{ item.unread_count }}
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal de Información Rápida & Procedencia (Sin entrar al chat) -->
    <TicketQuickInfoDialog
      v-model="isQuickInfoOpen"
      :conversation="selectedQuickInfoConversation"
      @open-chat="handleOpenChatFromDialog"
    />
  </aside>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import SocialChannelBadge from '@/shared/components/SocialChannelBadge.vue'
import TicketQuickInfoDialog from './TicketQuickInfoDialog.vue'
import type { Conversation } from '../types/conversation.types'

const props = defineProps<{
  conversations: Conversation[]
  selectedId: string | null
  activeTab: 'attending' | 'pending' | 'closed'
  search: string
  loading?: boolean
  attendingCount?: number
  pendingCount?: number
}>()

const emit = defineEmits<{
  (e: 'update:activeTab', tab: 'attending' | 'pending' | 'closed'): void
  (e: 'update:search', val: string): void
  (e: 'select', conv: Conversation): void
  (e: 'create-ticket'): void
}>()

const searchModel = computed({
  get: () => props.search,
  set: (val: string) => emit('update:search', val),
})

const totalTickets = computed(() => props.conversations.length)

const isQuickInfoOpen = ref(false)
const selectedQuickInfoConversation = ref<Conversation | null>(null)

function openQuickInfo(item: Conversation) {
  selectedQuickInfoConversation.value = item
  isQuickInfoOpen.value = true
}

function handleOpenChatFromDialog(item: Conversation) {
  emit('select', item)
}

function getInitials(name: string): string {
  if (!name) return 'WA'
  const parts = name.trim().split(' ')
  if (parts.length >= 2) {
    return (parts[0][0] + parts[1][0]).toUpperCase()
  }
  return name.slice(0, 2).toUpperCase()
}

function formatTime(dateStr: string | null): string {
  if (!dateStr) return ''
  try {
    const d = new Date(dateStr)
    const now = new Date()
    const isToday = d.toDateString() === now.toDateString()
    if (isToday) {
      return d.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' })
    }
    return d.toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit' })
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

function getChannelIcon(channel?: string) {
  switch (channel) {
    case 'facebook': return 'sym_r_public'
    case 'instagram': return 'sym_r_photo_camera'
    case 'tiktok': return 'sym_r_music_note'
    case 'whatsapp': default: return 'sym_r_chat'
  }
}
</script>

<style scoped lang="scss">
.channel-mini-badge {
  top: -2px;
  right: -2px;
  padding: 2px;
  min-height: unset;
  border: 1px solid var(--crm-bg-sidebar);
}

.inbox-ticket-list {
  width: 340px;
  min-width: 320px;
  height: 100%;
  display: flex;
  flex-direction: column;
  background: var(--crm-bg-sidebar);
  border-right: 1px solid var(--crm-color-border);
}

.inbox-ticket-list__header {
  border-bottom: 1px solid var(--crm-color-border);
}

.inbox-tabs {
  display: flex;
  gap: 4px;
  background: rgba(255, 255, 255, 0.03);
  padding: 3px;
  border-radius: 8px;
  border: 1px solid var(--crm-color-border);
}

.inbox-tab {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 4px;
  padding: 5px 6px;
  background: transparent;
  border: none;
  border-radius: 6px;
  color: var(--crm-color-muted);
  font-size: 0.76rem;
  font-weight: 500;
  cursor: pointer;
  transition: all var(--crm-transition-fast);

  &:hover {
    color: var(--crm-color-ink);
  }

  &--active {
    background: var(--crm-color-primary-soft);
    color: var(--crm-color-primary);
    font-weight: 600;
  }
}

.inbox-tab-count {
  font-size: 0.65rem;
  font-weight: 700;
  padding: 1px 5px;
  border-radius: 10px;
  background: rgba(16, 185, 129, 0.2);
  color: var(--crm-color-primary);

  &--amber {
    background: rgba(245, 158, 11, 0.2);
    color: #f59e0b;
  }
}

.inbox-ticket-list__items {
  flex: 1;
  overflow-y: auto;
}

.ticket-row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 14px;
  border-bottom: 1px solid var(--crm-color-border);
  cursor: pointer;
  transition: all var(--crm-transition-fast);

  &:hover {
    background: var(--crm-bg-card-hover);
  }

  &--active {
    background: var(--crm-color-primary-soft) !important;
    border-left: 3px solid var(--crm-color-primary);
  }
}

.ticket-avatar {
  background: var(--crm-color-surface-elevated);
  color: var(--crm-color-primary);
  font-size: 0.78rem;
  font-weight: 700;
  border: 1px solid var(--crm-color-border);
  flex-shrink: 0;
}

.ticket-content {
  flex: 1;
  min-width: 0;
}

.ticket-name {
  font-size: 0.85rem;
  font-weight: 600;
  color: var(--crm-color-ink);
}

.ticket-time {
  font-size: 0.7rem;
  color: var(--crm-color-dim);
  flex-shrink: 0;
}

.ticket-info-btn {
  opacity: 0.75;
  transition: opacity var(--crm-transition-fast), transform var(--crm-transition-fast);

  &:hover {
    opacity: 1;
    transform: scale(1.15);
  }
}

.ticket-snippet {
  font-size: 0.78rem;
  color: var(--crm-color-muted);
}

.unread-badge {
  font-size: 0.65rem;
  font-weight: 700;
  background: var(--crm-color-primary);
  color: #ffffff;
  padding: 1px 6px;
  border-radius: 99px;
  flex-shrink: 0;
}

.ticket-assignee-chip {
  font-size: 0.68rem;
  background: rgba(16, 185, 129, 0.12);
  color: #34d399;
  border: 1px solid rgba(16, 185, 129, 0.25);
  border-radius: 4px;
  padding: 1px 5px;
  max-width: 90px;
  white-space: nowrap;
}
</style>
