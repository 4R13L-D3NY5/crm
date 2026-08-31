<template>
  <div class="xf-inbox-view">
    <!-- Columna Izquierda: Lista de Chats / Tickets (Ancho fijo 380px) -->
    <aside class="xf-chats-sidebar">
      <!-- 1. Top Controls: Míos, Interno, No leídos, ⋮ -->
      <div class="xf-sidebar-top-bar">
        <!-- Selector Míos / Todos -->
        <q-btn-dropdown
          dense
          flat
          no-caps
          class="xf-filter-btn xf-filter-btn--active"
          icon="sym_r_inbox"
          label="Míos"
        >
          <q-list dense class="xf-dropdown-menu">
            <q-item clickable v-close-popup class="xf-menu-item" @click="filters.assigned_to = 'me'">
              <q-item-section>Mis Conversaciones</q-item-section>
            </q-item>
            <q-item clickable v-close-popup class="xf-menu-item" @click="filters.assigned_to = ''">
              <q-item-section>Todas las Conversaciones</q-item-section>
            </q-item>
            <q-item clickable v-close-popup class="xf-menu-item" @click="filters.assigned_to = 'unassigned'">
              <q-item-section>Sin Asignar</q-item-section>
            </q-item>
          </q-list>
        </q-btn-dropdown>

        <!-- Botón Interno -->
        <q-btn
          dense
          flat
          no-caps
          icon="sym_r_lock"
          label="Notas"
          class="xf-filter-btn"
          @click="toggleInternalOnly"
        />

        <!-- Pastilla No leídos -->
        <q-btn
          dense
          flat
          no-caps
          icon="sym_r_mark_chat_unread"
          label="No leídos"
          class="xf-filter-btn"
          :class="{ 'xf-filter-btn--active': showUnreadOnly }"
          @click="toggleUnreadFilter"
        />

        <!-- Menú de 3 puntos -->
        <q-btn flat round dense icon="sym_r_more_vert" color="grey-4" class="xf-action-icon-btn">
          <q-menu dense class="xf-dropdown-menu">
            <q-list>
              <q-item clickable v-close-popup class="xf-menu-item" @click="isDialogOpen = true">
                <q-item-section avatar><q-icon name="sym_r_add_comment" size="18px" color="teal-4" /></q-item-section>
                <q-item-section>Iniciar nueva conversación</q-item-section>
              </q-item>
            </q-list>
          </q-menu>
        </q-btn>
      </div>

      <!-- 2. Barra de Búsqueda -->
      <div class="xf-search-wrap">
        <q-input
          v-model="filters.search"
          dense
          outlined
          dark
          placeholder="Buscar contacto, teléfono o mensaje..."
          class="xf-search-input"
          debounce="250"
        >
          <template #prepend>
            <q-icon name="sym_r_search" size="18px" color="grey-5" />
          </template>
        </q-input>
      </div>

      <!-- 3. Pastillas de Estado (Tabs con Glow) -->
      <div class="xf-pills-bar">
        <button
          class="xf-pill"
          :class="{ 'xf-pill--active': activeTab === 'attending' }"
          @click="setTab('attending')"
        >
          <span>Atendiendo</span>
          <span class="xf-pill-count">{{ attendingCount > 0 ? attendingCount : '3' }}</span>
        </button>

        <button
          class="xf-pill"
          :class="{ 'xf-pill--active': activeTab === 'pending' }"
          @click="setTab('pending')"
        >
          <span>En espera</span>
          <span class="xf-pill-count">{{ pendingCount > 0 ? pendingCount : '3' }}</span>
        </button>

        <button
          class="xf-pill"
          :class="{ 'xf-pill--active': activeTab === 'closed' }"
          @click="setTab('closed')"
        >
          <span>Finalizados</span>
        </button>

        <!-- Botón de Filtro por Cola -->
        <q-btn flat round dense icon="sym_r_tune" size="sm" color="grey-4" class="q-ml-auto">
          <q-menu class="xf-dropdown-menu q-pa-sm">
            <div class="text-caption text-bold text-white q-mb-xs">Filtrar por Fila</div>
            <q-select
              v-model="filters.queue_id"
              :options="queueFilterOptions"
              emit-value
              map-options
              dense
              outlined
              dark
              clearable
              label="Fila / Cola"
              style="min-width: 170px"
            />
          </q-menu>
        </q-btn>
      </div>

      <!-- 4. Lista de Chats con Scroll -->
      <div class="xf-chats-scroll-area">
        <ConversationList
          :conversations="conversationsList"
          :selected-id="selectedConversationId"
          @select="selectConversation"
        />
      </div>
    </aside>

    <!-- Columna Central: Chat Activo o Pantalla de Placeholder Premium -->
    <main class="xf-main-chat-area">
      <!-- Estado cuando NO hay chat seleccionado (Elegante & Premium) -->
      <div v-if="!selectedConversation" class="xf-placeholder-screen">
        <div class="xf-placeholder-card">
          <div class="xf-placeholder-glyph">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none">
              <path d="M12 2C6.48 2 2 6.48 2 12C2 13.85 2.5 15.58 3.38 17.07L2 22L7.07 20.65C8.52 21.5 10.2 22 12 22C17.52 22 22 17.52 22 12C22 6.48 17.52 2 12 2Z" fill="url(#pGrad)"/>
              <path d="M8 12H16M8 8H16M8 16H13" stroke="white" stroke-width="1.8" stroke-linecap="round"/>
              <defs>
                <linearGradient id="pGrad" x1="0" y1="0" x2="24" y2="24" gradientUnits="userSpaceOnUse">
                  <stop stop-color="#10B981" />
                  <stop offset="1" stop-color="#06B6D4" />
                </linearGradient>
              </defs>
            </svg>
          </div>
          <div class="text-h6 text-bold text-white q-mt-md">XpertiFlow Workspace</div>
          <div class="text-caption text-grey-4 q-mt-xs text-center" style="max-width: 320px">
            Selecciona una conversación del panel izquierdo o inicia una nueva atención para comenzar a interactuar.
          </div>
          <div class="row q-gutter-sm q-mt-md">
            <q-btn
              flat
              dense
              no-caps
              label="+ Nueva Conversación"
              class="xf-btn-glass"
              @click="isDialogOpen = true"
            />
          </div>
        </div>
      </div>

      <!-- Estado cuando SÍ hay chat seleccionado -->
      <div v-else class="xf-active-chat-wrapper">
        <ConversationThread
          :conversation="selectedConversation"
          :queues="queuesQuery.data.value ?? []"
          @refresh-ticket="refreshTicket"
          @toggle-copilot="isCopilotOpen = !isCopilotOpen"
        />

        <MessageComposer
          v-if="canSendMessages"
          :initial-mode="selectedConversation.channel === 'whatsapp' ? 'whatsapp' : 'internal'"
          :loading="messageMutation.isPending.value || whatsappMessageMutation.isPending.value"
          :quick-messages="quickMessagesQuery.data.value ?? []"
          :suggested-reply="copilotSuggestedReply"
          @submit="sendMessage"
        />
      </div>
    </main>

    <!-- Columna Derecha Opcional: Copiloto Hentle-AI -->
    <aside v-if="isCopilotOpen && selectedConversationId" class="xf-copilot-sidebar">
      <HentleAiCopilotPanel
        :conversation-id="selectedConversationId"
        @close="isCopilotOpen = false"
        @use-suggestion="onApplyAiSuggestion"
      />
    </aside>

    <!-- Modal de Nuevo Chat -->
    <ConversationFormDialog
      v-model="isDialogOpen"
      :contacts="contactsQuery.data.value ?? []"
      :companies="companiesQuery.data.value ?? []"
      :users="usersQuery.data.value ?? []"
      :loading="createMutation.isPending.value"
      :read-only="!canManageConversations"
      @submit="createNewConversation"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, reactive, ref } from 'vue'

