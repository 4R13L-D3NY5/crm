<template>
  <q-layout
    view="hHh Lpr lFf"
    class="xf-app-layout"
  >
    <!-- Top Header Glassmorphism -->
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

          <!-- Logo XpertiFlow Suite Pro -->
          <div class="xf-logo-container">
            <div class="xf-logo-badge">
              <svg width="24" height="24" viewBox="0 0 28 28" fill="none">
                <rect width="28" height="28" rx="8" fill="url(#xfGrad)" />
                <path d="M7 14L12 9L16 13L21 8M7 20L12 15L16 19L21 14" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
                <defs>
                  <linearGradient id="xfGrad" x1="0" y1="0" x2="28" y2="28" gradientUnits="userSpaceOnUse">
                    <stop stop-color="#10B981" />
                    <stop offset="0.5" stop-color="#06B6D4" />
                    <stop offset="1" stop-color="#6366F1" />
                  </linearGradient>
                </defs>
              </svg>
            </div>
            <div class="column">
              <div class="row items-center q-gutter-x-xs">
                <span class="xf-logo-text">XpertiFlow</span>
                <span class="xf-logo-badge-pill">PRO</span>
              </div>
              <span class="xf-logo-sub">Omnichannel CRM</span>
            </div>
          </div>
        </div>

        <q-space />

        <!-- Acciones derechas del Header -->
        <div class="row items-center q-gutter-x-sm">
          <!-- Botón de Ayuda / Docs -->
          <q-btn
            flat
            round
            dense
            icon="sym_r_menu_book"
            color="grey-4"
            class="xf-header-action-btn"
            to="/app/docs"
          >
            <q-tooltip>Documentación de API y Ayuda</q-tooltip>
          </q-btn>

          <!-- Notificaciones con badge animado -->
          <q-btn
            flat
            round
            dense
            icon="sym_r_forum"
            color="grey-4"
            class="xf-header-action-btn"
            to="/app/conversations"
          >
            <q-badge color="negative" floating rounded class="xf-badge-pulse">26</q-badge>
            <q-tooltip>26 conversaciones pendientes o no leídas</q-tooltip>
          </q-btn>

          <!-- Selector de Organización con estilo Glass -->
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
              <q-icon name="sym_r_corporate_fare" size="16px" color="teal-4" />
            </template>
          </q-select>

          <!-- Avatar / Perfil con Menú Glass -->
          <q-btn flat round dense class="q-ml-xs">
            <q-avatar size="34px" class="xf-avatar-user">
              {{ initials }}
            </q-avatar>
            <q-menu anchor="bottom right" self="top right" dark class="xf-profile-menu">
              <div class="q-pa-md">
                <div class="text-bold text-white">{{ authStore.user?.name ?? 'Jose Claure' }}</div>
                <div class="text-caption text-grey-4">{{ authStore.user?.email ?? 'jclaure_dis@unitepc.net' }}</div>
                <div class="xf-user-role-badge q-mt-xs">Administrador General</div>
              </div>
              <q-separator dark class="q-my-xs" />
              <q-list dense>
                <q-item clickable v-close-popup to="/app/settings" class="xf-profile-item">
                  <q-item-section avatar><q-icon name="sym_r_tune" size="18px" /></q-item-section>
                  <q-item-section>Ajustes de Cuenta</q-item-section>
                </q-item>
                <q-item clickable v-close-popup class="xf-profile-item text-negative" @click="handleLogout">
                  <q-item-section avatar><q-icon name="sym_r_logout" size="18px" color="negative" /></q-item-section>
                  <q-item-section>Cerrar sesión</q-item-section>
                </q-item>
              </q-list>
            </q-menu>
          </q-btn>
        </div>
      </q-toolbar>
    </q-header>

    <!-- Sidebar Lateral Elegante -->
    <q-drawer
      v-model="leftDrawerOpen"
      show-if-above
      :width="250"
      bordered
      class="xf-sidebar-drawer"
    >
      <div class="xf-sidebar-content">
        <!-- Navegación por Grupos / Categorías -->
        <q-list class="xf-nav-list" padding>
          <div class="xf-nav-section-title">ATENCIÓN & MENSAJERÍA</div>

          <!-- 1. Chats Omnicanal -->
          <q-item
            clickable
            to="/app/conversations"
            class="xf-nav-item"
            active-class="xf-nav-item--active"
          >
            <q-item-section avatar>
              <q-icon name="sym_r_chat" />
            </q-item-section>
            <q-item-section>Bandeja Omnicanal</q-item-section>
            <q-item-section side>
              <span class="xf-nav-counter">26</span>
            </q-item-section>
          </q-item>

          <!-- 2. Conexiones Multicanal -->
          <q-item
            clickable
            to="/app/whatsapp"
            class="xf-nav-item"
            active-class="xf-nav-item--active"
          >
            <q-item-section avatar>
              <q-icon name="sym_r_hub" />
            </q-item-section>
            <q-item-section>Conexiones & Canales</q-item-section>
            <q-item-section side>
              <span class="xf-status-dot-active"></span>
            </q-item-section>
          </q-item>

          <!-- 3. Audiencia / Contactos -->
          <q-item
            clickable
            to="/app/contacts"
            class="xf-nav-item"
            active-class="xf-nav-item--active"
          >
            <q-item-section avatar>
              <q-icon name="sym_r_contacts" />
            </q-item-section>
            <q-item-section>Audiencia & Contactos</q-item-section>
          </q-item>

          <!-- 4. Pipeline Comercial (Kanban) -->
          <q-item
            clickable
            to="/app/deals"
            class="xf-nav-item"
            active-class="xf-nav-item--active"
          >
            <q-item-section avatar>
              <q-icon name="sym_r_view_kanban" color="teal-4" />
            </q-item-section>
            <q-item-section>
              <div class="row items-center justify-between">
                <span>Pipeline Comercial</span>
                <q-badge color="teal-9" text-color="teal-2" rounded class="text-caption">Kanban</q-badge>
              </div>
            </q-item-section>
          </q-item>

          <!-- 5. Empresas & Cuentas -->
          <q-item
            clickable
            to="/app/companies"
            class="xf-nav-item"
            active-class="xf-nav-item--active"
          >
            <q-item-section avatar>
              <q-icon name="sym_r_domain" />
            </q-item-section>
            <q-item-section>Empresas & Cuentas</q-item-section>
          </q-item>

          <!-- 6. Etiquetas & Segmentación -->
          <q-item
            clickable
            to="/app/tags"
            class="xf-nav-item"
            active-class="xf-nav-item--active"
          >
            <q-item-section avatar>
              <q-icon name="sym_r_label" />
            </q-item-section>
            <q-item-section>Etiquetas & Segmentos</q-item-section>
          </q-item>

          <!-- 7. Respuestas Rápidas -->
          <q-item
            clickable
            to="/app/quick-messages"
            class="xf-nav-item"
            active-class="xf-nav-item--active"
          >
            <q-item-section avatar>
              <q-icon name="sym_r_bolt" />
            </q-item-section>
            <q-item-section>Respuestas Rápidas</q-item-section>
          </q-item>

          <!-- 8. Envíos Programados -->
          <q-item
            clickable
            to="/app/scheduled-messages"
            class="xf-nav-item"
            active-class="xf-nav-item--active"
          >
            <q-item-section avatar>
              <q-icon name="sym_r_event_available" />
            </q-item-section>
            <q-item-section>Envíos Programados</q-item-section>
          </q-item>

          <div class="xf-nav-section-title q-mt-sm">INTELIGENCIA & AUTOMATIZACIÓN</div>

          <!-- 9. Hentle-AI Wäbot (Cognitivo RAG) -->
          <q-item
            clickable
            to="/app/wabot"
            class="xf-nav-item xf-nav-item--ai"
            active-class="xf-nav-item--active"
          >
            <q-item-section avatar>
              <q-icon name="sym_r_auto_awesome" class="xf-ai-icon" />
            </q-item-section>
            <q-item-section>
              <div class="row items-center justify-between">
                <span>Hentle-AI (RAG)</span>
                <span class="xf-ai-badge">IA</span>
              </div>
            </q-item-section>
          </q-item>

          <!-- 10. Campañas & Difusión Masiva -->
          <q-item
            clickable
            to="/app/campaigns"
            class="xf-nav-item"
            active-class="xf-nav-item--active"
          >
            <q-item-section avatar>
              <q-icon name="sym_r_campaign" />
            </q-item-section>
            <q-item-section>Campañas Masivas</q-item-section>
          </q-item>

          <!-- 11. Departamentos / Colas -->
          <q-item
            clickable
            to="/app/departments"
            class="xf-nav-item"
            active-class="xf-nav-item--active"
          >
            <q-item-section avatar>
              <q-icon name="sym_r_account_tree" />
            </q-item-section>
            <q-item-section>Filas & Enrutamiento</q-item-section>
          </q-item>

          <div class="xf-nav-section-title q-mt-sm">GESTIÓN & MÉTRICAS</div>

          <!-- 12. Equipo & Permisos -->
          <q-item
            clickable
            to="/app/users"
            class="xf-nav-item"
            active-class="xf-nav-item--active"
          >
            <q-item-section avatar>
              <q-icon name="sym_r_badge" />
            </q-item-section>
            <q-item-section>Equipo & Operadores</q-item-section>
          </q-item>

          <!-- 13. Informes & SLAs -->
          <q-item
            clickable
            to="/app/reports"
            class="xf-nav-item"
            active-class="xf-nav-item--active"
          >
            <q-item-section avatar>
              <q-icon name="sym_r_analytics" />
            </q-item-section>
            <q-item-section>Métricas & Analytics</q-item-section>
          </q-item>

          <!-- 14. Auditoría Forense -->
          <q-item
            clickable
            to="/app/audit"
            class="xf-nav-item"
            active-class="xf-nav-item--active"
          >
            <q-item-section avatar>
              <q-icon name="sym_r_security" color="cyan-4" />
            </q-item-section>
            <q-item-section>Auditoría Forense</q-item-section>
          </q-item>

          <!-- 15. Configuración -->
          <q-item
            clickable
            to="/app/settings"
            class="xf-nav-item"
            active-class="xf-nav-item--active"
          >
            <q-item-section avatar>
              <q-icon name="sym_r_tune" />
            </q-item-section>
            <q-item-section>Ajustes de Espacio</q-item-section>
          </q-item>

          <!-- 16. API Tokens -->
          <q-item
            clickable
            to="/app/tokens"
            class="xf-nav-item"
            active-class="xf-nav-item--active"
          >
            <q-item-section avatar>
              <q-icon name="sym_r_key" />
            </q-item-section>
            <q-item-section>Tokens de Acceso</q-item-section>
          </q-item>

          <!-- 14. Documentación OpenAPI -->
          <q-item
            clickable
            to="/app/docs"
            class="xf-nav-item"
            active-class="xf-nav-item--active"
          >
            <q-item-section avatar>
              <q-icon name="sym_r_code" />
            </q-item-section>
            <q-item-section>Swagger & API SDK</q-item-section>
          </q-item>
        </q-list>

        <!-- Footer Sidebar: Modo Oscuro / Claro Toggle -->
        <div class="xf-sidebar-footer">
          <div class="xf-theme-toggle-box">
            <div class="row items-center q-gutter-x-sm">
              <q-icon :name="isDarkMode ? 'sym_r_dark_mode' : 'sym_r_light_mode'" size="18px" :color="isDarkMode ? 'teal-4' : 'amber-7'" />
              <span class="text-caption text-weight-medium">{{ isDarkMode ? 'Modo Titanio' : 'Modo Studio' }}</span>
            </div>
            <q-toggle
              v-model="isDarkMode"
              dense
              color="primary"
              @update:model-value="toggleTheme"
            />
          </div>
        </div>
      </div>
    </q-drawer>

    <!-- Contenedor de Páginas -->
    <q-page-container class="xf-page-container">
      <router-view v-slot="{ Component }">
        <transition name="xf-fade" mode="out-in">
          <component :is="Component" />
        </transition>
      </router-view>
    </q-page-container>
  </q-layout>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useQuasar } from 'quasar'
