<template>
  <q-layout view="hHh Lpr lFf" class="xf-app-layout">
    <!-- Top Header Minimalista -->
    <q-header class="xf-header-top">
      <q-toolbar class="xf-toolbar">
        <div class="row items-center q-gutter-x-sm">
          <q-btn
            flat
            dense
            round
            icon="sym_r_menu"
            color="grey-4"
            class="xf-btn-icon"
            @click="leftDrawerOpen = !leftDrawerOpen"
          />

          <!-- Brand Logo XpertiFlow V2 -->
          <div class="xf-logo-container">
            <div class="xf-logo-badge">
              <svg width="22" height="22" viewBox="0 0 28 28" fill="none">
                <rect width="28" height="28" rx="7" fill="#10B981" />
                <path d="M7 14L12 9L16 13L21 8M7 20L12 15L16 19L21 14" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </div>
            <div class="row items-center q-gutter-x-xs">
              <span class="xf-logo-text">XpertiFlow</span>
              <span class="xf-logo-badge-pill">V2</span>
            </div>
          </div>

          <!-- Pill Indicador de Fase Activa -->
          <div class="phase-active-pill q-ml-sm gt-xs">
            <span class="phase-active-dot"></span>
            <span>Fase 4: Productividad & Respuestas Rápidas</span>
          </div>
        </div>

        <q-space />

        <!-- Acciones Derechas -->
        <div class="row items-center q-gutter-x-sm">
          <!-- Selector de Organización -->
          <q-select
            v-model="selectedOrganizationId"
            :options="organizationOptions"
            emit-value
            map-options
            dense
            outlined
            dark
            class="xf-org-select"
            @update:model-value="onOrganizationChange"
          >
            <template #prepend>
              <q-icon name="sym_r_corporate_fare" size="15px" class="text-teal-4" />
            </template>
          </q-select>

          <!-- Avatar / Menú de Usuario con Indicador de Presencia -->
          <q-btn flat round dense class="q-ml-xs relative-position">
            <q-avatar size="32px" class="xf-avatar-user">
              {{ initials }}
              <q-badge
                floating
                rounded
                :color="presenceBadgeColor"
                class="xf-presence-badge"
              />
            </q-avatar>
            <q-menu anchor="bottom right" self="top right" dark class="xf-profile-menu" style="min-width: 250px;">
              <div class="q-pa-md">
                <div class="row items-center justify-between no-wrap">
                  <div class="text-weight-bold text-white text-subtitle2 ellipsis" style="max-width: 140px;">
                    {{ authStore.user?.name ?? 'Admin' }}
                  </div>
                  <q-badge :color="presenceBadgeColor" :label="presenceBadgeLabel" rounded class="text-bold text-caption" />
                </div>
                <div class="text-caption text-grey-4 ellipsis">{{ authStore.user?.email ?? 'admin@crm.local' }}</div>
                <div class="xf-user-role-badge q-mt-xs">
                  {{ authStore.user?.current_role ? authStore.user.current_role.toUpperCase() : 'SUPERADMIN' }}
                </div>
              </div>

              <q-separator dark />

              <!-- Selector Rápido de Presencia de Operador -->
              <div class="q-px-sm q-pt-sm q-pb-xs">
                <div class="text-caption text-weight-bold text-grey-5 q-px-sm q-mb-xs" style="font-size: 10px; letter-spacing: 0.5px;">
                  ESTADO DE OPERADOR
                </div>
                <div class="column q-gutter-y-xs">
                  <q-item
                    clickable
                    v-close-popup
                    dense
                    :class="['xf-presence-item', { 'xf-presence-item--active': currentPresence === 'online' }]"
                    @click="setQuickPresence('online')"
                  >
                    <q-item-section avatar style="min-width: 24px;">
                      <q-badge rounded color="positive" class="q-mr-xs" style="width: 8px; height: 8px;" />
                    </q-item-section>
                    <q-item-section>
                      <q-item-label class="text-white text-caption text-weight-medium">En línea</q-item-label>
                      <q-item-label caption class="text-grey-5" style="font-size: 10px;">Disponible para nuevos chats</q-item-label>
                    </q-item-section>
                    <q-item-section side v-if="currentPresence === 'online'">
                      <q-icon name="sym_r_check" size="16px" color="positive" />
                    </q-item-section>
                  </q-item>

                  <q-item
                    clickable
                    v-close-popup
                    dense
                    :class="['xf-presence-item', { 'xf-presence-item--active': currentPresence === 'busy' }]"
                    @click="setQuickPresence('busy')"
                  >
                    <q-item-section avatar style="min-width: 24px;">
                      <q-badge rounded color="warning" class="q-mr-xs" style="width: 8px; height: 8px;" />
                    </q-item-section>
                    <q-item-section>
                      <q-item-label class="text-white text-caption text-weight-medium">En pausa / Ausente</q-item-label>
                      <q-item-label caption class="text-grey-5" style="font-size: 10px;">No asignar nuevas colas</q-item-label>
                    </q-item-section>
                    <q-item-section side v-if="currentPresence === 'busy'">
                      <q-icon name="sym_r_check" size="16px" color="warning" />
                    </q-item-section>
                  </q-item>

                  <q-item
                    clickable
                    v-close-popup
                    dense
                    :class="['xf-presence-item', { 'xf-presence-item--active': currentPresence === 'offline' }]"
                    @click="setQuickPresence('offline')"
                  >
                    <q-item-section avatar style="min-width: 24px;">
                      <q-badge rounded color="grey-6" class="q-mr-xs" style="width: 8px; height: 8px;" />
                    </q-item-section>
                    <q-item-section>
                      <q-item-label class="text-white text-caption text-weight-medium">Fuera de turno</q-item-label>
                      <q-item-label caption class="text-grey-5" style="font-size: 10px;">Desconectado del sistema</q-item-label>
                    </q-item-section>
                    <q-item-section side v-if="currentPresence === 'offline'">
                      <q-icon name="sym_r_check" size="16px" color="grey-5" />
                    </q-item-section>
                  </q-item>
                </div>
              </div>

              <q-separator dark class="q-my-xs" />

              <q-list dense>
                <q-item clickable v-close-popup class="xf-profile-item" @click="isSettingsModalOpen = true">
                  <q-item-section avatar style="min-width: 24px;">
                    <q-icon name="sym_r_manage_accounts" size="18px" color="teal-4" />
                  </q-item-section>
                  <q-item-section>
                    <q-item-label class="text-white text-caption text-weight-medium">Mi Cuenta & Ajustes</q-item-label>
                    <q-item-label caption class="text-grey-5" style="font-size: 10px;">Audio, temas, 2FA y perfil</q-item-label>
                  </q-item-section>
                </q-item>

                <!-- Toggle Rápido Modo Oscuro -->
                <q-item tag="label" class="xf-profile-item">
                  <q-item-section avatar style="min-width: 24px;">
                    <q-icon :name="isDarkMode ? 'sym_r_dark_mode' : 'sym_r_light_mode'" size="18px" :color="isDarkMode ? 'teal-4' : 'amber-7'" />
                  </q-item-section>
                  <q-item-section>
                    <q-item-label class="text-white text-caption text-weight-medium">Modo Oscuro</q-item-label>
                  </q-item-section>
                  <q-item-section side>
                    <q-toggle v-model="isDarkMode" dense color="primary" size="sm" @update:model-value="toggleTheme" />
                  </q-item-section>
                </q-item>

                <q-separator dark class="q-my-xs" />

                <q-item clickable v-close-popup class="xf-profile-item text-negative" @click="handleLogout">
                  <q-item-section avatar style="min-width: 24px;">
                    <q-icon name="sym_r_logout" size="18px" color="negative" />
                  </q-item-section>
                  <q-item-section class="text-caption text-weight-medium">Cerrar sesión</q-item-section>
                </q-item>
              </q-list>
            </q-menu>
          </q-btn>
        </div>
      </q-toolbar>
    </q-header>

    <!-- Sidebar Lateral Minimalista — Fase 4 Activa -->
    <q-drawer
      v-model="leftDrawerOpen"
      show-if-above
      :width="240"
      bordered
      class="xf-sidebar-drawer"
    >
      <div class="xf-sidebar-content">
        <div>
          <!-- Encabezado de Navegación de Fase -->
          <div class="xf-nav-section-header">
            <div class="text-caption text-uppercase text-weight-bold text-grey-5 letter-spacing-wide">
              Fase 4 • Módulos Activos
            </div>
          </div>

          <!-- Lista de Navegación: Fase 1 + Fase 2 + Fase 3 -->
          <q-list class="xf-nav-list" padding>
            <!-- 1. Dashboard / Resumen -->
            <q-item
              clickable
              to="/app/dashboard"
              class="xf-nav-item"
              active-class="xf-nav-item--active"
            >
              <q-item-section avatar>
                <q-icon name="sym_r_dashboard" size="18px" />
              </q-item-section>
              <q-item-section>Dashboard</q-item-section>
            </q-item>

            <!-- 2. Bandeja Multicanal -->
            <q-item
              clickable
              to="/app/conversations"
              class="xf-nav-item xf-nav-item--multichannel"
              active-class="xf-nav-item--active"
            >
              <q-item-section avatar>
                <q-icon name="sym_r_forum" size="18px" class="xf-multichannel-icon" />
              </q-item-section>
              <q-item-section>
                <div class="row items-center no-wrap justify-between">
                  <span>Bandeja Multicanal</span>
                  <span class="xf-multichannel-badge">Principal</span>
                </div>
              </q-item-section>
            </q-item>

            <!-- 3. Canales & Conexiones (Fase 2) -->
            <q-item
              clickable
              to="/app/whatsapp"
              class="xf-nav-item"
              active-class="xf-nav-item--active"
            >
              <q-item-section avatar>
                <q-icon name="sym_r_hub" size="18px" />
              </q-item-section>
              <q-item-section>Canales & Conexiones</q-item-section>
            </q-item>

            <!-- 4. Pipeline Comercial & Kanban (Fase 3) -->
            <q-item
              clickable
              to="/app/deals"
              class="xf-nav-item"
              active-class="xf-nav-item--active"
            >
              <q-item-section avatar>
                <q-icon name="sym_r_view_kanban" size="18px" />
              </q-item-section>
              <q-item-section>Pipeline Comercial</q-item-section>
            </q-item>

            <!-- 5. Respuestas Rápidas & Snippets (Fase 4) -->
            <q-item
              clickable
              to="/app/quick-messages"
              class="xf-nav-item"
              active-class="xf-nav-item--active"
            >
              <q-item-section avatar>
                <q-icon name="sym_r_bolt" size="18px" />
              </q-item-section>
              <q-item-section>Respuestas Rápidas</q-item-section>
            </q-item>

            <!-- 6. Audiencia & Contactos -->
            <q-item
              clickable
              to="/app/contacts"
              class="xf-nav-item"
              active-class="xf-nav-item--active"
            >
              <q-item-section avatar>
                <q-icon name="sym_r_contacts" size="18px" />
              </q-item-section>
              <q-item-section>Contactos & Audiencia</q-item-section>
            </q-item>

            <!-- 7. Etiquetas & Segmentos -->
            <q-item
              clickable
              to="/app/tags"
              class="xf-nav-item"
              active-class="xf-nav-item--active"
            >
              <q-item-section avatar>
                <q-icon name="sym_r_label" size="18px" />
              </q-item-section>
              <q-item-section>Etiquetas & Segmentos</q-item-section>
            </q-item>

            <!-- 8. Empresas & Cuentas -->
            <q-item
              clickable
              to="/app/companies"
              class="xf-nav-item"
              active-class="xf-nav-item--active"
            >
              <q-item-section avatar>
                <q-icon name="sym_r_domain" size="18px" />
              </q-item-section>
              <q-item-section>Empresas & Cuentas</q-item-section>
            </q-item>

            <!-- 9. Operadores & Equipo -->
            <q-item
              clickable
              to="/app/users"
              class="xf-nav-item"
              active-class="xf-nav-item--active"
            >
              <q-item-section avatar>
                <q-icon name="sym_r_group" size="18px" />
              </q-item-section>
              <q-item-section>Operadores & Equipo</q-item-section>
            </q-item>

            <!-- 10. Filas & Departamentos -->
            <q-item
              clickable
              to="/app/departments"
              class="xf-nav-item"
              active-class="xf-nav-item--active"
            >
              <q-item-section avatar>
                <q-icon name="sym_r_alt_route" size="18px" />
              </q-item-section>
              <q-item-section>Filas & Departamentos</q-item-section>
            </q-item>

            <!-- 11. Parametrización -->
            <q-item
              clickable
              to="/app/parameters"
              class="xf-nav-item"
              active-class="xf-nav-item--active"
            >
              <q-item-section avatar>
                <q-icon name="sym_r_tune" size="18px" />
              </q-item-section>
              <q-item-section>Parametrización</q-item-section>
            </q-item>
          </q-list>

          <!-- Tarjeta Minimalista del Roadmap V2 -->
          <div class="xf-roadmap-card q-mx-sm q-mt-md">
            <div class="row items-center justify-between q-mb-xs">
              <span class="text-caption text-weight-bold text-white">Roadmap V2</span>
              <span class="text-caption text-teal-4 text-weight-medium">Fase 4 / 6</span>
            </div>
            <p class="text-caption text-grey-5 q-mb-xs roadmap-text">
              Fase 5 (Hentle-AI Copilot & RAG) se desbloqueará en su etapa.
            </p>
            <q-linear-progress :value="0.66" color="positive" track-color="grey-9" rounded size="4px" />
          </div>

        </div>

        <!-- Footer Sidebar: Toggle Modo Oscuro / Claro -->
        <div class="xf-sidebar-footer">
          <div class="xf-theme-toggle-box">
            <div class="row items-center q-gutter-x-xs">
              <q-icon
                :name="isDarkMode ? 'sym_r_dark_mode' : 'sym_r_light_mode'"
                size="16px"
                :color="isDarkMode ? 'teal-4' : 'amber-7'"
              />
              <span class="text-caption text-weight-medium">{{ isDarkMode ? 'Oscuro' : 'Claro' }}</span>
            </div>
            <q-toggle
              v-model="isDarkMode"
              dense
              color="primary"
              size="sm"
              @update:model-value="toggleTheme"
            />
          </div>
        </div>
      </div>
    </q-drawer>

    <!-- Contenedor Principal de Vistas -->
    <q-page-container class="xf-page-container">
      <router-view v-slot="{ Component }">
        <transition name="xf-fade" mode="out-in">
          <component :is="Component" />
        </transition>
      </router-view>
    </q-page-container>
    <!-- Modal Centralizado de Ajustes de Usuario & Preferencias -->
    <UserSettingsModal v-model="isSettingsModalOpen" />
  </q-layout>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useQuasar } from 'quasar'