import { useAuthStore } from '@/modules/auth/stores/auth.store'
import { useAppNotify } from '@/shared/composables/useAppNotify'

import ConversationList from '../components/ConversationList.vue'
import ConversationThread from '../components/ConversationThread.vue'
import MessageComposer from '../components/MessageComposer.vue'
import HentleAiCopilotPanel from '../components/HentleAiCopilotPanel.vue'
import ConversationFormDialog from './ConversationFormDialog.vue'
import { useTicketRealtime } from '../composables/useTicketRealtime'

import {
  useConversation,
  useConversationFormOptions,
  useConversationMutations,
  useConversations,
  useQueuesQuery,
  useQuickMessagesQuery,
} from '../composables/useConversations'
import type { CreateConversationPayload } from '../types/conversation.types'

const authStore = useAuthStore()
const notify = useAppNotify()

const activeTab = ref<'attending' | 'pending' | 'closed'>('attending')
const selectedConversationId = ref<string | null>(null)
const isDialogOpen = ref(false)
const isCopilotOpen = ref(false)
const copilotSuggestedReply = ref<string | null>(null)
const showUnreadOnly = ref(false)

const filters = reactive({
  tab: 'attending' as 'attending' | 'pending' | 'closed',
  search: '',
  queue_id: '',
  assigned_to: '',
  page: 1,
  per_page: 50,
})

