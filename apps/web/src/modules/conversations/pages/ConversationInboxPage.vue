<template>
  <div class="inbox-page-layout">
    <!-- Columna 1: Lista de Tickets y Filtros (En móvil se oculta cuando hay un chat seleccionado) -->
    <InboxTicketList
      v-if="!isMobile || !selectedConversationId"
      :conversations="filteredConversations"
      :selected-id="selectedConversationId"
      :active-tab="activeTab"
      :search="searchQuery"
      :loading="conversationsQuery.isLoading.value"
      :attending-count="attendingCount"
      :pending-count="pendingCount"
      :all-count="allConversationsCount"
      :closed-count="closedCount"
      :is-mobile="isMobile"
      @update:active-tab="onTabChange"
      @update:search="searchQuery = $event"
      @select="selectConversation"
      @select-contact="handleSelectContactFromSearch"
      @create-ticket="isNewTicketModalOpen = true"
    />

    <!-- Columna 2: Chat en Vivo o Pantalla Vacía de Selección -->
    <div v-if="selectedConversation && (!isMobile || selectedConversationId)" class="inbox-center-area">
      <InboxChatThread
        :conversation="selectedConversation"
        :all-conversations="allConversations"
        @select-conversation="selectConversation"
        :is-action-loading="isActionPending"
        :is-sending="mutations.whatsappMessageMutation.isPending.value || mutations.facebookMessageMutation.isPending.value || mutations.instagramMessageMutation.isPending.value || mutations.messageMutation.isPending.value"
        :is-mobile="isMobile"
        @back="handleBackToList"
        @accept="handleAcceptTicket"
        @transfer="isTransferModalOpen = true"
        @release="handleReleaseTicket"
        @close="handleCloseTicket"
        @reopen="handleReopenTicket"
        @toggle-profile="isProfileOpen = !isProfileOpen"
        @send-message="handleSendMessage"
      />
    </div>

    <!-- Estado de carga al seleccionar una conversación -->
    <div
      v-else-if="selectedConversationId && activeConversationQuery.isLoading.value"
      class="inbox-center-area inbox-center-empty"
    >
      <div class="column items-center justify-center text-center q-pa-xl text-grey-5">
        <q-spinner-dots size="48px" color="teal-4" />
        <div class="text-caption text-grey-4 q-mt-md font-sans">Cargando conversación...</div>
      </div>
    </div>

    <!-- Estado de error al seleccionar una conversación -->
    <div
      v-else-if="selectedConversationId && activeConversationQuery.isError.value"
      class="inbox-center-area inbox-center-empty"
    >
      <div class="column items-center justify-center text-center q-pa-xl text-grey-5">
        <q-icon name="sym_r_error" size="44px" color="negative" class="q-mb-sm" />
        <div class="text-subtitle1 text-white">No se pudo cargar la conversación</div>
        <div class="text-caption text-grey-4 q-mt-xs">Es posible que el ticket no exista o haya sido archivado.</div>
        <q-btn flat color="teal-4" label="Volver a la bandeja" class="q-mt-md" @click="handleBackToList" />
      </div>
    </div>

    <!-- Empty Selection State (solo desktop si no hay conversación activa seleccionada) -->
    <div v-else-if="!isMobile" class="inbox-center-area inbox-center-empty">
      <div class="column items-center text-center q-pa-xl text-grey-5">
        <div class="empty-chat-icon q-mb-md">
          <q-icon name="sym_r_forum" size="44px" color="teal-4" />
        </div>
        <div class="text-h6 text-weight-bold text-white">Bandeja Multicanal de Atención</div>
        <p class="text-caption text-grey-4 q-mt-xs" style="max-width: 380px">
          Selecciona una conversación del listado izquierdo para responder o iniciar atención en tiempo real.
        </p>
      </div>
    </div>

    <!-- Columna 3: Perfil 360° del Contacto (Desktop) -->
    <InboxContactSidebar
      v-if="!isMobile && isProfileOpen && selectedConversation"
      :conversation="selectedConversation"
      @close="isProfileOpen = false"
      @transfer="isTransferModalOpen = true"
      @release="handleReleaseTicket"
      @resolve="handleCloseTicket"
      @reopen="handleReopenTicket"
    />

    <!-- Drawer/Modal Móvil: Perfil 360° del Contacto en Móvil -->
    <q-dialog
      v-if="isMobile"
      v-model="isProfileOpen"
      position="right"
      maximized
      transition-show="slide-left"
      transition-hide="slide-right"
    >
      <div class="mobile-profile-sheet bg-dark column no-wrap" style="width: 100vw; max-width: 340px; height: 100%;">
        <InboxContactSidebar
          v-if="selectedConversation"
          :conversation="selectedConversation"
        :all-conversations="allConversations"
        @select-conversation="selectConversation"
          :is-mobile="true"
          @close="isProfileOpen = false"
          @transfer="isTransferModalOpen = true"
          @release="handleReleaseTicket"
          @resolve="handleCloseTicket"
          @reopen="handleReopenTicket"
        />
      </div>
    </q-dialog>

    <!-- Diálogos Desacoplados -->
    <TicketTransferDialog
      v-model="isTransferModalOpen"
      :users="formOptions.usersQuery.data.value ?? []"
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
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useQuasar } from 'quasar'
import { useAppNotify } from '@/shared/composables/useAppNotify'
import { markConversationAsRead } from '../api/conversations.api'
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

