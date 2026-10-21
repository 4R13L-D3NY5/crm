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
        </div>

        <q-space />

        <!-- Acciones Derechas -->
        <div class="row items-center q-gutter-x-sm">
          <!-- Selector de Organización -->
          <q-select
            v-if="organizationOptions.length > 1 && authStore.isAdmin"
            v-model="selectedOrganizationId"
            :options="organizationOptions"
            emit-value
            map-options
            dense
            outlined
            class="xf-org-select"
            @update:model-value="onOrganizationChange"
          >
            <template #prepend>
              <q-icon name="sym_r_corporate_fare" size="15px" color="primary" />
            </template>
          </q-select>
          <div
            v-else-if="authStore.user?.current_organization"
            class="row items-center q-gutter-x-xs q-px-sm q-py-xs bg-dark-soft rounded-borders gt-xs"
          >
            <q-icon name="sym_r_corporate_fare" size="15px" color="primary" />
            <span class="text-caption text-weight-bold text-ink ellipsis" style="max-width: 130px;">{{ authStore.user.current_organization.name }}</span>
          </div>


          <!-- Botón de Guía Contextual & Centro de Ayuda (Foquito) -->
          <q-btn
            flat
            dense
            no-caps
            class="xf-guide-bulb-btn q-px-sm"
            @click="isGuideModalOpen = true"
          >
            <q-icon name="sym_r_lightbulb" color="amber-4" size="18px" class="q-mr-xs" />
            <span class="text-caption text-weight-bold text-amber-2 gt-xs">
              Guía: {{ currentMenuName }}
            </span>
            <q-tooltip class="bg-dark text-caption" anchor="bottom middle" self="top middle">
              Abrir manual interactivo con figuras y ejemplos para {{ currentMenuName }}
            </q-tooltip>
          </q-btn>

          <!-- Centro de Notificaciones Persistente -->
          <NotificationBell />

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
            <q-menu anchor="bottom right" self="top right" class="xf-profile-menu" style="min-width: 250px;">
              <div class="q-pa-md">
                <div class="row items-center justify-between no-wrap">
                  <div class="text-weight-bold text-ink text-subtitle2 ellipsis" style="max-width: 140px;">
                    {{ authStore.user?.name ?? 'Admin' }}
                  </div>
                  <q-badge :color="presenceBadgeColor" :label="presenceBadgeLabel" rounded class="text-bold text-caption" />
                </div>
                <div class="text-caption text-muted ellipsis">{{ authStore.user?.email ?? 'admin@crm.local' }}</div>
                <div class="xf-user-role-badge q-mt-xs">
                  {{ authStore.user?.current_role ? authStore.user.current_role.toUpperCase() : 'SUPERADMIN' }}
                </div>
              </div>

              <q-separator />

              <!-- Selector Rápido de Presencia de Operador -->
              <div class="q-px-sm q-pt-sm q-pb-xs">
                <div class="text-caption text-weight-bold text-dim q-px-sm q-mb-xs" style="font-size: 10px; letter-spacing: 0.5px;">
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
                      <q-item-label class="text-ink text-caption text-weight-medium">En línea</q-item-label>
                      <q-item-label caption class="text-muted" style="font-size: 10px;">Disponible para nuevos chats</q-item-label>
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
                      <q-item-label class="text-ink text-caption text-weight-medium">En pausa / Ausente</q-item-label>
                      <q-item-label caption class="text-muted" style="font-size: 10px;">No asignar nuevas colas</q-item-label>
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
                      <q-item-label class="text-ink text-caption text-weight-medium">Fuera de turno</q-item-label>
                      <q-item-label caption class="text-muted" style="font-size: 10px;">Desconectado del sistema</q-item-label>
                    </q-item-section>
                    <q-item-section side v-if="currentPresence === 'offline'">
                      <q-icon name="sym_r_check" size="16px" color="grey-5" />
                    </q-item-section>
                  </q-item>
                </div>
              </div>

              <q-separator class="q-my-xs" />

              <q-list dense>
                <q-item clickable v-close-popup class="xf-profile-item" @click="isSettingsModalOpen = true">
                  <q-item-section avatar style="min-width: 24px;">
                    <q-icon name="sym_r_manage_accounts" size="18px" color="primary" />
                  </q-item-section>
                  <q-item-section>
                    <q-item-label class="text-ink text-caption text-weight-medium">Mi Cuenta & Ajustes</q-item-label>
                    <q-item-label caption class="text-muted" style="font-size: 10px;">Audio, temas, 2FA y perfil</q-item-label>
                  </q-item-section>
                </q-item>

                <!-- Toggle Rápido Modo Oscuro -->
                <q-item tag="label" class="xf-profile-item">
                  <q-item-section avatar style="min-width: 24px;">
                    <q-icon :name="isDark ? 'sym_r_dark_mode' : 'sym_r_light_mode'" size="18px" :color="isDark ? 'primary' : 'amber-7'" />
                  </q-item-section>
                  <q-item-section>
                    <q-item-label class="text-ink text-caption text-weight-medium">Modo Oscuro</q-item-label>
                  </q-item-section>
                  <q-item-section side>
                    <q-toggle v-model="isDark" dense color="primary" size="sm" @update:model-value="toggleTheme" />
                  </q-item-section>
                </q-item>

                <q-separator class="q-my-xs" />

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
          <!-- SECCIÓN 1: OPERACIÓN DIARIA (Visible para Operadores y Administradores) -->
          <div class="xf-nav-section-header">
            <div class="text-caption text-uppercase text-weight-bold text-grey-5 letter-spacing-wide">
              Operación
            </div>
          </div>

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

            <!-- 3. Pipeline Comercial & Kanban -->
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

            <!-- 4. Respuestas Rápidas & Snippets -->
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

            <!-- 5. Notas & Base de Conocimiento -->
            <q-item
              clickable
              to="/app/notes"
              class="xf-nav-item"
              active-class="xf-nav-item--active"
            >
              <q-item-section avatar>
                <q-icon name="sym_r_sticky_note_2" size="18px" />
              </q-item-section>
              <q-item-section>Notas & Base de Conocimiento</q-item-section>
            </q-item>

            <!-- 5.1 Base de Conocimiento IA -->
            <q-item
              clickable
              to="/app/wabot"
              class="xf-nav-item"
              active-class="xf-nav-item--active"
            >
              <q-item-section avatar>
                <q-icon name="sym_r_psychology" size="18px" color="teal-4" />
              </q-item-section>
              <q-item-section>Base de Conocimiento IA</q-item-section>
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
          </q-list>

          <!-- SECCIÓN 2: GESTIÓN & CONFIGURACIÓN (Solo Administradores y Owners) -->
          <template v-if="authStore.isAdmin">
            <div class="xf-nav-section-header q-pt-sm">
              <div class="text-caption text-uppercase text-weight-bold text-grey-5 letter-spacing-wide">
                Gestión & Configuración
              </div>
            </div>

            <q-list class="xf-nav-list" padding>
              <!-- Canales & Conexiones -->
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

              <!-- Parametrización (Sedes, Carreras, Horarios, Estados) -->
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

              <!-- Operadores & Equipo -->
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

              <!-- Filas & Departamentos -->
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

              <!-- Campañas Masivas -->
              <q-item
                clickable
                to="/app/campaigns"
                class="xf-nav-item"
                active-class="xf-nav-item--active"
              >
                <q-item-section avatar>
                  <q-icon name="sym_r_campaign" size="18px" />
                </q-item-section>
                <q-item-section>Campañas Masivas</q-item-section>
              </q-item>

              <!-- Envíos Programados -->
              <q-item
                clickable
                to="/app/scheduled-messages"
                class="xf-nav-item"
                active-class="xf-nav-item--active"
              >
                <q-item-section avatar>
                  <q-icon name="sym_r_schedule_send" size="18px" />
                </q-item-section>
                <q-item-section>Envíos Programados</q-item-section>
              </q-item>

              <!-- Empresas & Cuentas -->
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

              <!-- Reportes & Métricas -->
              <q-item
                clickable
                to="/app/reports"
                class="xf-nav-item"
                active-class="xf-nav-item--active"
              >
                <q-item-section avatar>
                  <q-icon name="sym_r_monitoring" size="18px" />
                </q-item-section>
                <q-item-section>Reportes & Métricas</q-item-section>
              </q-item>

              <!-- Inteligencia Artificial (Hentle-AI) -->
              <q-item
                clickable
                to="/app/wabot"
                class="xf-nav-item"
                active-class="xf-nav-item--active"
              >
                <q-item-section avatar>
                  <q-icon name="sym_r_psychology" size="18px" color="teal-4" />
                </q-item-section>
                <q-item-section>Inteligencia Artificial</q-item-section>
              </q-item>

              <!-- Planes & Precios -->
              <q-item
                clickable
                to="/app/pricing"
                class="xf-nav-item"
                active-class="xf-nav-item--active"
              >
                <q-item-section avatar>
                  <q-icon name="sym_r_loyalty" size="18px" />
                </q-item-section>
                <q-item-section>Planes & Precios</q-item-section>
              </q-item>
            </q-list>
          </template>

          <!-- SECCIÓN 3: MASTER SAAS (Solo SuperAdmin admin@crm.local) -->
          <template v-if="authStore.isSuperAdmin">
            <div class="xf-nav-section-header q-pt-sm">
              <div class="text-caption text-uppercase text-weight-bold text-grey-5 letter-spacing-wide">
                Master SaaS
              </div>
            </div>

            <q-list class="xf-nav-list" padding>
              <q-item
                clickable
                to="/app/superadmin"
                class="xf-nav-item"
                active-class="xf-nav-item--active"
              >
                <q-item-section avatar>
                  <q-icon name="sym_r_admin_panel_settings" size="18px" color="indigo-4" />
                </q-item-section>
                <q-item-section>
                  <div class="row items-center no-wrap justify-between">
                    <span>Portal SuperAdmin</span>
                    <span class="xf-superadmin-badge">Master</span>
                  </div>
                </q-item-section>
              </q-item>
            </q-list>
          </template>
        </div>

        <!-- Footer Sidebar: Toggle Modo Oscuro / Claro -->
        <div class="xf-sidebar-footer">
          <div class="xf-theme-toggle-box">
            <div class="row items-center q-gutter-x-xs">
              <q-icon
                :name="isDark ? 'sym_r_dark_mode' : 'sym_r_light_mode'"
                size="16px"
                :color="isDark ? 'primary' : 'amber-7'"
              />
              <span class="text-caption text-weight-medium text-ink">{{ isDark ? 'Oscuro' : 'Claro' }}</span>
            </div>
            <q-toggle
              v-model="isDark"
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
    <!-- Modal Interactivo de Guía de Uso del Sistema (Foquito) -->
    <SystemGuideModal v-model="isGuideModalOpen" />
  </q-layout>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useQuasar } from 'quasar'