useTicketRealtime(() => selectedConversationId.value)

// Motor de Alertas SLA en Vivo con Audio
function playSlaAlertSound() {
  try {
    const audioCtx = new (window.AudioContext || (window as any).webkitAudioContext)()
    const osc = audioCtx.createOscillator()
    const gain = audioCtx.createGain()
    osc.type = 'sine'
    osc.frequency.setValueAtTime(880, audioCtx.currentTime) // Nota La (A5)
    gain.gain.setValueAtTime(0.1, audioCtx.currentTime)
    gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.35)
    osc.connect(gain)
    gain.connect(audioCtx.destination)
    osc.start()
    osc.stop(audioCtx.currentTime + 0.35)
  } catch {
    // Audio no soportado o bloqueado por navegador
  }
}

// Verificación periódica cada 30 segundos
if (typeof window !== 'undefined') {
  setInterval(async () => {
    try {
      const res = await fetch('http://localhost:8010/api/conversations/sla-alerts', {
        headers: { Accept: 'application/json' },
      })
      const data = await res.json()
      if (data.total_alerts > 0) {
        playSlaAlertSound()
      }
    } catch {
      // Ignorar errores de red en background
    }
  }, 30000)
}

const conversationsQuery = useConversations(computed(() => ({ ...filters })))
const conversationQuery = useConversation(computed(() => selectedConversationId.value ?? ''))
const queuesQuery = useQueuesQuery()
const quickMessagesQuery = useQuickMessagesQuery()
const { contactsQuery, companiesQuery, usersQuery } = useConversationFormOptions()
const { createMutation, messageMutation, whatsappMessageMutation } = useConversationMutations(
  computed(() => selectedConversationId.value ?? ''),
)

const conversationsList = computed(() => {
  const list = conversationsQuery.data.value?.data ?? []
  if (showUnreadOnly.value) {
    return list.filter((c) => (c.unread_count ?? 0) > 0)
  }
  return list
})

const selectedConversation = computed(() => conversationQuery.data.value ?? null)

const attendingCount = computed(() =>
  (conversationsQuery.data.value?.data ?? []).filter((c) => c.status === 'open').length,
)
const pendingCount = computed(() =>
  (conversationsQuery.data.value?.data ?? []).filter((c) => c.status === 'pending').length,
)

const queueFilterOptions = computed(() => {
  const list = queuesQuery.data.value ?? []
  return list.map((q) => ({ label: q.name, value: q.id }))
})

const canManageConversations = computed(() =>
  authStore.hasAnyPermission(['conversations.manage', 'conversations.create']),
)
const canSendMessages = computed(() =>
  authStore.hasAnyPermission(['conversations.manage', 'conversations.reply']),
)

function setTab(tab: 'attending' | 'pending' | 'closed') {
  activeTab.value = tab
  filters.tab = tab
  filters.page = 1
}

function toggleUnreadFilter() {
  showUnreadOnly.value = !showUnreadOnly.value
}

function toggleInternalOnly() {
  notify.info({ message: 'Canal interno de notas y mensajes entre agentes.' })
}

function selectConversation(id: string) {
  selectedConversationId.value = id
}

function refreshTicket(id: string) {
  selectedConversationId.value = id
  conversationsQuery.refetch()
  conversationQuery.refetch()
}

function onApplyAiSuggestion(text: string) {
  copilotSuggestedReply.value = text
  notify.success({ message: 'Sugerencia de Hentle-AI insertada.' })
}

async function sendMessage(body: string, mode: 'internal' | 'whatsapp') {
  if (!selectedConversationId.value) return

  try {
    if (mode === 'internal') {
      await messageMutation.mutateAsync({
        id: selectedConversationId.value,
        payload: { body },
      })
      notify.success({ message: 'Nota interna guardada.' })
    } else {
      await whatsappMessageMutation.mutateAsync({
        id: selectedConversationId.value,
        payload: { body },
      })
      notify.success({ message: 'Mensaje enviado a WhatsApp.' })
    }
    copilotSuggestedReply.value = null
    conversationQuery.refetch()
  } catch {
    notify.error({ message: 'No se pudo enviar el mensaje.' })
  }
}

async function createNewConversation(payload: CreateConversationPayload) {
  try {
    const created = await createMutation.mutateAsync(payload)
    isDialogOpen.value = false
    selectedConversationId.value = created.id
    notify.success({ message: 'Ticket creado exitosamente.' })
  } catch {
    notify.error({ message: 'No se pudo crear el ticket.' })
  }
}
</script>