import { useAuthStore } from '@/modules/auth/stores/auth.store'
import type { PresenceStatus } from '@/modules/auth/types/auth.types'
import UserSettingsModal from '@/shared/components/UserSettingsModal.vue'

const $q = useQuasar()
const router = useRouter()
const authStore = useAuthStore()

const leftDrawerOpen = ref(true)
const isDarkMode = ref(true)
const isSettingsModalOpen = ref(false)

const currentPresence = computed<PresenceStatus>(() => {
  return authStore.user?.presence_status ?? 'online'
})

const presenceBadgeColor = computed(() => {
  switch (currentPresence.value) {
    case 'online':
      return 'positive'
    case 'busy':
      return 'warning'
    case 'offline':
    default:
      return 'grey-6'
  }
})

const presenceBadgeLabel = computed(() => {
  switch (currentPresence.value) {
    case 'online':
      return 'En línea'
    case 'busy':
      return 'Ausente'
    case 'offline':
    default:
      return 'Fuera de turno'
  }
})

async function setQuickPresence(status: PresenceStatus) {
  try {
    await authStore.setPresence(status)
    $q.notify({
      type: 'positive',
      message: `Estado de operador: ${status === 'online' ? 'En línea' : status === 'busy' ? 'En pausa' : 'Fuera de turno'}`,
      position: 'bottom-right',
      timeout: 1800,
    })
  } catch (err: any) {
    $q.notify({
      type: 'negative',
      message: err?.message || 'Error al actualizar estado de presencia',
      position: 'bottom-right',
    })
  }
}

