<template>
  <div class="inbox-page-layout">
    <!-- Columna 1: Lista de Tickets y Filtros -->
    <InboxTicketList
      :conversations="filteredConversations"
      :selected-id="selectedConversationId"
      :active-tab="activeTab"
      :search="searchQuery"
      :loading="conversationsQuery.isLoading.value"
      :attending-count="attendingCount"
      :pending-count="pendingCount"
      @update:active-tab="onTabChange"
      @update:search="searchQuery = $event"
      @select="selectConversation"
      @create-ticket="isNewTicketModalOpen = true"
    />

    <!-- Columna 2: Chat en Vivo o Pantalla Vacía de Selección -->
    <div v-if="selectedConversation" class="inbox-center-area">
      <InboxChatThread
        :conversation="selectedConversation"
        :is-action-loading="isActionPending"
        :is-sending="mutations.whatsappMessageMutation.isPending.value || mutations.instagramMessageMutation.isPending.value || mutations.messageMutation.isPending.value"
        @accept="handleAcceptTicket"
        @transfer="isTransferModalOpen = true"
        @close="handleCloseTicket"
        @toggle-profile="isProfileOpen = !isProfileOpen"
        @send-message="handleSendMessage"
      />
    </div>

    <!-- Empty Selection State (si no hay conversación activa seleccionada) -->
    <div v-else class="inbox-center-area inbox-center-empty">
      <div class="column items-center text-center q-pa-xl text-grey-5">
        <div class="empty-chat-icon q-mb-md">
          <q-icon name="sym_r_forum" size="44px" color="teal-4" />
        </div>
        <div class="text-h6 text-weight-bold text-white">Bandeja Omnicanal de Atención</div>
        <p class="text-caption text-grey-4 q-mt-xs" style="max-width: 380px">
          Selecciona una conversación del listado izquierdo para responder o iniciar atención en tiempo real.
        </p>
      </div>
    </div>

    <!-- Columna 3: Perfil 360° del Contacto (Colapsable) -->
    <InboxContactSidebar
      v-if="isProfileOpen && selectedConversation"
      :conversation="selectedConversation"
      @close="isProfileOpen = false"
      @transfer="isTransferModalOpen = true"
      @resolve="handleCloseTicket"
    />

    <!-- Diálogos Desacoplados -->
    <TicketTransferDialog
      v-model="isTransferModalOpen"
      :loading="mutations.transferMutation.isPending.value"
      @submit="handleTransferTicket"
    />

    <ConversationFormDialog
      v-model="isNewTicketModalOpen"
      :contacts="formOptions.contactsQuery.data.value ?? []"
      :companies="formOptions.companiesQuery.data.value ?? []"
      :users="formOptions.usersQuery.data.value ?? []"
      :loading="mutations.createMutation.isPending.value"
      @submit="handleTicketSubmit"
    />

  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useAppNotify } from '@/shared/composables/useAppNotify'
import {
  useConversation,
  useConversationFormOptions,
  useConversationMutations,
  useConversations,
} from '../composables/useConversations'
import type { Conversation, CreateConversationPayload, TransferTicketPayload } from '../types/conversation.types'

import InboxTicketList from '../components/InboxTicketList.vue'
import InboxChatThread from '../components/InboxChatThread.vue'
import InboxContactSidebar from '../components/InboxContactSidebar.vue'
import TicketTransferDialog from '../components/TicketTransferDialog.vue'
import ConversationFormDialog from './ConversationFormDialog.vue'

const notify = useAppNotify()
const formOptions = useConversationFormOptions()

// Estados y Filtros
const activeTab = ref<'attending' | 'pending' | 'closed'>('attending')
const searchQuery = ref('')
const selectedConversationId = ref<string | null>(null)
const isProfileOpen = ref(true)
const isTransferModalOpen = ref(false)
const isNewTicketModalOpen = ref(false)

// Query de lista de conversaciones con refetch continuo para tiempo real
const conversationsQuery = useConversations(computed(() => ({
  search: searchQuery.value || undefined,
})))

const allConversations = computed<Conversation[]>(() => {
  return conversationsQuery.data.value?.data ?? []
})

// Contadores de tickets
const attendingCount = computed(() => {
  return allConversations.value.filter(c => c.status === 'open').length
})

const pendingCount = computed(() => {
  return allConversations.value.filter(c => c.status === 'pending').length
})

// Filtrar según el tab activo
const filteredConversations = computed(() => {
  const targetStatus = activeTab.value === 'attending'
    ? 'open'
    : activeTab.value === 'pending'
      ? 'pending'
      : 'closed'

  return allConversations.value.filter(c => {
    return c.status === targetStatus
  })
})

