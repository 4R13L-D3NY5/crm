<template>
  <div class="notification-bell-wrapper">
    <q-btn
      flat
      round
      dense
      class="xf-btn-icon relative-position"
      :class="{ 'has-unread': store.unreadCount > 0 }"
      aria-label="Centro de Notificaciones y Alertas SLA"
    >
      <q-icon
        :name="store.unreadCount > 0 ? 'sym_r_notifications_active' : 'sym_r_notifications'"
        size="21px"
        :color="store.unreadCount > 0 ? 'amber-4' : 'grey-4'"
      />

      <!-- Badge con conteo en tiempo real -->
      <q-badge
        v-if="store.unreadCount > 0"
        floating
        rounded
        color="negative"
        class="bell-unread-badge"
      >
        {{ store.unreadCount > 99 ? '99+' : store.unreadCount }}
      </q-badge>

      <q-tooltip anchor="bottom middle" self="top middle">
        Alertas y Notificaciones ({{ store.unreadCount }} pendientes)
      </q-tooltip>

      <!-- Menú desplegable del Centro de Notificaciones Operativas -->
      <q-menu
        anchor="bottom right"
        self="top right"
        class="xf-notifications-menu"
        style="width: 440px; max-width: 95vw;"
      >
        <!-- Header del Menú -->
        <div class="q-pa-sm xf-notif-header row items-center justify-between border-bottom">
          <div class="row items-center q-gutter-x-xs">
            <q-icon name="sym_r_notifications" size="18px" color="primary" />
            <span class="text-subtitle2 text-weight-bold text-ink">Alertas & Notificaciones</span>
            <q-badge
              v-if="store.unreadCount > 0"
              color="negative"
              text-color="white"
              rounded
              class="q-ml-xs text-bold"
              style="font-size: 10px;"
            >
              {{ store.unreadCount }} pendientes
            </q-badge>
          </div>

          <div class="row items-center q-gutter-x-xs">
            <q-btn
              flat
              dense
              no-caps
              size="xs"
              color="amber-4"
              icon="sym_r_campaign"
              label="Probar Alerta"
              class="xf-btn-action-text q-mr-xs"
              @click="store.triggerTestNotification"
            >
              <q-tooltip>Emitir alerta de prueba con sonido y ventana emergente</q-tooltip>
            </q-btn>
            <q-btn
              v-if="store.unreadCount > 0"
              flat
              dense
              no-caps
              size="xs"
              color="primary"
              icon="sym_r_done_all"
              label="Marcar leídas"
              class="xf-btn-action-text"
              @click="store.markAllAsRead"
            >
              <q-tooltip>Marcar todas como atendidas</q-tooltip>
            </q-btn>
            <q-btn
              flat
              dense
              round
              size="xs"
              icon="sym_r_refresh"
              color="grey-5"
              :loading="store.loading"
              @click="store.loadNotifications"
            >
              <q-tooltip>Actualizar ahora</q-tooltip>
            </q-btn>
          </div>
        </div>

        <!-- Banner para habilitar notificaciones de escritorio si aún no están autorizadas -->
        <div
          v-if="!store.hasDesktopPermission"
          class="q-px-sm q-py-xs bg-dark-soft row items-center justify-between border-bottom"
          style="border-left: 3px solid #f59e0b;"
        >
          <div class="row items-center q-gutter-x-xs ellipsis" style="max-width: 320px;">
            <q-icon name="sym_r_notifications_active" size="16px" color="amber-4" />
            <span class="text-caption text-grey-3" style="font-size: 11px;">
              Alertas de escritorio inactivas en navegador.
            </span>
          </div>
          <q-btn
            flat
            dense
            no-caps
            size="xs"
            color="amber-4"
            label="Habilitar"
            class="text-weight-bold"
            @click="store.requestDesktopPermission"
          />
        </div>

        <!-- Filtros Operativos SLA y No Leídos -->
        <div class="q-px-sm q-py-xs bg-surface-subtle row items-center q-gutter-x-xs no-wrap overflow-hidden">
          <q-btn
            v-for="filter in filterOptions"
            :key="filter.value"
            flat
            dense
            no-caps
            size="xs"
            :class="['notif-chip-btn', { 'notif-chip-btn--active': store.activeFilter === filter.value }]"
            @click="store.activeFilter = filter.value"
          >
            <div class="row items-center q-gutter-x-xs no-wrap">
              <span>{{ filter.label }}</span>
              <q-badge
                v-if="getFilterBadgeCount(filter.value) > 0"
                rounded
                :color="getFilterBadgeColor(filter.value)"
                class="q-ml-xs"
                style="font-size: 9px; padding: 1px 4px;"
              >
                {{ getFilterBadgeCount(filter.value) }}
              </q-badge>
            </div>
            <q-tooltip>{{ filter.tooltip }}</q-tooltip>
          </q-btn>
        </div>

        <!-- Lista de Notificaciones con Scroll -->
        <q-scroll-area style="height: 380px;" class="xf-notif-scroll">
          <div v-if="store.filteredNotifications.length === 0" class="column items-center justify-center q-py-xl text-center">
            <div class="empty-notif-icon q-mb-sm">
              <q-icon name="sym_r_done_all" size="32px" color="positive" />
            </div>
            <div class="text-caption text-weight-medium text-ink">¡Todo al día!</div>
            <div class="text-caption text-dim q-px-md" style="font-size: 11.5px;">
              {{ getEmptyMessage() }}
            </div>
          </div>

          <q-list v-else separator class="xf-notif-list">
            <q-item
              v-for="item in store.filteredNotifications"
              :key="item.id"
              clickable
              v-close-popup="Boolean(item.action_url)"
              :class="['xf-notif-item', `xf-notif-item--${item.type}`, { 'xf-notif-item--unread': !item.is_read }]"
              @click="handleNotificationClick(item)"
            >
              <!-- Icono contextual -->
              <q-item-section avatar style="min-width: 36px;">
                <div :class="['notif-type-icon', `notif-type-icon--${item.type}`]">
                  <q-icon :name="getTypeIcon(item.type)" size="18px" />
                </div>
              </q-item-section>

              <!-- Texto y Detalles -->
              <q-item-section>
                <div class="row items-center justify-between no-wrap">
                  <div class="row items-center q-gutter-x-xs ellipsis" style="max-width: 250px;">
                    <q-badge
                      dense
                      rounded
                      :color="getTypeBadgeColor(item.type)"
                      :label="getTypeBadgeLabel(item.type)"
                      class="text-bold"
                      style="font-size: 9px; padding: 1px 5px;"
                    />
                    <span class="text-caption text-weight-bold text-ink ellipsis">
                      {{ item.title }}
                    </span>
                  </div>
                  <span class="text-caption text-dim text-right" style="font-size: 10px;">
                    {{ item.created_at_human }}
                  </span>
                </div>

                <div class="text-caption text-muted notif-message-preview q-mt-xs">
                  {{ item.message }}
                </div>
              </q-item-section>

              <!-- Indicador de no leído / Acciones -->
              <q-item-section side class="q-pl-xs items-center">
                <span v-if="!item.is_read" class="unread-dot" title="Sin atender" />
                <q-btn
                  flat
                  dense
                  round
                  size="xs"
                  icon="sym_r_close"
                  color="grey-6"
                  class="notif-delete-btn"
                  @click.stop="store.removeNotification(item.id)"
                >
                  <q-tooltip>Descartar</q-tooltip>
                </q-btn>
              </q-item-section>
            </q-item>
          </q-list>
        </q-scroll-area>

        <!-- Footer Informativo con Reglas de Inactividad -->
        <div class="q-pa-xs text-center border-top bg-surface-subtle row items-center justify-between q-px-sm">
          <span class="text-caption text-dim" style="font-size: 10.5px;">
            SLA Asesor: <strong>{{ store.advisorTimeoutMinutes }}m</strong> • Inactividad Cliente: <strong>{{ store.clientTimeoutMinutes }}m</strong>
          </span>
          <q-btn
            flat
            dense
            no-caps
            size="xs"
            color="primary"
            label="Ver SLA"
            to="/app/parameters"
            v-close-popup
          />
        </div>
      </q-menu>
    </q-btn>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { useNotificationsStore } from '../stores/useNotificationsStore'