onMounted(() => {
  const userPrefTheme = authStore.user?.preferences?.theme_mode
  const saved = userPrefTheme || localStorage.getItem('whaticket_theme')
  if (saved) {
    isDarkMode.value = saved === 'dark'
  } else {
    isDarkMode.value = true
    localStorage.setItem('whaticket_theme', 'dark')
  }
  applyTheme(isDarkMode.value)

  const accentColor = authStore.user?.preferences?.accent_color
  if (accentColor) {
    const colorMap: Record<string, string> = {
      emerald: '#10b981',
      cyan: '#06b6d4',
      indigo: '#6366f1',
      amber: '#f59e0b',
      purple: '#8b5cf6',
      rose: '#ec4899',
    }
    if (colorMap[accentColor]) {
      document.documentElement.style.setProperty('--crm-color-primary', colorMap[accentColor])
    }
  }
})

function toggleTheme(val: boolean) {
  applyTheme(val)
  localStorage.setItem('whaticket_theme', val ? 'dark' : 'light')
}

function applyTheme(dark: boolean) {
  $q.dark.set(dark)
  if (dark) {
    document.documentElement.setAttribute('data-theme', 'dark')
    document.body.classList.add('body--dark')
    document.body.classList.remove('body--light')
  } else {
    document.documentElement.setAttribute('data-theme', 'light')
    document.body.classList.add('body--light')
    document.body.classList.remove('body--dark')
  }
}

