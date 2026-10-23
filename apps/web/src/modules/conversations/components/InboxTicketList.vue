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

      <!-- Selector de Modo de Búsqueda: Conversaciones vs Contactos -->
      <div class="row items-center justify-between q-mb-xs">
        <span class="text-caption text-grey-5" style="font-size: 0.7rem">
          {{ searchScope === 'contacts' ? 'Directorio de Contactos' : 'Conversaciones' }}
        </span>
        <q-btn-toggle
          v-model="searchScope"
          dense
          rounded
          unelevated
          toggle-color="teal-9"
          toggle-text-color="teal-2"
          color="dark"
          text-color="grey-5"
          size="xs"
          class="inbox-scope-toggle"
          :options="[
            { label: 'Chats', value: 'conversations', icon: 'sym_r_forum' },
            { label: 'Contactos', value: 'contacts', icon: 'sym_r_person' },
          ]"
        />
      </div>

      <!-- Barra de Búsqueda -->
      <q-input
        v-model="searchModel"
        dense
        outlined
        dark
        :placeholder="searchScope === 'contacts' ? 'Buscar contacto en CRM (nombre, tel)...' : 'Buscar por contacto o mensaje...'"
        class="inbox-search-input"
        debounce="200"
      >
        <template #prepend>
          <q-icon :name="searchScope === 'contacts' ? 'sym_r_person_search' : 'sym_r_search'" size="16px" :color="searchScope === 'contacts' ? 'teal-4' : 'grey-5'" />
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

      <!-- Tabs de Estado Minimalistas (Modo Conversaciones) -->
      <div v-if="searchScope === 'conversations'" class="inbox-tabs q-mt-sm">
        <button
          v-if="searchModel"
          type="button"
          class="inbox-tab"
          :class="{ 'inbox-tab--active': activeTab === 'all' }"
          @click="emit('update:activeTab', 'all')"
        >
          <span>Todos</span>
          <span v-if="(allCount ?? 0) > 0" class="inbox-tab-count">{{ allCount }}</span>
        </button>

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
          <span v-if="searchModel && (closedCount ?? 0) > 0" class="inbox-tab-count">{{ closedCount }}</span>
        </button>
      </div>
    </div>

    <!-- Lista de Contactos (Modo Directorio de Contactos) -->
    <div v-if="searchScope === 'contacts'" class="inbox-ticket-list__items scroll">
      <div v-if="loadingContacts" class="row justify-center q-pa-lg">
        <q-spinner-dots size="32px" color="teal-4" />
      </div>

      <div
        v-else-if="searchedContacts.length === 0"
        class="column items-center justify-center q-pa-xl text-center text-grey-5"
      >
        <q-icon name="sym_r_person_search" size="36px" class="q-mb-xs text-grey-6" />
        <div class="text-caption text-weight-medium">
          {{ searchModel ? 'No se encontraron contactos en el CRM' : 'Escribe para buscar contactos en el directorio...' }}
        </div>
      </div>

      <div
        v-for="contact in searchedContacts"
        v-else
        :key="contact.id"
        class="ticket-row contact-search-row cursor-pointer"
        @click="emit('select-contact', contact)"
      >
        <div class="relative-position">
          <q-avatar size="38px" class="ticket-avatar bg-teal-10 text-teal-2">
            {{ getInitials(contact.name || contact.first_name || 'C') }}
          </q-avatar>
        </div>

        <div class="ticket-content col ellipsis q-ml-sm">
          <div class="row items-center justify-between no-wrap">
            <span class="ticket-name text-weight-medium text-white ellipsis">
              {{ contact.name || contact.first_name + ' ' + (contact.last_name || '') }}
            </span>
          </div>

          <div class="row items-center q-gutter-x-xs text-caption text-grey-4 q-mt-xs">
            <q-icon v-if="contact.phone" name="sym_r_call" size="12px" color="teal-4" />
            <span v-if="contact.phone" class="font-mono">{{ contact.phone }}</span>
            <span v-if="contact.phone && contact.email" class="text-grey-6">•</span>
            <span v-if="contact.email" class="ellipsis">{{ contact.email }}</span>
          </div>

          <div v-if="contact.tags?.length" class="row q-gutter-xs q-mt-xs">
            <span
              v-for="t in contact.tags.slice(0, 3)"
              :key="t.id || t.name"
              class="text-caption text-teal-3 font-mono"
              style="font-size: 0.65rem;"
            >
              #{{ t.name }}
            </span>
          </div>
        </div>

        <div class="column items-end justify-center q-ml-xs">
          <q-btn
            flat
            round
            dense
            size="sm"
            color="teal-4"
            icon="sym_r_chat"
          >
            <q-tooltip>Abrir o iniciar conversación</q-tooltip>
          </q-btn>
        </div>
      </div>
    </div>

    <!-- Lista de Conversaciones con Scroll (Modo Chats) -->
    <div v-else class="inbox-ticket-list__items scroll">
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
            <div class="row items-center no-wrap ellipsis col">
              <span class="ticket-name ellipsis">{{ item.contact?.name || item.subject || 'Contacto' }}</span>
              <q-badge
                v-if="searchModel || activeTab === 'all'"
                :color="item.status === 'pending' || !item.assigned_to_user_id ? 'amber-9' : item.status === 'open' ? 'teal-9' : 'grey-8'"
                :text-color="item.status === 'pending' || !item.assigned_to_user_id ? 'dark' : item.status === 'open' ? 'teal-2' : 'grey-3'"
                :label="item.status === 'pending' || !item.assigned_to_user_id ? 'En espera' : item.status === 'open' ? 'Atendiendo' : 'Cerrado'"
                class="q-ml-xs text-caption font-mono"
                style="font-size: 0.6rem; padding: 1px 4px;"
              />
            </div>
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
                :phone-number="item.channel_account?.display_phone_number"
                size="xs"
              />
              <span
                v-if="item.assignee?.name"
                class="ticket-assignee-chip ellipsis"
                :title="`Asignado a: ${item.assignee.name}`"
              >
                👤 {{ item.assignee.name }}
              </span>
              <span
                v-else
                class="ticket-unassigned-chip ellipsis"
                title="Sin operador asignado (en espera de atención)"
              >
                ⚠️ Sin asignar
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
import { computed, ref, watch } from 'vue'
import { http } from '@/shared/api/http'
import SocialChannelBadge from '@/shared/components/SocialChannelBadge.vue'
import TicketQuickInfoDialog from './TicketQuickInfoDialog.vue'
import type { Conversation } from '../types/conversation.types'