import type { AppNotificationItem } from '../api/notifications.api'

const router = useRouter()
const store = useNotificationsStore()

const filterOptions = computed(() => [
  { label: 'Todas', value: 'all' as const, tooltip: 'Todas las alertas y notificaciones' },
  { label: 'No leídos', value: 'unread' as const, tooltip: 'Mensajes nuevos sin abrir' },
  { label: 'Sin respuesta Asesor', value: 'waiting_advisor' as const, tooltip: `Clientes esperando respuesta del asesor (> ${store.advisorTimeoutMinutes} min)` },
  { label: 'Sin respuesta Cliente', value: 'waiting_client' as const, tooltip: `Clientes inactivos esperando seguimiento (> ${store.clientTimeoutMinutes} min)` },
])

function getFilterBadgeCount(filterValue: string): number {
  if (filterValue === 'all') return store.unreadCount
  if (filterValue === 'unread') return store.counts.unread
  if (filterValue === 'waiting_advisor') return store.counts.waiting_advisor
  if (filterValue === 'waiting_client') return store.counts.waiting_client
  return 0
}

function getFilterBadgeColor(filterValue: string): string {
  if (filterValue === 'waiting_advisor') return 'warning'
  if (filterValue === 'waiting_client') return 'cyan-8'
  if (filterValue === 'unread') return 'positive'
  return 'primary'
}