const selectedOrganizationId = computed({
  get: () => authStore.user?.current_organization?.id ?? '',
  set: (id: string) => {
    authStore.switchOrganization(id)
  },
})

const organizationOptions = computed(() => {
  return (authStore.user?.organizations ?? []).map((org) => ({
    label: org.name,
    value: org.id,
  }))
})

const initials = computed(() => {
  const name = authStore.user?.name ?? 'Admin'
  return name.slice(0, 2).toUpperCase()
})

function onOrganizationChange(id: string) {
  authStore.switchOrganization(id)
}

async function handleLogout() {
  await authStore.logout()
  router.push('/login')
}
</script>

<style scoped lang="scss">
.xf-app-layout {
  background-color: var(--crm-bg-app);
  min-height: 100vh;
}

.xf-header-top {
  background-color: var(--crm-bg-header) !important;
  border-bottom: 1px solid var(--crm-color-border);
  height: 52px;
}

.xf-toolbar {
  min-height: 52px;
  padding: 0 16px;
}

.xf-btn-icon {
  transition: all var(--crm-transition-fast);
  &:hover {
    background: rgba(255, 255, 255, 0.06);
  }
}

.xf-logo-container {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-left: 4px;
}

.xf-logo-text {
  font-size: 1rem;
  font-weight: 700;
  color: var(--crm-color-ink);
  letter-spacing: -0.02em;
}

