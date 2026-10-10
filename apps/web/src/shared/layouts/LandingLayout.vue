<template>
  <q-layout view="hHh lpR fFf" class="landing-layout">
    <!-- Header de Navegación Sticky con Glassmorphism -->
    <header class="landing-header">
      <div class="landing-header__inner">
        <!-- Logo Brand -->
        <router-link to="/planes" class="landing-brand">
          <div class="landing-brand__icon">
            <svg width="30" height="30" viewBox="0 0 28 28" fill="none">
              <rect width="28" height="28" rx="8" fill="#10B981" />
              <path d="M7 14L12 9L16 13L21 8M7 20L12 15L16 19L21 14" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </div>
          <div class="landing-brand__text">
            <span class="landing-brand__title">XpertiFlow</span>
            <span class="landing-brand__badge">V2 SUITE</span>
          </div>
        </router-link>

        <!-- Enlaces Rápidos de Navegación -->
        <nav class="landing-nav desktop-only">
          <a href="#planes" class="landing-nav__link">Planes</a>
          <a href="#calculadora" class="landing-nav__link">Calculadora</a>
          <a href="#comparativa" class="landing-nav__link">Comparativa</a>
          <a href="#faq" class="landing-nav__link">Preguntas</a>
        </nav>

        <!-- Acciones: Tema y Login -->
        <div class="landing-header__actions">
          <button
            type="button"
            class="landing-theme-btn"
            :title="isDark ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro'"
            @click="toggleTheme"
          >
            <q-icon :name="isDark ? 'sym_r_light_mode' : 'sym_r_dark_mode'" size="18px" />
          </button>

          <router-link to="/" class="landing-login-btn">
            <span>Iniciar Sesión</span>
            <q-icon name="sym_r_arrow_forward" size="16px" />
          </router-link>
        </div>
      </div>
    </header>

    <!-- Contenedor Principal -->
    <q-page-container class="landing-container">
      <router-view v-slot="{ Component }">
        <transition name="xf-fade" mode="out-in">
          <component :is="Component" />
        </transition>
      </router-view>
    </q-page-container>

    <!-- Footer Corporativo Elegante -->
    <footer class="landing-footer">
      <div class="landing-footer__inner">
        <div class="landing-footer__brand">
          <div class="landing-brand">
            <div class="landing-brand__icon">
              <svg width="24" height="24" viewBox="0 0 28 28" fill="none">
                <rect width="28" height="28" rx="8" fill="#10B981" />
                <path d="M7 14L12 9L16 13L21 8M7 20L12 15L16 19L21 14" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </div>
            <span class="landing-brand__title" style="font-size: 1.1rem;">XpertiFlow CRM</span>
          </div>
          <p class="landing-footer__desc">
            Plataforma Omnicanal Integral de Ventas, WhatsApp Multiagente y Copiloto IA Autónomo.
          </p>
        </div>

        <div class="landing-footer__links">
          <div class="landing-footer__col">
            <div class="landing-footer__title">Producto</div>
            <a href="#planes" class="landing-footer__link">Planes y Precios</a>
            <a href="#calculadora" class="landing-footer__link">Calculador de Costos</a>
            <a href="#comparativa" class="landing-footer__link">Matriz de Funciones</a>
          </div>
          <div class="landing-footer__col">
            <div class="landing-footer__title">Canales & IA</div>
            <span class="landing-footer__link-text">WhatsApp Cloud & QR</span>
            <span class="landing-footer__link-text">Instagram & Messenger</span>
            <span class="landing-footer__link-text">Hentle-AI RAG Vectorial</span>
          </div>
          <div class="landing-footer__col">
            <div class="landing-footer__title">Acceso</div>
            <router-link to="/" class="landing-footer__link">Portal de Clientes</router-link>
            <router-link to="/app/dashboard" class="landing-footer__link">Workspace Activo</router-link>
          </div>
        </div>
      </div>

      <div class="landing-footer__bottom">
        <span>© 2026 XpertiFlow CRM V2. Todos los derechos reservados.</span>
        <span class="text-dim">Diseño de Alta Fidelidad • Gentle AI™ Architecture</span>
      </div>
    </footer>
  </q-layout>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'

const isDark = ref(true)

function toggleTheme() {
  isDark.value = !isDark.value
  applyTheme(isDark.value)
}

function applyTheme(dark: boolean) {
  if (dark) {
    document.documentElement.removeAttribute('data-theme')
    document.body.classList.remove('body--light')
    localStorage.setItem('xpertiflow-theme', 'dark')
  } else {
    document.documentElement.setAttribute('data-theme', 'light')
    document.body.classList.add('body--light')
    localStorage.setItem('xpertiflow-theme', 'light')
  }
}

