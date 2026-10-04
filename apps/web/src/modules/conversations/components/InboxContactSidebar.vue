<template>
  <aside class="inbox-contact-sidebar">
    <div class="inbox-contact-sidebar__header q-pa-md row items-center justify-between">
      <span class="text-subtitle2 text-weight-bold text-white">Detalle del Cliente</span>
      <q-btn flat round dense icon="sym_r_close" color="grey-4" size="sm" @click="emit('close')" />
    </div>

    <div class="inbox-contact-sidebar__content q-pa-md scroll">
      <!-- Tarjeta Principal del Perfil -->
      <div class="column items-center text-center q-mb-md">
        <q-avatar size="56px" class="contact-profile-avatar q-mb-sm">
          {{ getInitials(conversation.contact?.name || conversation.subject || 'W') }}
        </q-avatar>

        <div class="text-subtitle1 text-weight-bold text-white">
          {{ conversation.contact?.name || conversation.subject || 'Cliente' }}
        </div>

        <div v-if="conversation.contact?.phone" class="text-caption text-grey-4 font-mono q-mt-xs">
          {{ conversation.contact.phone }}
        </div>

        <div class="row items-center q-gutter-x-sm q-mt-sm">
          <q-btn
            v-if="conversation.contact?.phone"
            unelevated
            dense
            size="sm"
            color="positive"
            icon="sym_r_chat"
            label="WhatsApp Web"
            no-caps
            class="q-px-sm"
            :href="`https://wa.me/${cleanPhone(conversation.contact.phone)}`"
            target="_blank"
          />
        </div>
      </div>

      <q-separator dark class="q-my-md" style="border-color: var(--crm-color-border)" />

      <!-- Metadatos de la Conversación -->
      <div class="q-gutter-y-sm">
        <div class="info-row">
          <span class="info-label">Canal:</span>
          <span class="info-val text-capitalize">{{ conversation.channel }}</span>
        </div>

        <div class="info-row">
          <span class="info-label">Estado del Ticket:</span>
          <q-badge :color="statusBadgeColor" class="q-px-xs">
            {{ statusBadgeLabel }}
          </q-badge>
        </div>

        <div class="info-row">
          <span class="info-label">Agente Asignado:</span>
          <span class="info-val">{{ conversation.assignee?.name || conversation.assignment?.name || 'Sin asignar' }}</span>
        </div>

        <div v-if="conversation.company" class="info-row">
          <span class="info-label">Empresa B2B:</span>
          <span class="info-val text-primary">{{ conversation.company.name }}</span>
        </div>
      </div>

      <q-separator dark class="q-my-md" style="border-color: var(--crm-color-border)" />

      <!-- Pipeline Comercial & Deals (Fase 3) -->
      <div class="q-mb-md">
        <div class="row items-center justify-between q-mb-xs">
          <span class="text-caption text-weight-bold text-white">Pipeline Comercial</span>
          <q-btn
            flat
            dense
            round
            size="xs"
            color="teal-4"
            icon="sym_r_open_in_new"
            to="/app/deals"
          >
            <q-tooltip>Abrir Tablero Kanban</q-tooltip>
          </q-btn>
        </div>
        <q-btn
          outline
          dense
          no-caps
          color="teal-4"
          icon="sym_r_view_kanban"
          label="Ver en Tablero Kanban"
          class="full-width q-py-xs"
          to="/app/deals"
        />
      </div>

      <q-separator dark class="q-my-md" style="border-color: var(--crm-color-border)" />

      <!-- Acciones de Gestión -->
      <div class="q-gutter-y-xs">
        <q-btn
          v-if="conversation.status !== 'closed'"
          outline
          dense
          no-caps
          color="primary"
          icon="sym_r_swap_horiz"
          label="Transferir a otro agente"
          class="full-width q-py-xs"
          @click="emit('transfer')"
        />

        <q-btn
          v-if="conversation.status !== 'closed'"
          flat
          dense
          no-caps
          color="negative"
          icon="sym_r_done_all"
          label="Finalizar conversación"
          class="full-width q-py-xs"
          @click="emit('resolve')"
        />
      </div>
    </div>
  </aside>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { Conversation } from '../types/conversation.types'

const props = defineProps<{
  conversation: Conversation
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'transfer'): void
  (e: 'resolve'): void
}>()

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
      return 'Cerrado'
  }
})

function getInitials(name: string): string {
  if (!name) return 'WA'
  const parts = name.trim().split(' ')
  if (parts.length >= 2) return (parts[0][0] + parts[1][0]).toUpperCase()
  return name.slice(0, 2).toUpperCase()
}

function cleanPhone(phone: string): string {
  return phone.replace(/[^0-9]/g, '')
}
</script>

<style scoped lang="scss">
.inbox-contact-sidebar {
  width: 290px;
  min-width: 280px;
  height: 100%;
  display: flex;
  flex-direction: column;
  background: var(--crm-bg-sidebar);
  border-left: 1px solid var(--crm-color-border);
}

.inbox-contact-sidebar__header {
  height: 54px;
  border-bottom: 1px solid var(--crm-color-border);
}

.contact-profile-avatar {
  background: var(--crm-color-surface-elevated);
  color: var(--crm-color-primary);
  font-size: 1.1rem;
  font-weight: 700;
  border: 1px solid var(--crm-color-border);
}

.info-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 0.78rem;
}

.info-label {
  color: var(--crm-color-dim);
}

.info-val {
  color: var(--crm-color-ink);
  font-weight: 500;
}
</style>
