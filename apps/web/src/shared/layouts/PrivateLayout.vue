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
            <span>Fase 2: Conexión WhatsApp & Bandeja Omnicanal</span>
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

          <!-- Avatar / Menú de Usuario -->
          <q-btn flat round dense class="q-ml-xs">
            <q-avatar size="32px" class="xf-avatar-user">
              {{ initials }}
            </q-avatar>
            <q-menu anchor="bottom right" self="top right" dark class="xf-profile-menu">
              <div class="q-pa-md">
                <div class="text-weight-bold text-white">{{ authStore.user?.name ?? 'Admin' }}</div>
                <div class="text-caption text-grey-4">{{ authStore.user?.email ?? 'admin@crm.local' }}</div>
                <div class="xf-user-role-badge q-mt-xs">Fase 2 • Superadmin</div>
              </div>
              <q-separator dark class="q-my-xs" />
              <q-list dense>
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

    <!-- Sidebar Lateral Minimalista — Fase 2 Activa -->
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
              Fase 2 • Módulos Activos
            </div>
          </div>

          <!-- Lista de Navegación: Fase 1 + Fase 2 -->
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

            <!-- 2. Bandeja Omnicanal (Fase 2) -->
            <q-item
              clickable
              to="/app/conversations"
              class="xf-nav-item"
              active-class="xf-nav-item--active"
            >
              <q-item-section avatar>
                <q-icon name="sym_r_chat" size="18px" />
              </q-item-section>
              <q-item-section>Bandeja Omnicanal</q-item-section>
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

            <!-- 4. Audiencia & Contactos -->
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

            <!-- 5. Etiquetas & Segmentos -->
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

            <!-- 6. Empresas & Cuentas -->
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
          </q-list>

          <!-- Tarjeta Minimalista del Roadmap V2 -->
          <div class="xf-roadmap-card q-mx-sm q-mt-md">
            <div class="row items-center justify-between q-mb-xs">
              <span class="text-caption text-weight-bold text-white">Roadmap V2</span>
              <span class="text-caption text-teal-4 text-weight-medium">Fase 2 / 6</span>
            </div>
            <p class="text-caption text-grey-5 q-mb-xs roadmap-text">
              Fase 3 (Pipeline Comercial & Kanban) se desbloqueará en su etapa.
            </p>
            <q-linear-progress :value="0.33" color="positive" track-color="grey-9" rounded size="4px" />
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
</style>