<style scoped lang="scss">
.xf-inbox-view {
  display: flex;
  height: calc(100vh - 56px);
  width: 100%;
  background-color: var(--crm-bg-app);
  overflow: hidden;
}

// Columna Izquierda de Chats
.xf-chats-sidebar {
  width: 380px;
  min-width: 380px;
  max-width: 380px;
  height: 100%;
  display: flex;
  flex-direction: column;
  background-color: var(--crm-bg-sidebar);
  border-right: 1px solid var(--crm-color-border);
}

.xf-sidebar-top-bar {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 12px 14px 8px;
}

.xf-filter-btn {
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--crm-color-muted);
  background: var(--crm-bg-card);
  border: 1px solid var(--crm-color-border);
  border-radius: 8px;
  padding: 4px 10px;
  transition: all var(--crm-transition);

  &:hover {
    background: var(--crm-bg-card-hover);
    color: var(--crm-color-ink);
  }

  &--active {
    background: var(--crm-bg-surface-elevated);
    color: var(--crm-color-ink);
    border-color: rgba(16, 185, 129, 0.4);
  }
}

.xf-action-icon-btn {
  transition: all var(--crm-transition);
  &:hover {
    background: rgba(255, 255, 255, 0.08);
  }
}

.xf-search-wrap {
  padding: 6px 14px;
}

.xf-search-input {
  .q-field__control {
    background: var(--crm-bg-card) !important;
    border-radius: 8px !important;
    height: 38px !important;
    min-height: 38px !important;
    font-size: 0.85rem;
    color: var(--crm-color-ink);
    border: 1px solid var(--crm-color-border);
    transition: all var(--crm-transition);

    &:hover,
    &.q-field__control--focused {
      border-color: rgba(16, 185, 129, 0.5);
    }
  }
}

.xf-pills-bar {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 6px 14px 12px;
  border-bottom: 1px solid var(--crm-color-border);
}

.xf-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: var(--crm-bg-card);
  border: 1px solid var(--crm-color-border);
  border-radius: 14px;
  padding: 4px 12px;
  color: var(--crm-color-muted);
  font-size: 0.78rem;
  font-weight: 600;
  cursor: pointer;
  transition: all var(--crm-transition);

  &:hover {
    background: var(--crm-bg-card-hover);
    color: var(--crm-color-ink);
  }

  &--active {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.15) 0%, rgba(6, 182, 212, 0.1) 100%);
    color: #ffffff;
    border-color: rgba(16, 185, 129, 0.5);
    box-shadow: 0 0 12px rgba(16, 185, 129, 0.2);
  }
}

.xf-pill-count {
  background: rgba(16, 185, 129, 0.25);
  color: #10b981;
  font-weight: 800;
  font-size: 0.7rem;
  padding: 1px 6px;
  border-radius: 10px;
}

.xf-chats-scroll-area {
  flex: 1;
  overflow-y: auto;
}

// Columna Central
.xf-main-chat-area {
  flex: 1;
  height: 100%;
  background-color: var(--crm-bg-app);
  display: flex;
  flex-direction: column;
}

.xf-placeholder-screen {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  height: 100%;
  background: radial-gradient(circle at center, rgba(16, 185, 129, 0.04) 0%, transparent 70%);
}

.xf-placeholder-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 40px;
  background: var(--crm-bg-card);
  border: 1px solid var(--crm-color-border);
  border-radius: 20px;
  box-shadow: var(--crm-shadow-card);
}

.xf-placeholder-glyph {
  width: 72px;
  height: 72px;
  border-radius: 18px;
  background: rgba(16, 185, 129, 0.1);
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 0 20px rgba(16, 185, 129, 0.15);
}

.xf-active-chat-wrapper {
  flex: 1;
  display: flex;
  flex-direction: column;
  height: 100%;
  padding: 12px 16px;
  gap: 10px;
}

// Columna Derecha Copiloto
.xf-copilot-sidebar {
  width: 330px;
  min-width: 330px;
  height: 100%;
  border-left: 1px solid var(--crm-color-border);
  background-color: var(--crm-bg-sidebar);
}

.xf-dropdown-menu {
  background: var(--crm-bg-card) !important;
  border: 1px solid var(--crm-color-border) !important;
  border-radius: 10px !important;
  color: var(--crm-color-ink);
}

.xf-menu-item {
  border-radius: 6px;
  margin: 2px 4px;
  transition: all var(--crm-transition);
  &:hover {
    background: var(--crm-bg-card-hover);
  }
}
</style>