.xf-logo-badge-pill {
  font-size: 0.65rem;
  font-weight: 700;
  padding: 1px 5px;
  border-radius: 4px;
  background: var(--crm-color-primary-soft);
  color: var(--crm-color-primary);
  border: 1px solid rgba(16, 185, 129, 0.2);
}

.phase-active-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 3px 10px;
  background: rgba(16, 185, 129, 0.08);
  border: 1px solid rgba(16, 185, 129, 0.2);
  border-radius: 99px;
  font-size: 0.72rem;
  font-weight: 600;
  color: var(--crm-color-primary);
}

.phase-active-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background-color: var(--crm-color-primary);
}

.xf-org-select {
  min-width: 150px;
  :deep(.q-field__control) {
    height: 32px;
    min-height: 32px;
    background: var(--crm-bg-card);
    border-radius: 6px;
    border: 1px solid var(--crm-color-border);
    font-size: 0.8rem;
  }
}

.xf-avatar-user {
  background: var(--crm-color-primary) !important;
  color: #ffffff;
  font-weight: 700;
  font-size: 0.75rem;
}

.xf-user-role-badge {
  display: inline-block;
  font-size: 0.68rem;
  font-weight: 600;
  background: var(--crm-color-primary-soft);
  color: var(--crm-color-primary);
  padding: 1px 6px;
  border-radius: 4px;
}

