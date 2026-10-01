<template>
  <q-layout view="hHh lpR fFf" class="public-layout">
    <q-page-container>
      <q-page class="public-layout__page">
        <!-- Columna Izquierda: Showcase de Producto V2 Enterprise -->
        <div class="public-layout__hero">
          <div class="public-layout__hero-inner">
            <!-- Brand Badge -->
            <div class="xf-brand-badge q-mb-md">
              <div class="xf-brand-logo">
                <svg width="22" height="22" viewBox="0 0 28 28" fill="none">
                  <rect width="28" height="28" rx="8" fill="url(#heroGrad)" />
                  <path d="M7 14L12 9L16 13L21 8M7 20L12 15L16 19L21 14" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
                  <defs>
                    <linearGradient id="heroGrad" x1="0" y1="0" x2="28" y2="28" gradientUnits="userSpaceOnUse">
                      <stop stop-color="#10B981" />
                      <stop offset="0.5" stop-color="#06B6D4" />
                      <stop offset="1" stop-color="#6366F1" />
                    </linearGradient>
                  </defs>
                </svg>
              </div>
              <span class="xf-brand-title">XpertiFlow CRM</span>
              <span class="xf-pro-pill">V2 PRO</span>
            </div>

            <!-- Gran Título de Impacto -->
            <h1 class="public-layout__title">
              La plataforma <span class="gradient-text">omnicanal</span> diseñada para cerrar ventas.
            </h1>

            <p class="public-layout__subtitle">
              Centraliza WhatsApp, Inbox multicanal, Pipeline comercial Kanban y Copiloto IA con búsqueda vectorial en una sola arquitectura modular de alto rendimiento.
            </p>

            <!-- Tarjetas de Capacidades en Vivo -->
            <div class="public-layout__features q-mt-lg q-gutter-y-sm">
              <!-- Feature 1: WhatsApp Inbox -->
              <div class="feature-card">
                <div class="feature-card__icon bg-teal-10 text-teal-3">
                  <q-icon name="sym_r_forum" size="20px" />
                </div>
                <div>
                  <div class="feature-card__title">Bandeja Multicanal en Tiempo Real</div>
                  <div class="feature-card__desc">Atención unificada WhatsApp Business, derivación automática y modo fantasma para supervisión.</div>
                </div>
                <div class="feature-card__status">
                  <span class="pulse-dot" />
                  <span class="text-caption text-positive font-weight-bold">Activo</span>
                </div>
              </div>

              <!-- Feature 2: Pipeline Comercial -->
              <div class="feature-card">
                <div class="feature-card__icon bg-indigo-10 text-indigo-3">
                  <q-icon name="sym_r_view_kanban" size="20px" />
                </div>
                <div>
                  <div class="feature-card__title">Pipeline Comercial & CRM 360°</div>
                  <div class="feature-card__desc">Tableros Kanban por etapas, importación masiva de contactos y segmentación por etiquetas.</div>
                </div>
              </div>

              <!-- Feature 3: Copiloto Hentle-AI -->
              <div class="feature-card">
                <div class="feature-card__icon bg-purple-10 text-purple-3">
                  <q-icon name="sym_r_psychology" size="20px" />
                </div>
                <div>
                  <div class="feature-card__title">Copiloto Hentle-AI (RAG 768d)</div>
                  <div class="feature-card__desc">Sugerencias inteligentes de respuesta asistidas por búsqueda vectorial pgvector.</div>
                </div>
              </div>
            </div>

            <!-- Footer con badges de infraestructura -->
            <div class="public-layout__tech-stack q-mt-xl row items-center q-gutter-x-md text-caption text-grey-5">
              <span class="row items-center q-gutter-x-xs">
                <q-icon name="sym_r_database" size="14px" color="teal-4" />
                <span>PostgreSQL 16 + pgvector</span>
              </span>
              <span>•</span>
              <span class="row items-center q-gutter-x-xs">
                <q-icon name="sym_r_bolt" size="14px" color="amber-4" />
                <span>Redis 7.4 Caching</span>
              </span>
              <span>•</span>
              <span class="row items-center q-gutter-x-xs">
                <q-icon name="sym_r_verified_user" size="14px" color="cyan-4" />
                <span>Sanctum Multi-Tenant</span>
              </span>
            </div>
          </div>
        </div>

        <!-- Columna Derecha: Tarjeta de Acceso Limpia -->
        <div class="public-layout__card-container">
          <div class="public-layout__card">
            <router-view v-slot="{ Component }">
              <transition name="crm-page" mode="out-in">
                <component :is="Component" />
              </transition>
            </router-view>
          </div>
        </div>
      </q-page>
    </q-page-container>
  </q-layout>