import { useAuthStore } from '@/modules/auth/stores/auth.store'
import type { PresenceStatus } from '@/modules/auth/types/auth.types'
import UserSettingsModal from '@/shared/components/UserSettingsModal.vue'
import SystemGuideModal from '@/shared/components/SystemGuideModal.vue'
import NotificationBell from '@/modules/notifications/components/NotificationBell.vue'
import { useAppTheme } from '@/shared/composables/useAppTheme'

const $q = useQuasar()
const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()
const themeStore = useAppTheme()
const isDark = themeStore.isDark
const { applyThemeMode, initTheme } = themeStore

const leftDrawerOpen = ref(!$q.screen.lt.md)
const isSettingsModalOpen = ref(false)
const isGuideModalOpen = ref(false)

const currentMenuName = computed(() => {
  const p = (route.path || '').toLowerCase()
  const n = String(route.name || '').toLowerCase()

  if (p.includes('/tags') || n.includes('tag')) return 'Etiquetas & Segmentos'
  if (p.includes('/deals') || n.includes('deal')) return 'Pipeline Comercial'
  if (p.includes('/quick-messages') || n.includes('quick-message')) return 'Respuestas Rápidas'
  if (p.includes('/notes') || n.includes('note')) return 'Notas & Base de Conocimiento'
  if (p.includes('/contacts') || n.includes('contact')) return 'Contactos & Audiencia'
  if (p.includes('/conversations') || n.includes('conversation')) return 'Bandeja Multicanal'
  if (p.includes('/whatsapp') || n.includes('whatsapp')) return 'Canales WhatsApp'
  if (p.includes('/parameters') || n.includes('parameter')) return 'Parametrización & Horarios'
  if (p.includes('/users') || n.includes('user')) return 'Operadores & Equipo'
  if (p.includes('/departments') || n.includes('department')) return 'Filas & Departamentos'
  if (p.includes('/campaigns') || n.includes('campaign')) return 'Campañas Masivas'
  if (p.includes('/scheduled-messages') || n.includes('scheduled')) return 'Envíos Programados'
  if (p.includes('/companies') || n.includes('compan')) return 'Empresas B2B'
  if (p.includes('/reports') || n.includes('report')) return 'Reportes & Métricas'
  if (p.includes('/wabot') || p.includes('/ai')) return 'Inteligencia Artificial'
  if (p.includes('/pricing') || p.includes('/planes')) return 'Planes & Precios'
  if (p.includes('/superadmin')) return 'Portal SuperAdmin'
  if (p.includes('/settings')) return 'Ajustes del Sistema'
  return 'Dashboard'
})

// Cerrar drawer lateral automáticamente en pantallas móviles al navegar
router.afterEach(() => {
  if ($q.screen.lt.md) {
    leftDrawerOpen.value = false
  }
})

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
  initTheme(authStore.user?.preferences)
})

function toggleTheme(val: boolean) {
  applyThemeMode(val)
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

    .xf-superadmin-badge {
      font-size: 0.62rem;
      font-weight: 700;
      letter-spacing: 0.3px;
      padding: 1px 6px;
      border-radius: 4px;
      background: rgba(99, 102, 241, 0.12);
      color: #818cf8;
      border: 1px solid rgba(99, 102, 241, 0.25);
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

.xf-guide-bulb-btn {
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  position: relative;
  background: rgba(245, 158, 11, 0.12);
  border: 1px solid rgba(245, 158, 11, 0.35);
  border-radius: 8px;
  height: 32px;
  &:hover {
    background: rgba(245, 158, 11, 0.22);
    border-color: rgba(245, 158, 11, 0.65);
    filter: drop-shadow(0 0 10px rgba(251, 191, 36, 0.45));
    transform: translateY(-1px);
  }
}
</style>