.xf-sidebar-drawer {
  background-color: var(--crm-bg-sidebar) !important;
  border-right: 1px solid var(--crm-color-border) !important;
}

.xf-sidebar-content {
  display: flex;
  flex-direction: column;
  height: 100%;
  justify-content: space-between;
  overflow-y: auto;
}

.xf-nav-section-header {
  padding: 14px 16px 6px;
}

.letter-spacing-wide {
  letter-spacing: 0.05em;
  font-size: 0.68rem;
}

.xf-nav-list {
  padding: 4px 8px;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.xf-nav-item {
  border-radius: 8px;
  color: var(--crm-color-muted);
  min-height: 36px;
  padding: 6px 12px;
  font-size: 0.84rem;
  font-weight: 500;
  transition: all var(--crm-transition-fast);

  :deep(.q-icon) {
    font-size: 18px;
    color: var(--crm-color-dim);
    transition: color var(--crm-transition-fast);
  }

  &:hover {
    background: var(--crm-bg-card-hover);
    color: var(--crm-color-ink);

    :deep(.q-icon) {
      color: var(--crm-color-ink);
    }
  }

  &--active {
    background: var(--crm-color-primary-soft) !important;
    color: var(--crm-color-primary) !important;
    font-weight: 600;

    :deep(.q-icon) {
      color: var(--crm-color-primary) !important;
    }
  }

  &--multichannel {
    // Inactivo: fondo transparente igual a los demás ítems para no parecer seleccionado
    background: transparent;
    border: none;
    box-shadow: none;

    .xf-multichannel-icon {
      color: #10b981 !important;
      transition: transform var(--crm-transition-fast);
    }

    .xf-multichannel-badge {
      font-size: 0.62rem;
      font-weight: 600;
      letter-spacing: 0.3px;
      padding: 1px 6px;
      border-radius: 4px;
      background: rgba(16, 185, 129, 0.08);
      color: #10b981;
      border: 1px solid rgba(16, 185, 129, 0.18);
    }

    &:hover {
      background: var(--crm-bg-card-hover);

      .xf-multichannel-icon {
        transform: scale(1.05);
      }
    }

    &.xf-nav-item--active {
      background: rgba(16, 185, 129, 0.12) !important;
      color: #ffffff !important;
      font-weight: 600;

      .xf-multichannel-icon {
        color: #34d399 !important;
      }

      .xf-multichannel-badge {
        background: rgba(16, 185, 129, 0.2);
        color: #6ee7b7;
        border-color: rgba(16, 185, 129, 0.35);
      }
    }
  }
}

.xf-roadmap-card {
  padding: 10px 12px;
  background: var(--crm-bg-card);
  border: 1px solid var(--crm-color-border);
  border-radius: 8px;
}

.roadmap-text {
  font-size: 0.7rem;
  line-height: 1.35;
}

.xf-sidebar-footer {
  padding: 10px;
  border-top: 1px solid var(--crm-color-border);
}

.xf-theme-toggle-box {
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: var(--crm-bg-card);
  border: 1px solid var(--crm-color-border);
  padding: 4px 10px;
  border-radius: 8px;
}

.xf-page-container {
  background-color: var(--crm-bg-app);
}

.xf-profile-menu {
  background: var(--crm-bg-card) !important;
  border: 1px solid var(--crm-color-border) !important;
  border-radius: 10px !important;
  box-shadow: var(--crm-shadow-dropdown) !important;
}

.xf-presence-badge {
  top: -2px;
  right: -2px;
  min-width: 9px;
  min-height: 9px;
  border: 2px solid var(--crm-bg-header);
  padding: 0;
}

.xf-presence-item {
  border-radius: 6px;
  padding: 4px 8px;
  cursor: pointer;
  transition: background var(--crm-transition-fast);
  &:hover {
    background: rgba(255, 255, 255, 0.06);
  }
  &--active {
    background: rgba(16, 185, 129, 0.12) !important;
  }
}

.xf-profile-item {
  border-radius: 6px;
  margin: 1px 4px;
  padding: 6px 8px;
  cursor: pointer;
  transition: background var(--crm-transition-fast);
  &:hover {
    background: rgba(255, 255, 255, 0.06);
  }
}
</style>