const props = defineProps<{
  conversations: Conversation[]
  selectedId: string | null
  activeTab: 'all' | 'attending' | 'pending' | 'closed'
  search: string
  loading?: boolean
  attendingCount?: number
  pendingCount?: number
  allCount?: number
  closedCount?: number
  isMobile?: boolean
}>()

const emit = defineEmits<{
  (e: 'update:activeTab', tab: 'all' | 'attending' | 'pending' | 'closed'): void
  (e: 'update:search', val: string): void
  (e: 'select', conv: Conversation): void
  (e: 'select-contact', contact: any): void
  (e: 'create-ticket'): void
}>()

const searchModel = computed({
  get: () => props.search,
  set: (val: string) => emit('update:search', val),
})

const totalTickets = computed(() => props.conversations.length)

// Modo de búsqueda: 'conversations' (en la bandeja) o 'contacts' (directorio CRM)
const searchScope = ref<'conversations' | 'contacts'>('conversations')
const searchedContacts = ref<any[]>([])
const loadingContacts = ref(false)

async function searchContacts(query: string) {
  if (!query || query.trim().length === 0) {
    searchedContacts.value = []
    return
  }
  loadingContacts.value = true
  try {
    const res = await http.get('/contacts', {
      params: {
        search: query.trim(),
        per_page: 25,
      },
    })
    searchedContacts.value = res.data?.data || []
  } catch (err) {
    console.error('Error searching contacts', err)
  } finally {
    loadingContacts.value = false
  }
}

watch(
  [searchModel, searchScope],
  ([query, scope]) => {
    if (scope === 'contacts') {
      searchContacts(query)
    }
  },
)

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
    case 'email': return '#6366f1'
    case 'manual': return '#64748b'
    case 'whatsapp': default: return '#10b981'
  }
}

function getChannelIcon(channel?: string) {
  switch (channel) {
    case 'facebook': return 'sym_r_public'
    case 'instagram': return 'sym_r_photo_camera'
    case 'tiktok': return 'sym_r_music_note'
    case 'email': return 'sym_r_mail'
    case 'manual': return 'sym_r_person'
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

  @media (max-width: 899px) {
    width: 100% !important;
    min-width: 100% !important;
    max-width: 100% !important;
    border-right: none !important;
  }
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

.ticket-unassigned-chip {
  font-size: 0.68rem;
  background: rgba(245, 158, 11, 0.12);
  color: #fbbf24;
  border: 1px solid rgba(245, 158, 11, 0.25);
  border-radius: 4px;
  padding: 1px 5px;
  max-width: 90px;
  white-space: nowrap;
}

.inbox-scope-toggle {
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid var(--crm-color-border);
  padding: 1px;
}

.contact-search-row {
  transition: background var(--crm-transition-fast);

  &:hover {
    background: rgba(16, 185, 129, 0.08);
  }
}
</style>