import { useAuthStore } from '@/modules/auth/stores/auth.store'

const $q = useQuasar()
const router = useRouter()
const authStore = useAuthStore()

const leftDrawerOpen = ref(true)
const isDarkMode = ref(true)

onMounted(() => {
  const saved = localStorage.getItem('whaticket_theme')
  if (saved) {
    isDarkMode.value = saved === 'dark'
  } else {
    isDarkMode.value = true
    localStorage.setItem('whaticket_theme', 'dark')
  }
  applyTheme(isDarkMode.value)
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

<style lang="scss">
.xf-app-layout {
  background-color: var(--crm-bg-app);
  min-height: 100vh;
}

.xf-header-top {
  background-color: var(--crm-bg-header) !important;
  border-bottom: 1px solid var(--crm-color-border);
  height: 56px;
}

.xf-toolbar {
  min-height: 56px;
  padding: 0 18px;
}

.xf-btn-icon,
.xf-header-action-btn {
  transition: all var(--crm-transition);
  &:hover {
    background: rgba(255, 255, 255, 0.08);
    transform: translateY(-1px);
  }
}

.xf-logo-container {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-left: 6px;
}

.xf-logo-text {
  font-size: 1.12rem;
  font-weight: 800;
  color: var(--crm-color-ink);
  letter-spacing: -0.03em;
  background: linear-gradient(135deg, #ffffff 0%, #cbd5e1 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
}

.xf-logo-badge-pill {
  font-size: 0.62rem;
  font-weight: 800;
  padding: 1px 5px;
  border-radius: 4px;
  background: linear-gradient(135deg, #10b981 0%, #06b6d4 100%);
  color: #ffffff;
  letter-spacing: 0.04em;
}

.xf-logo-sub {
  font-size: 0.68rem;
  font-weight: 500;
  color: var(--crm-color-muted);
  letter-spacing: 0.02em;
  line-height: 1;
}

.xf-badge-pulse {
  font-size: 0.68rem;
  font-weight: 700;
  box-shadow: 0 0 10px rgba(239, 68, 68, 0.5);
}

.xf-org-select {
  min-width: 170px;
  .q-field__control {
    height: 36px;
    min-height: 36px;
    background: var(--crm-bg-card);
    border-radius: 8px;
    border: 1px solid var(--crm-color-border);
    font-size: 0.85rem;
  }
}

.xf-avatar-user {
  background: linear-gradient(135deg, #10b981 0%, #6366f1 100%) !important;
  color: #ffffff;
  font-weight: 700;
  font-size: 0.82rem;
  border: 1.5px solid rgba(255, 255, 255, 0.2);
}

.xf-user-role-badge {
  display: inline-block;
  font-size: 0.7rem;
  font-weight: 600;
  background: rgba(16, 185, 129, 0.15);
  color: #10b981;
  padding: 2px 6px;
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
}

.xf-nav-list {
  padding: 10px 8px;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.xf-nav-section-title {
  font-size: 0.65rem;
  font-weight: 800;
  letter-spacing: 0.08em;
  color: var(--crm-color-muted);
  padding: 8px 12px 4px;
}

.xf-nav-item {
  border-radius: 10px;
  color: var(--crm-color-muted);
  min-height: 38px;
  padding: 6px 12px;
  font-size: 0.84rem;
  font-weight: 500;
  transition: all var(--crm-transition);

  .q-icon {
    font-size: 20px;
    color: var(--crm-color-muted);
    transition: all var(--crm-transition);
  }

  &:hover {
    background: var(--crm-bg-card-hover);
    color: var(--crm-color-ink);
    transform: translateX(2px);

    .q-icon {
      color: var(--crm-color-ink);
    }
  }

  &--active {
    background: linear-gradient(90deg, rgba(16, 185, 129, 0.12) 0%, rgba(16, 185, 129, 0.03) 100%) !important;
    border-left: 3px solid #10b981;
    color: var(--crm-color-ink) !important;
    font-weight: 600;

    .q-icon {
      color: #10b981 !important;
    }
  }
}

.xf-nav-counter {
  font-size: 0.7rem;
  font-weight: 700;
  background: rgba(16, 185, 129, 0.2);
  color: #10b981;
  padding: 2px 6px;
  border-radius: 10px;
}

.xf-status-dot-active {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background-color: #10b981;
  box-shadow: 0 0 6px rgba(16, 185, 129, 0.8);
}

.xf-ai-icon {
  color: #a855f7 !important;
}

.xf-ai-badge {
  font-size: 0.6rem;
  font-weight: 800;
  background: linear-gradient(135deg, #a855f7 0%, #ec4899 100%);
  color: #ffffff;
  padding: 1px 5px;
  border-radius: 4px;
}

.xf-sidebar-footer {
  padding: 12px 10px;
  border-top: 1px solid var(--crm-color-border);
}

.xf-theme-toggle-box {
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: var(--crm-bg-card);
  border: 1px solid var(--crm-color-border);
  padding: 6px 12px;
  border-radius: 10px;
}

.xf-page-container {
  background-color: var(--crm-bg-app);
}

.xf-profile-menu {
  background: var(--crm-bg-card) !important;
  border: 1px solid var(--crm-color-border) !important;
  border-radius: 12px !important;
}

// Transición suave entre páginas
.xf-fade-enter-active,
.xf-fade-leave-active {
  transition: opacity 0.18s ease, transform 0.18s ease;
}

.xf-fade-enter-from {
  opacity: 0;
  transform: translateY(4px);
}

.xf-fade-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}
</style>
