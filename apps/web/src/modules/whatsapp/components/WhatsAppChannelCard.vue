<template>
  <q-card flat bordered class="whatsapp-channel-card">
    <q-card-section class="q-pb-sm">
      <div class="row items-start justify-between">
        <!-- Icono y Datos de Línea -->
        <div class="row items-center q-gutter-x-md">
          <div
            class="channel-icon"
            :class="`channel-icon--${account.status.toLowerCase()}`"
          >
            <q-icon name="sym_r_chat" size="22px" />
          </div>

          <div>
            <div class="channel-name ellipsis text-weight-bold text-white">
              {{ account.name }}
            </div>
            <div class="channel-number text-caption text-grey-4">
              {{ account.display_phone_number || account.phone_number_id || 'Sin número asignado' }}
            </div>
          </div>
        </div>

        <!-- Menú de Opciones -->
        <q-btn flat round dense icon="sym_r_more_vert" color="grey-5" size="sm">
          <q-menu dark dense class="channel-menu">
            <q-list>
              <q-item
                v-if="account.status === 'CONNECTED'"
                clickable
                v-close-popup
                class="text-amber-4"
                @click="emit('disconnect', account.id)"
              >
                <q-item-section avatar><q-icon name="sym_r_link_off" size="16px" /></q-item-section>
                <q-item-section>Desconectar</q-item-section>
              </q-item>
              <q-item
                v-else
                clickable
                v-close-popup
                class="text-teal-4"
                @click="emit('connect', account)"
              >
                <q-item-section avatar><q-icon name="sym_r_qr_code" size="16px" /></q-item-section>
                <q-item-section>Conectar vía QR</q-item-section>
              </q-item>
              <q-item
                clickable
                v-close-popup
                @click="emit('simulate', account)"
              >
                <q-item-section avatar><q-icon name="sym_r_send" size="16px" color="primary" /></q-item-section>
                <q-item-section>Simular Mensaje Entrante</q-item-section>
              </q-item>
              <q-separator dark class="q-my-xs" />
              <q-item
                clickable
                v-close-popup
                class="text-negative"
                @click="emit('delete', account.id)"
              >
                <q-item-section avatar><q-icon name="sym_r_delete" size="16px" color="negative" /></q-item-section>
                <q-item-section>Eliminar Línea</q-item-section>
              </q-item>
            </q-list>
          </q-menu>
        </q-btn>
      </div>

      <!-- Badge de Estado Minimalista -->
      <div class="row items-center justify-between q-mt-md">
        <div class="status-pill" :class="`status-pill--${account.status.toLowerCase()}`">
          <span class="status-dot"></span>
          <span>{{ statusLabel }}</span>
        </div>

        <div v-if="account.last_connected_at" class="text-caption text-grey-5" style="font-size: 0.7rem">
          {{ formatDate(account.last_connected_at) }}
        </div>
      </div>
    </q-card-section>

    <!-- Footer con Acciones Rápidas -->
    <q-separator dark class="q-mt-sm" style="border-color: var(--crm-color-border)" />
    <q-card-actions align="between" class="q-px-md q-py-sm">
      <q-btn
        flat
        dense
        size="sm"
        no-caps
        icon="sym_r_send"
        label="Simular Entrada"
        color="teal-4"
        @click="emit('simulate', account)"
      />

      <q-btn
        v-if="account.status === 'CONNECTED'"
        flat
        dense
        size="sm"
        no-caps
        icon="sym_r_link_off"
        label="Desconectar"
        color="grey-4"
        @click="emit('disconnect', account.id)"
      />
      <q-btn
        v-else
        unelevated
        dense
        size="sm"
        no-caps
        icon="sym_r_qr_code_scanner"
        label="Escanear QR"
        class="xf-btn-primary q-px-sm"
        @click="emit('connect', account)"
      />
    </q-card-actions>
  </q-card>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { WhatsAppAccount } from '../types/whatsapp.types'

const props = defineProps<{
  account: WhatsAppAccount
}>()

const emit = defineEmits<{
  (e: 'connect', account: WhatsAppAccount): void
  (e: 'disconnect', id: string): void
  (e: 'simulate', account: WhatsAppAccount): void
  (e: 'delete', id: string): void
}>()

const statusLabel = computed(() => {
  switch (props.account.status) {
    case 'CONNECTED':
      return 'Línea Conectada'
    case 'CONNECTING':
      return 'Sincronizando QR...'
    case 'DISCONNECTED':
    default:
      return 'Desconectada'
  }
})

function formatDate(dateStr: string) {
  try {
    const d = new Date(dateStr)
    return `Conectado ${d.toLocaleDateString('es-ES', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })}`
  } catch {
    return ''
  }
}
</script>

<style scoped lang="scss">
.whatsapp-channel-card {
  background: var(--crm-bg-card);
  border: 1px solid var(--crm-color-border);
  border-radius: var(--crm-radius-card);
  transition: all var(--crm-transition-fast);

  &:hover {
    border-color: var(--crm-color-border-hover);
    transform: translateY(-1px);
  }
}

.channel-icon {
  width: 40px;
  height: 40px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(255, 255, 255, 0.05);
  color: var(--crm-color-muted);
  border: 1px solid var(--crm-color-border);

  &--connected {
    background: var(--crm-color-primary-soft);
    color: var(--crm-color-primary);
    border-color: rgba(16, 185, 129, 0.25);
  }

  &--connecting {
    background: rgba(245, 158, 11, 0.1);
    color: #f59e0b;
    border-color: rgba(245, 158, 11, 0.25);
  }
}

.channel-name {
  font-size: 0.92rem;
  letter-spacing: -0.01em;
}

.channel-number {
  font-family: var(--crm-font-mono, monospace);
  font-size: 0.75rem;
}

.status-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 3px 8px;
  border-radius: var(--crm-radius-pill);
  font-size: 0.72rem;
  font-weight: 600;
  border: 1px solid transparent;

  &--connected {
    background: var(--crm-color-primary-soft);
    color: var(--crm-color-primary);
    border-color: rgba(16, 185, 129, 0.2);

    .status-dot {
      background-color: var(--crm-color-primary);
    }
  }

  &--connecting {
    background: rgba(245, 158, 11, 0.1);
    color: #f59e0b;
    border-color: rgba(245, 158, 11, 0.2);

    .status-dot {
      background-color: #f59e0b;
    }
  }

  &--disconnected {
    background: rgba(255, 255, 255, 0.04);
    color: var(--crm-color-muted);
    border-color: var(--crm-color-border);

    .status-dot {
      background-color: var(--crm-color-dim);
    }
  }
}

.status-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
}

.channel-menu {
  background: var(--crm-bg-card) !important;
  border: 1px solid var(--crm-color-border) !important;
  border-radius: 8px !important;
}
</style>