onMounted(() => {
  const saved = localStorage.getItem('xpertiflow-theme')
  if (saved === 'light') {
    isDark.value = false
    applyTheme(false)
  } else {
    isDark.value = true
    applyTheme(true)
  }
})
</script>

<style scoped lang="scss">
.landing-layout {
  min-height: 100vh;
  background-color: var(--crm-bg-app);
  color: var(--crm-color-ink);
  overflow-x: hidden;
  position: relative;
}

// Header
.landing-header {
  position: sticky;
  top: 0;
  z-index: 1000;
  background: var(--crm-bg-header);
  backdrop-filter: blur(16px);
  -webkit-backdrop-filter: blur(16px);
  border-bottom: 1px solid var(--crm-color-border);
  transition: all var(--crm-transition);
}

.landing-header__inner {
  max-width: 1240px;
  margin: 0 auto;
  padding: 14px 24px;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.landing-brand {
  display: flex;
  align-items: center;
  gap: 12px;
  text-decoration: none;
}

.landing-brand__icon {
  display: flex;
  align-items: center;
  justify-content: center;
  transition: transform var(--crm-transition);
  &:hover {
    transform: rotate(4deg) scale(1.05);
  }
}

.landing-brand__text {
  display: flex;
  align-items: center;
  gap: 8px;
}

.landing-brand__title {
  font-size: 1.25rem;
  font-weight: 800;
  color: var(--crm-color-ink);
  letter-spacing: -0.02em;
}

.landing-brand__badge {
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.05em;
  padding: 2px 7px;
  border-radius: var(--crm-radius-pill);
  background: var(--crm-color-primary-soft);
  color: var(--crm-color-primary);
  border: 1px solid rgba(16, 185, 129, 0.25);
}

// Nav Links
.landing-nav {
  display: flex;
  align-items: center;
  gap: 28px;
}

.landing-nav__link {
  font-size: 0.9rem;
  font-weight: 500;
  color: var(--crm-color-muted);
  text-decoration: none;
  transition: color var(--crm-transition-fast);

  &:hover {
    color: var(--crm-color-primary);
  }
}

// Actions
.landing-header__actions {
  display: flex;
  align-items: center;
  gap: 12px;
}

.landing-theme-btn {
  width: 38px;
  height: 38px;
  border-radius: var(--crm-radius-control);
  background: rgba(255, 255, 255, 0.04);
  border: 1px solid var(--crm-color-border);
  color: var(--crm-color-ink);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all var(--crm-transition);

  &:hover {
    background: rgba(255, 255, 255, 0.08);
    border-color: var(--crm-color-border-hover);
    transform: translateY(-1px);
  }
}

.landing-login-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 16px;
  border-radius: var(--crm-radius-control);
  background: var(--crm-color-primary);
  color: #ffffff;
  font-size: 0.85rem;
  font-weight: 600;
  text-decoration: none;
  transition: all var(--crm-transition);
  box-shadow: 0 2px 8px rgba(16, 185, 129, 0.25);

  &:hover {
    background: var(--crm-color-primary-hover);
    transform: translateY(-1px);
    box-shadow: 0 4px 14px rgba(16, 185, 129, 0.35);
  }
}

// Container
.landing-container {
  padding: 0 !important;
}

// Footer
.landing-footer {
  border-top: 1px solid var(--crm-color-border);
  background: var(--crm-bg-sidebar);
  margin-top: 80px;
  padding: 60px 24px 30px;
}

.landing-footer__inner {
  max-width: 1240px;
  margin: 0 auto;
  display: grid;
  grid-template-columns: 1.5fr 2fr;
  gap: 48px;

  @media (max-width: 768px) {
    grid-template-columns: 1fr;
    gap: 32px;
  }
}

.landing-footer__desc {
  color: var(--crm-color-muted);
  font-size: 0.88rem;
  margin-top: 12px;
  max-width: 360px;
  line-height: 1.6;
}

.landing-footer__links {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 24px;

  @media (max-width: 580px) {
    grid-template-columns: 1fr;
  }
}

.landing-footer__title {
  font-size: 0.82rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--crm-color-dim);
  margin-bottom: 14px;
}

.landing-footer__link {
  display: block;
  font-size: 0.86rem;
  color: var(--crm-color-muted);
  text-decoration: none;
  margin-bottom: 8px;
  transition: color var(--crm-transition-fast);

  &:hover {
    color: var(--crm-color-primary);
  }
}

.landing-footer__link-text {
  display: block;
  font-size: 0.86rem;
  color: var(--crm-color-dim);
  margin-bottom: 8px;
}

.landing-footer__bottom {
  max-width: 1240px;
  margin: 40px auto 0;
  padding-top: 24px;
  border-top: 1px solid var(--crm-color-border);
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 0.82rem;
  color: var(--crm-color-dim);
  flex-wrap: wrap;
  gap: 12px;
}
</style>