</template>

<style scoped lang="scss">
.public-layout {
  background-color: #080c14 !important;
  background-image:
    radial-gradient(circle at 10% 20%, rgba(16, 185, 129, 0.12) 0%, transparent 35%),
    radial-gradient(circle at 85% 15%, rgba(6, 182, 212, 0.1) 0%, transparent 30%),
    radial-gradient(circle at 50% 80%, rgba(99, 102, 241, 0.08) 0%, transparent 40%),
    linear-gradient(180deg, #080c14 0%, #0d131f 100%) !important;
  min-height: 100vh;
  color: #f8fafc;
}

.public-layout__page {
  min-height: 100vh;
  display: grid;
  grid-template-columns: minmax(0, 1.3fr) minmax(360px, 520px);
  gap: 56px;
  align-items: center;
  max-width: 1440px;
  margin: 0 auto;
  padding: 48px 40px;
}

.public-layout__hero {
  color: #f8fafc;
}

.public-layout__hero-inner {
  max-width: 660px;
}

.xf-brand-badge {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 6px 14px;
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 999px;
  background: rgba(17, 24, 39, 0.7);
  backdrop-filter: blur(12px);
}

.xf-brand-title {
  font-size: 0.88rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  color: #ffffff;
}

.xf-pro-pill {
  font-size: 0.65rem;
  font-weight: 800;
  letter-spacing: 0.1em;
  padding: 2px 6px;
  border-radius: 4px;
  background: linear-gradient(135deg, #10b981 0%, #06b6d4 100%);
  color: #ffffff;
}

.public-layout__title {
  margin: 0 0 18px;
  font-family: var(--crm-font-display, sans-serif);
  font-size: clamp(2.4rem, 4.5vw, 3.8rem);
  font-weight: 800;
  line-height: 1.05;
  letter-spacing: -0.035em;
  color: #ffffff;
}

.gradient-text {
  background: linear-gradient(135deg, #10b981 0%, #06b6d4 50%, #818cf8 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
}

.public-layout__subtitle {
  margin: 0;
  color: #94a3b8;
  font-size: 1.05rem;
  line-height: 1.65;
}

.feature-card {
  display: grid;
  grid-template-columns: auto 1fr auto;
  align-items: center;
  gap: 14px;
  padding: 12px 16px;
  background: rgba(17, 24, 39, 0.55);
  border: 1px solid rgba(255, 255, 255, 0.06);
  border-radius: 12px;
  backdrop-filter: blur(10px);
  transition: transform 0.2s ease, border-color 0.2s ease;

  &:hover {
    transform: translateX(4px);
    border-color: rgba(16, 185, 129, 0.3);
  }
}

.feature-card__icon {
  width: 38px;
  height: 38px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.feature-card__title {
  font-size: 0.88rem;
  font-weight: 700;
  color: #ffffff;
}

.feature-card__desc {
  font-size: 0.78rem;
  color: #94a3b8;
  line-height: 1.4;
}

.feature-card__status {
  display: flex;
  align-items: center;
  gap: 6px;
}

.pulse-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background-color: #10b981;
  box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
  animation: pulse-ring 2s infinite;
}

@keyframes pulse-ring {
  0% {
    box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
  }
  70% {
    box-shadow: 0 0 0 8px rgba(16, 185, 129, 0);
  }
  100% {
    box-shadow: 0 0 0 0 rgba(16, 185, 129, 0);
  }
}

.public-layout__card-container {
  width: 100%;
}

.public-layout__card {
  padding: 36px 32px;
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 20px;
  background: rgba(17, 24, 39, 0.82);
  box-shadow: 0 20px 40px -15px rgba(0, 0, 0, 0.7), 0 0 30px -5px rgba(16, 185, 129, 0.1);
  backdrop-filter: blur(24px);
}

@media (max-width: 1080px) {
  .public-layout__page {
    grid-template-columns: 1fr;
    gap: 36px;
    padding: 32px 20px;
  }

  .public-layout__hero-inner {
    max-width: 100%;
  }
}
</style>