const route = useRoute()
const router = useRouter()
const $q = useQuasar()
const notify = useAppNotify()
const formOptions = useConversationFormOptions()

// Detección de pantalla móvil (< 900px)
const isMobile = computed(() => $q.screen.width < 900)

// Estados y Filtros
const activeTab = ref<'all' | 'attending' | 'pending' | 'closed'>('attending')
const previousTabBeforeSearch = ref<'all' | 'attending' | 'pending' | 'closed'>('attending')
const searchQuery = ref((route.query.search as string) || (route.query.phone as string) || '')
const selectedConversationId = ref<string | null>((route.query.id as string) || null)
const isProfileOpen = ref(!$q.screen.lt.md)
const isTransferModalOpen = ref(false)
const isNewTicketModalOpen = ref(false)

// Query de lista de conversaciones con refetch continuo para tiempo real (per_page: 100 para no limitar a 20)
const conversationsQuery = useConversations(computed(() => ({
  search: searchQuery.value || undefined,
  per_page: 100,
})))

const allConversations = computed<Conversation[]>(() => {
  return conversationsQuery.data.value?.data ?? []
})

// Contadores de tickets (Alineados con la regla Whaticket: sin operador asignado siempre es 'En espera')
const attendingCount = computed(() => {
  return allConversations.value.filter(c => c.status === 'open' && (c.assigned_to_user_id || c.assignee)).length
})

const pendingCount = computed(() => {
  return allConversations.value.filter(c => c.status === 'pending' || (c.status === 'open' && !c.assigned_to_user_id && !c.assignee)).length
})

const closedCount = computed(() => {
  return allConversations.value.filter(c => c.status === 'closed').length
})

const allConversationsCount = computed(() => {
  return allConversations.value.length
})