function getTypeIcon(type: string): string {
  switch (type) {
    case 'waiting_advisor':
      return 'sym_r_hourglass_top'
    case 'waiting_client':
      return 'sym_r_hourglass_empty'
    case 'unread':
    case 'chat':
      return 'sym_r_mark_chat_unread'
    default:
      return 'sym_r_notifications'
  }
}

function getTypeBadgeLabel(type: string): string {
  switch (type) {
    case 'waiting_advisor':
      return 'SLA Asesor'
    case 'waiting_client':
      return 'Inactivo'
    case 'unread':
    case 'chat':
      return 'Nuevo'
    default:
      return 'Aviso'
  }
}

function getTypeBadgeColor(type: string): string {
  switch (type) {
    case 'waiting_advisor':
      return 'warning'
    case 'waiting_client':
      return 'cyan-8'
    case 'unread':
    case 'chat':
      return 'positive'
    default:
      return 'primary'
  }
}

function getEmptyMessage(): string {
  if (store.activeFilter === 'waiting_advisor') {
    return 'Excelente. Ningún cliente lleva esperando más del tiempo máximo permitido.'
  }
  if (store.activeFilter === 'waiting_client') {
    return 'No hay clientes inactivos fuera del tiempo límite.'
  }
  if (store.activeFilter === 'unread') {
    return 'No tienes mensajes nuevos sin abrir.'
  }
  return 'No hay alertas pendientes de atención ni mensajes sin leer.'
}

function handleNotificationClick(item: AppNotificationItem) {
  if (!item.is_read) {
    store.markAsRead(item.id)
  }

  if (item.action_url) {
    router.push(item.action_url)
  }
}

onMounted(() => {
  store.startPolling(20000)
})

onUnmounted(() => {
  store.stopPolling()
})
</script>

<style scoped lang="scss">
.notification-bell-wrapper {
  display: inline-flex;
  align-items: center;
}

.bell-unread-badge {
  font-size: 10px;
  font-weight: 700;
  padding: 2px 5px;
  min-height: 16px;
  top: 3px;
  right: 3px;
  box-shadow: 0 0 10px rgba(239, 68, 68, 0.6);
  animation: pulse-badge 2s infinite ease-in-out;
}

@keyframes pulse-badge {
  0%, 100% {
    transform: scale(1);
  }
  50% {
    transform: scale(1.15);
  }
}

.xf-notifications-menu {
  background: var(--crm-bg-card, #131d2a) !important;
  border: 1px solid var(--crm-color-border, rgba(255, 255, 255, 0.12));
  border-radius: 12px;
  box-shadow: 0 12px 36px rgba(0, 0, 0, 0.45);
  overflow: hidden;
}

.xf-notif-header {
  background: var(--crm-bg-card);
}

.notif-chip-btn {
  font-size: 11px;
  padding: 3px 8px;
  border-radius: 6px;
  color: var(--crm-color-dim, #94a3b8);
  transition: all 0.2s ease;

  &:hover {
    color: var(--crm-color-ink, #f8fafc);
    background: rgba(255, 255, 255, 0.05);
  }

  &--active {
    background: rgba(16, 185, 129, 0.15) !important;
    color: #10b981 !important;
    font-weight: 700;
  }
}

.xf-notif-item {
  padding: 10px 12px;
  transition: background 0.15s ease;
  border-bottom: 1px solid rgba(255, 255, 255, 0.04);

  &:hover {
    background: rgba(255, 255, 255, 0.04);

    .notif-delete-btn {
      opacity: 1;
    }
  }

  &--waiting_advisor {
    border-left: 3px solid #f59e0b;
  }

  &--waiting_client {
    border-left: 3px solid #06b6d4;
  }

  &--unread {
    border-left: 3px solid #10b981;
    background: rgba(16, 185, 129, 0.03);
  }
}

.notif-message-preview {
  font-size: 11.5px;
  line-height: 1.4;
  color: var(--crm-color-dim, #94a3b8);
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.notif-type-icon {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;

  &--waiting_advisor {
    background: rgba(245, 158, 11, 0.15);
    color: #f59e0b;
  }
  &--waiting_client {
    background: rgba(6, 182, 212, 0.15);
    color: #06b6d4;
  }
  &--unread, &--chat {
    background: rgba(16, 185, 129, 0.15);
    color: #10b981;
  }
  &--system {
    background: rgba(56, 189, 248, 0.15);
    color: #38bdf8;
  }
}

.unread-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background-color: #10b981;
  box-shadow: 0 0 6px rgba(16, 185, 129, 0.7);
  display: inline-block;
  margin-bottom: 4px;
}

.notif-delete-btn {
  opacity: 0.3;
  transition: opacity 0.2s ease;
}

.empty-notif-icon {
  width: 52px;
  height: 52px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.03);
  display: flex;
  align-items: center;
  justify-content: center;
}
</style>