// Query de la conversación activa
const selectedIdForQuery = computed(() => selectedConversationId.value || '')
const activeConversationQuery = useConversation(selectedIdForQuery)
const selectedConversation = computed(() => activeConversationQuery.data.value ?? null)

// Mutaciones
const mutations = useConversationMutations(selectedIdForQuery)

const isActionPending = computed(() => {
  return (
    mutations.acceptMutation.isPending.value ||
    mutations.closeMutation.isPending.value ||
    mutations.transferMutation.isPending.value
  )
})

function onTabChange(tab: 'attending' | 'pending' | 'closed') {
  activeTab.value = tab
  // Si la conversación seleccionada no coincide con el nuevo tab, reset
  if (selectedConversation.value) {
    const expected = tab === 'attending' ? 'open' : tab === 'pending' ? 'pending' : 'closed'
    if (selectedConversation.value.status !== expected) {
      selectedConversationId.value = null
    }
  }
}

function selectConversation(conv: Conversation) {
  selectedConversationId.value = conv.id
}

async function handleAcceptTicket() {
  if (!selectedConversationId.value) return
  try {
    await mutations.acceptMutation.mutateAsync(selectedConversationId.value)
    notify.success({ message: 'Ticket aceptado. Ahora estás atendiendo esta conversación.' })
    activeTab.value = 'attending'
  } catch {
    notify.error({ message: 'Error al aceptar el ticket.' })
  }
}

async function handleCloseTicket() {
  if (!selectedConversationId.value) return
  try {
    await mutations.closeMutation.mutateAsync({ id: selectedConversationId.value })
    notify.info({ message: 'Ticket resuelto y finalizado.' })
  } catch {
    notify.error({ message: 'Error al finalizar el ticket.' })
  }
}

async function handleTransferTicket(payload: TransferTicketPayload) {
  if (!selectedConversationId.value) return
  try {
    await mutations.transferMutation.mutateAsync({
      id: selectedConversationId.value,
      payload,
    })
    notify.success({ message: 'Ticket transferido exitosamente.' })
    isTransferModalOpen.value = false
  } catch {
    notify.error({ message: 'Error al transferir el ticket.' })
  }
}

async function handleTicketSubmit(payload: CreateConversationPayload) {
  try {
    const created = await mutations.createMutation.mutateAsync(payload)
    isNewTicketModalOpen.value = false
    selectedConversationId.value = created.id
    notify.success({ message: 'Conversación creada exitosamente.' })
  } catch {
    notify.error({ message: 'Error al crear conversación.' })
  }
}


async function handleSendMessage({ body, is_internal }: { body: string; is_internal: boolean }) {
  if (!selectedConversationId.value) return
  try {
    if (is_internal) {
      await mutations.messageMutation.mutateAsync({
        id: selectedConversationId.value,
        payload: { body },
      })
      notify.info({ message: 'Nota interna agregada.' })
    } else if (selectedConversation.value?.channel === 'instagram') {
      await mutations.instagramMessageMutation.mutateAsync({
        id: selectedConversationId.value,
        payload: { body },
      })
      notify.success({ message: 'Mensaje directo enviado a Instagram.' })
    } else {
      await mutations.whatsappMessageMutation.mutateAsync({
        id: selectedConversationId.value,
        payload: { body },
      })
    }
  } catch (err: any) {
    const errorMsg = err?.response?.data?.message || 'Error al enviar el mensaje.'
    notify.error({ message: errorMsg })
  }
}

// Auto-seleccionar el primer ticket si no hay ninguno seleccionado

watch(
  filteredConversations,
  (list) => {
    if (!selectedConversationId.value && list.length > 0) {
      selectedConversationId.value = list[0].id
    }
  },
  { immediate: true },
)
</script>

<style scoped lang="scss">
.inbox-page-layout {
  display: flex;
  height: calc(100vh - 52px);
  overflow: hidden;
  background: var(--crm-bg-app);
}

.inbox-center-area {
  flex: 1;
  display: flex;
  flex-direction: column;
  height: 100%;
  min-width: 0;
}

.inbox-center-empty {
  align-items: center;
  justify-content: center;
  background: var(--crm-bg-app);
}

.empty-chat-icon {
  width: 72px;
  height: 72px;
  border-radius: 16px;
  background: var(--crm-color-primary-soft);
  display: flex;
  align-items: center;
  justify-content: center;
  border: 1px solid rgba(16, 185, 129, 0.2);
}
</style>