// Filtrar según el tab activo (si está en 'all' o buscando, permite ver todas las coincidencias)
const filteredConversations = computed(() => {
  if (activeTab.value === 'all') {
    return allConversations.value
  }
  if (activeTab.value === 'attending') {
    return allConversations.value.filter(c => c.status === 'open' && (c.assigned_to_user_id || c.assignee))
  }
  if (activeTab.value === 'pending') {
    return allConversations.value.filter(c => c.status === 'pending' || (c.status === 'open' && !c.assigned_to_user_id && !c.assignee))
  }
  return allConversations.value.filter(c => c.status === 'closed')
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

// Sincronización bidireccional de ?id= en la URL con selectedConversationId
watch(
  () => route.query.id,
  (newId) => {
    const stringId = typeof newId === 'string' && newId.trim() !== '' ? newId.trim() : null
    if (stringId !== selectedConversationId.value) {
      selectedConversationId.value = stringId
    }
  },
  { immediate: true },
)

// Si el usuario entra sin id y no tiene tickets en Atendiendo pero sí en En espera, alternar automáticamente a 'pending'
const hasAutoSwitchedToPending = ref(false)
watch(
  [allConversations, attendingCount, pendingCount],
  ([all, attending, pending]) => {
    if (!route.query.id && !hasAutoSwitchedToPending.value && all.length > 0) {
      if (attending === 0 && pending > 0 && activeTab.value === 'attending') {
        activeTab.value = 'pending'
        hasAutoSwitchedToPending.value = true
      }
    }
  },
  { immediate: true },
)

// Sincronizar parámetro search/phone de la URL
watch(
  () => (route.query.search as string) || (route.query.phone as string),
  (val) => {
    if (typeof val === 'string' && val !== searchQuery.value) {
      searchQuery.value = val
    }
  },
)

// Si la conversación seleccionada se carga, alinear el tab activo al estado real del ticket
watch(
  selectedConversation,
  (conv) => {
    if (!conv) return
    const isAttending = conv.status === 'open' && (conv.assigned_to_user_id || conv.assignee)
    const isPending = conv.status === 'pending' || (conv.status === 'open' && !conv.assigned_to_user_id && !conv.assignee)
    const isClosed = conv.status === 'closed'

    if (isAttending && activeTab.value !== 'attending') {
      activeTab.value = 'attending'
    } else if (isPending && activeTab.value !== 'pending') {
      activeTab.value = 'pending'
    } else if (isClosed && activeTab.value !== 'closed') {
      activeTab.value = 'closed'
    }
  },
  { immediate: true },
)

// Al escribir en el buscador, alternar temporalmente al tab 'all' para no ocultar resultados de otros estados
watch(searchQuery, (newVal, oldVal) => {
  if (newVal && !oldVal) {
    if (activeTab.value !== 'all') {
      previousTabBeforeSearch.value = activeTab.value
      activeTab.value = 'all'
    }
  } else if (!newVal && oldVal) {
    if (activeTab.value === 'all') {
      activeTab.value = previousTabBeforeSearch.value
    }
  }
})

// Si se ingresó buscando un teléfono o término sin id previo, auto-seleccionar la primera coincidencia
watch(
  allConversations,
  (conversations) => {
    if (!selectedConversationId.value && searchQuery.value && conversations.length > 0) {
      selectConversation(conversations[0])
    }
  },
)

function onTabChange(tab: 'all' | 'attending' | 'pending' | 'closed') {
  activeTab.value = tab
  // Si la conversación seleccionada no coincide con el nuevo tab, limpiar selección
  if (tab !== 'all' && selectedConversation.value) {
    const isAttending = selectedConversation.value.status === 'open' && (selectedConversation.value.assigned_to_user_id || selectedConversation.value.assignee)
    const isPending = selectedConversation.value.status === 'pending' || (selectedConversation.value.status === 'open' && !selectedConversation.value.assigned_to_user_id && !selectedConversation.value.assignee)
    const isClosed = selectedConversation.value.status === 'closed'

    const matches = (tab === 'attending' && isAttending) ||
                    (tab === 'pending' && isPending) ||
                    (tab === 'closed' && isClosed)

    if (!matches) {
      selectedConversationId.value = null
      if (route.query.id) {
        const query = { ...route.query }
        delete query.id
        router.replace({ query })
      }
    }
  }
}

function handleSelectContactFromSearch(contact: any) {
  if (!contact?.id) return
  const existing = allConversations.value.find(
    (c) => c.contact?.id === contact.id || (contact.phone && c.contact?.phone === contact.phone)
  )
  if (existing) {
    selectConversation(existing)
    return
  }

  isNewTicketModalOpen.value = true
}

function handleBackToList() {
  selectedConversationId.value = null
  if (route.query.id) {
    const query = { ...route.query }
    delete query.id
    router.replace({ query })
  }
  if (isMobile.value) {
    isProfileOpen.value = false
  }
}

function selectConversation(conv: Conversation) {
  selectedConversationId.value = conv.id
  if (route.query.id !== conv.id) {
    router.replace({ query: { ...route.query, id: conv.id } })
  }
  if (conv.unread_count && conv.unread_count > 0) {
    conv.unread_count = 0
    markConversationAsRead(conv.id).catch(() => {})
  }
  if (isMobile.value) {
    isProfileOpen.value = false
  }
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

async function handleReopenTicket() {
  if (!selectedConversationId.value) return
  try {
    await mutations.reopenMutation.mutateAsync(selectedConversationId.value)
    notify.success({ message: 'Ticket reactivado. Ahora se encuentra en tu lista de Atendiendo.' })
    activeTab.value = 'attending'
  } catch {
    notify.error({ message: 'Error al reactivar el ticket.' })
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

async function handleReleaseTicket() {
  if (!selectedConversationId.value) return
  $q.dialog({
    title: 'Liberar Chat para Relevo',
    message: '¿Deseas liberar este chat? Se desasignará de tu nombre y pasará a la bandeja Sin Asignar / Pendientes para que el nuevo operador o equipo de turno pueda continuar la atención.',
    cancel: {
      label: 'Cancelar',
      flat: true,
      color: 'grey-4',
      noCaps: true,
    },
    ok: {
      label: 'Sí, liberar chat',
      color: 'amber-9',
      unelevated: true,
      noCaps: true,
    },
    dark: true,
  }).onOk(async () => {
    try {
      await mutations.releaseMutation.mutateAsync({
        id: selectedConversationId.value!,
        payload: { reason: 'Liberado voluntariamente para cambio de turno' },
      })
      notify.success({ message: 'Chat liberado a la bandeja general exitosamente.' })
      activeTab.value = 'pending'
    } catch {
      notify.error({ message: 'Error al liberar el chat.' })
    }
  })
}

async function handleTicketSubmit(payload: CreateConversationPayload) {
  try {
    const created = await mutations.createMutation.mutateAsync(payload)
    isNewTicketModalOpen.value = false
    selectedConversationId.value = created.id
    if (route.query.id !== created.id) {
      router.replace({ query: { ...route.query, id: created.id } })
    }
    notify.success({ message: 'Conversación creada exitosamente.' })
  } catch {
    notify.error({ message: 'Error al crear conversación.' })
  }
}


function handleSendMessage({
  body,
  is_internal,
  file,
  media_type,
}: {
  body: string
  is_internal: boolean
  file?: File | null
  media_type?: string | null
}) {
  if (!selectedConversationId.value) return
  const convId = selectedConversationId.value
  const channel = selectedConversation.value?.channel

  // Al responder el asesor, limpiar indicador no leído de inmediato
  if (selectedConversation.value) {
    selectedConversation.value.unread_count = 0
  }
  const currentTicket = allConversations.value.find(c => c.id === convId)
  if (currentTicket) {
    currentTicket.unread_count = 0
  }

  const onErrorHandler = (err: any) => {
    const errorMsg = err?.response?.data?.message || 'Error al enviar el mensaje.'
    notify.error({ message: errorMsg })
  }

  if (is_internal) {
    mutations.messageMutation.mutate({
      id: convId,
      payload: { body },
    }, {
      onSuccess: () => notify.info({ message: 'Nota interna agregada.' }),
      onError: onErrorHandler,
    })
  } else if (channel === 'facebook') {
    mutations.facebookMessageMutation.mutate({
      id: convId,
      payload: { body },
    }, {
      onError: onErrorHandler,
    })
  } else if (channel === 'instagram') {
    mutations.instagramMessageMutation.mutate({
      id: convId,
      payload: { body },
    }, {
      onError: onErrorHandler,
    })
  } else if (channel === 'tiktok') {
    mutations.whatsappMessageMutation.mutate({
      id: convId,
      payload: { body },
    }, {
      onError: onErrorHandler,
    })
  } else {
    if (file) {
      const formData = new FormData()
      formData.append('body', body || '')
      formData.append('file', file)
      if (media_type) formData.append('media_type', media_type)
      mutations.whatsappMessageMutation.mutate({
        id: convId,
        payload: formData,
      }, {
        onError: onErrorHandler,
      })
    } else {
      mutations.whatsappMessageMutation.mutate({
        id: convId,
        payload: { body },
      }, {
        onError: onErrorHandler,
      })
    }
  }
}

// Auto-seleccionar el primer ticket solo en desktop si no hay ninguno seleccionado ni query id
watch(
  filteredConversations,
  (list) => {
    if (!isMobile.value && !selectedConversationId.value && !route.query.id && list.length > 0) {
      selectedConversationId.value = list[0].id
      router.replace({ query: { ...route.query, id: list[0].id } })
    }
  },
  { immediate: true },
)

// Cuando carga la conversación seleccionada, sincronizar pestaña y marcar leída
watch(
  selectedConversation,
  (conv) => {
    if (!conv) return

    // Sincronizar automáticamente el tab con el estado real del ticket si no coincide
    const isAttending = conv.status === 'open' && (conv.assigned_to_user_id || conv.assignee)
    const isPending = conv.status === 'pending' || (conv.status === 'open' && !conv.assigned_to_user_id && !conv.assignee)
    const isClosed = conv.status === 'closed'

    if (isAttending && activeTab.value !== 'attending') {
      activeTab.value = 'attending'
    } else if (isPending && activeTab.value !== 'pending') {
      activeTab.value = 'pending'
    } else if (isClosed && activeTab.value !== 'closed') {
      activeTab.value = 'closed'
    }

    if (conv.unread_count && conv.unread_count > 0) {
      conv.unread_count = 0
      markConversationAsRead(conv.id).catch(() => {})
    }
  },
  { immediate: true },
)

// Auto-refresco del chat en vivo cada 2.5 segundos
let chatPollingTimer: ReturnType<typeof setInterval> | null = null

onMounted(() => {
  chatPollingTimer = setInterval(() => {
    conversationsQuery.refetch()
    if (selectedConversationId.value) {
      activeConversationQuery.refetch()
    }
  }, 2500)
})

onUnmounted(() => {
  if (chatPollingTimer) {
    clearInterval(chatPollingTimer)
  }
})
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
