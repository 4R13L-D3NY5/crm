<template>
  <div class="pricing-page">
    <!-- Luces de fondo ambientales / Aurora Glow -->
    <div class="pricing-glow-orb pricing-glow-orb--1" />
    <div class="pricing-glow-orb pricing-glow-orb--2" />
    <div class="pricing-glow-orb pricing-glow-orb--3" />

    <!-- 1. HERO SECTION -->
    <section class="pricing-hero">
      <div class="pricing-badge">
        <span class="pricing-badge__dot" />
        <span>Omnichannel Suite Pro V2 • Planes 100% Transparentes</span>
      </div>

      <h1 class="pricing-title">
        La plataforma conversacional que <br />
        <span class="text-gradient">multiplica tus ventas</span> y automatiza tu soporte
      </h1>

      <p class="pricing-subtitle">
        Centraliza WhatsApp, Instagram y Messenger en un solo lugar. Conecta a todo tu equipo,
        añade números según tu crecimiento e impulsa la atención 24/7 con el poder de Hentle-AI.
      </p>

      <!-- Selector de Facturación (Mensual / Anual) -->
      <div class="billing-toggle-container">
        <div class="billing-toggle">
          <button
            type="button"
            class="billing-toggle__btn"
            :class="{ 'billing-toggle__btn--active': billingCycle === 'monthly' }"
            @click="billingCycle = 'monthly'"
          >
            Facturación Mensual
          </button>
          <button
            type="button"
            class="billing-toggle__btn"
            :class="{ 'billing-toggle__btn--active': billingCycle === 'annual' }"
            @click="billingCycle = 'annual'"
          >
            Facturación Anual
            <span class="billing-discount-badge">Ahorra 15%</span>
          </button>
        </div>
      </div>
    </section>

    <!-- 2. TARJETAS DE PLANES (GRID) -->
    <section id="planes" class="pricing-cards-section">
      <div class="pricing-cards-grid">
        <!-- PLAN 1: BÁSICO -->
        <div class="pricing-card">
          <div class="pricing-card__header">
            <div class="pricing-card__badge-row">
              <span class="tier-pill tier-pill--basic">Plan Básico</span>
              <span class="tier-caption">Para arrancar</span>
            </div>
            <h3 class="pricing-card__title">Starter Omnichannel</h3>
            <p class="pricing-card__desc">
              Todo lo necesario para salir del caos del celular y profesionalizar la atención de tu equipo.
            </p>

            <div class="pricing-price-box">
              <div class="pricing-price">
                <span class="pricing-currency">$</span>
                <span class="pricing-amount">{{ billingCycle === 'monthly' ? '120' : '102' }}</span>
                <span class="pricing-period">USD / mes</span>
              </div>
              <div class="pricing-price-sub">
                {{ billingCycle === 'annual' ? 'Facturado anualmente ($1,224 / año)' : 'Facturación mes a mes sin ataduras' }}
              </div>
            </div>
          </div>

          <!-- Specs Clave Destacados -->
          <div class="pricing-specs-highlight">
            <div class="spec-item">
              <q-icon name="sym_r_phone_iphone" size="18px" color="primary" />
              <span><strong>1 Número de WhatsApp</strong> incluido</span>
            </div>
            <div class="spec-item">
              <q-icon name="sym_r_group" size="18px" color="primary" />
              <span><strong>3 Agentes</strong> incluidos</span>
            </div>
            <div class="spec-item spec-item--addon">
              <q-icon name="sym_r_add_circle" size="16px" color="accent" />
              <span><strong>+$20 USD/mes</strong> por número adicional</span>
            </div>
          </div>

          <div class="pricing-card__divider" />

          <!-- Lista de Características -->
          <div class="pricing-features">
            <div class="pricing-features__title">Incluye todo lo esencial:</div>
            <ul class="features-list">
              <li>
                <q-icon name="sym_r_check_circle" size="17px" class="check-icon" />
                <span>Bandeja colaborativa omnicanal con notas internas</span>
              </li>
              <li>
                <q-icon name="sym_r_check_circle" size="17px" class="check-icon" />
                <span>WhatsApp Web QR y WhatsApp Cloud API oficial</span>
              </li>
              <li>
                <q-icon name="sym_r_check_circle" size="17px" class="check-icon" />
                <span>Directorio de Contactos y Empresas B2B (hasta 3,000)</span>
              </li>
              <li>
                <q-icon name="sym_r_check_circle" size="17px" class="check-icon" />
                <span>1 Embudo comercial Kanban para Deals y ventas</span>
              </li>
              <li>
                <q-icon name="sym_r_check_circle" size="17px" class="check-icon" />
                <span>Respuestas rápidas (`/atajos`) y etiquetas</span>
              </li>
              <li>
                <q-icon name="sym_r_check_circle" size="17px" class="check-icon" />
                <span>Mensajes programados con recordatorio</span>
              </li>
              <li>
                <q-icon name="sym_r_check_circle" size="17px" class="check-icon" />
                <span>Soporte estándar vía chat y documentación</span>
              </li>
            </ul>
          </div>

          <button
            type="button"
            class="pricing-btn pricing-btn--secondary"
            @click="openContactModal('Plan Básico Starter ($120/mes)')"
          >
            <span>Elegir Plan Básico</span>
            <q-icon name="sym_r_arrow_forward" size="16px" />
          </button>
        </div>

        <!-- PLAN 2: PRO (MÁS POPULAR) -->
        <div class="pricing-card pricing-card--featured">
          <div class="featured-ribbon">
            <q-icon name="sym_r_star" size="13px" />
            <span>MÁS POPULAR</span>
          </div>

          <div class="pricing-card__header">
            <div class="pricing-card__badge-row">
              <span class="tier-pill tier-pill--pro">Plan Pro</span>
              <span class="tier-caption text-primary">Mejor relación valor/precio</span>
            </div>
            <h3 class="pricing-card__title">Growth & Automation</h3>
            <p class="pricing-card__desc">
              Para empresas con equipos comerciales y de soporte que buscan automatizar flujos y escalar.
            </p>

            <div class="pricing-price-box">
              <div class="pricing-price">
                <span class="pricing-currency">$</span>
                <span class="pricing-amount">{{ billingCycle === 'monthly' ? '230' : '195' }}</span>
                <span class="pricing-period">USD / mes</span>
              </div>
              <div class="pricing-price-sub">
                {{ billingCycle === 'annual' ? 'Facturado anualmente ($2,340 / año • Ahorras $420)' : 'Facturación mes a mes' }}
              </div>
            </div>
          </div>

          <!-- Specs Clave Destacados -->
          <div class="pricing-specs-highlight pricing-specs-highlight--featured">
            <div class="spec-item">
              <q-icon name="sym_r_phone_iphone" size="18px" color="primary" />
              <span><strong>2 Números de WhatsApp</strong> incluidos</span>
            </div>
            <div class="spec-item">
              <q-icon name="sym_r_group" size="18px" color="primary" />
              <span><strong>7 Agentes</strong> incluidos</span>
            </div>
            <div class="spec-item spec-item--addon">
              <q-icon name="sym_r_add_circle" size="16px" color="accent" />
              <span><strong>+$20 USD/mes</strong> por número adicional</span>
            </div>
          </div>

          <div class="pricing-card__divider" />

          <!-- Lista de Características -->
          <div class="pricing-features">
            <div class="pricing-features__title">Todo lo de Básico, más:</div>
            <ul class="features-list">
              <li>
                <q-icon name="sym_r_check_circle" size="17px" class="check-icon" />
                <span><strong>Multicanal completo:</strong> WhatsApp + Instagram DM + Facebook</span>
              </li>
              <li>
                <q-icon name="sym_r_check_circle" size="17px" class="check-icon" />
                <span><strong>Filas & Departamentos:</strong> Enrutamiento a Ventas, Soporte, etc.</span>
              </li>
              <li>
                <q-icon name="sym_r_check_circle" size="17px" class="check-icon" />
                <span><strong>Bot Flows Interactivos:</strong> Menús guiados y bienvenida (1, 2, 3...)</span>
              </li>
              <li>
                <q-icon name="sym_r_check_circle" size="17px" class="check-icon" />
                <span><strong>Campañas Masivas:</strong> Con algoritmo anti-baneo y jitter aleatorio</span>
              </li>
              <li>
                <q-icon name="sym_r_check_circle" size="17px" class="check-icon" />
                <span><strong>IA Asistente (Lite):</strong> Resumen de conversaciones y corrección de tono</span>
              </li>
              <li>
                <q-icon name="sym_r_check_circle" size="17px" class="check-icon" />
                <span>3 Embudos comerciales Kanban + Contactos ampliados (hasta 15,000)</span>
              </li>
              <li>
                <q-icon name="sym_r_check_circle" size="17px" class="check-icon" />
                <span>Métricas de SLA, tiempos de primera respuesta y satisfacción CSAT</span>
              </li>
            </ul>
          </div>

          <button
            type="button"
            class="pricing-btn pricing-btn--primary"
            @click="openContactModal('Plan Pro Growth ($230/mes)')"
          >
            <span>Elegir Plan Pro</span>
            <q-icon name="sym_r_bolt" size="16px" />
          </button>
        </div>

        <!-- PLAN 3: ELITE IA (AUTONOMOUS AI SUITE) -->
        <div class="pricing-card pricing-card--elite">
          <div class="pricing-card__header">
            <div class="pricing-card__badge-row">
              <span class="tier-pill tier-pill--elite">Plan Elite IA</span>
              <span class="tier-caption text-secondary">Suite Total Autónomo</span>
            </div>
            <h3 class="pricing-card__title">Enterprise & Hentle-AI</h3>
            <p class="pricing-card__desc">
              Máxima potencia. Agente autónomo con Inteligencia Artificial, RAG vectorial y capacidad corporativa.
            </p>

            <div class="pricing-price-box">
              <div class="pricing-price">
                <span class="pricing-currency">$</span>
                <span class="pricing-amount">{{ billingCycle === 'monthly' ? '390' : '330' }}</span>
                <span class="pricing-period">USD / mes</span>
              </div>
              <div class="pricing-price-sub">
                {{ billingCycle === 'annual' ? 'Facturado anualmente ($3,960 / año • Ahorras $720)' : 'Facturación mes a mes' }}
              </div>
            </div>
          </div>

          <!-- Specs Clave Destacados -->
          <div class="pricing-specs-highlight pricing-specs-highlight--elite">
            <div class="spec-item">
              <q-icon name="sym_r_phone_iphone" size="18px" color="secondary" />
              <span><strong>4 Números de WhatsApp</strong> incluidos</span>
            </div>
            <div class="spec-item">
              <q-icon name="sym_r_group" size="18px" color="secondary" />
              <span><strong>15 Agentes</strong> incluidos</span>
            </div>
            <div class="spec-item spec-item--addon">
              <q-icon name="sym_r_add_circle" size="16px" color="accent" />
              <span><strong>+$20 USD/mes</strong> por número adicional</span>
            </div>
          </div>

          <div class="pricing-card__divider" />

          <!-- Lista de Características -->
          <div class="pricing-features">
            <div class="pricing-features__title">El poder completo de IA:</div>
            <ul class="features-list">
              <li>
                <q-icon name="sym_r_auto_awesome" size="17px" class="check-icon check-icon--ai" />
                <span><strong>Agente Autónomo Hentle-AI 24/7:</strong> Responde con precisión humana</span>
              </li>
              <li>
                <q-icon name="sym_r_auto_awesome" size="17px" class="check-icon check-icon--ai" />
                <span><strong>Base RAG (`pgvector` 768 dims):</strong> Ingesta de catálogos, PDFs y FAQs</span>
              </li>
              <li>
                <q-icon name="sym_r_auto_awesome" size="17px" class="check-icon check-icon--ai" />
                <span><strong>Copiloto en vivo:</strong> Sugerencias instantáneas 1-clic a agentes humanos</span>
              </li>
              <li>
                <q-icon name="sym_r_auto_awesome" size="17px" class="check-icon check-icon--ai" />
                <span><strong>Voice-to-Text:</strong> Transcripción y resumen automático de audios</span>
              </li>
              <li>
                <q-icon name="sym_r_check_circle" size="17px" class="check-icon" />
                <span>Embudos comerciales y Contactos <strong>ilimitados</strong></span>
              </li>
              <li>
                <q-icon name="sym_r_check_circle" size="17px" class="check-icon" />
                <span>Módulo de Auditoría avanzada & Logs de seguridad completos</span>
              </li>
              <li>
                <q-icon name="sym_r_check_circle" size="17px" class="check-icon" />
                <span>Acceso completo a API & Webhooks para integración con ERPs</span>
              </li>
              <li>
                <q-icon name="sym_r_check_circle" size="17px" class="check-icon" />
                <span><strong>Onboarding VIP 1-a-1:</strong> Calibración dedicada del modelo de IA</span>
              </li>
            </ul>
          </div>

          <button
            type="button"
            class="pricing-btn pricing-btn--elite"
            @click="openContactModal('Plan Elite IA Enterprise ($390/mes)')"
          >
            <span>Elegir Plan Elite IA</span>
            <q-icon name="sym_r_auto_awesome" size="16px" />
          </button>
        </div>
      </div>
    </section>

    <!-- 3. CALCULADORA INTERACTIVA DE INVERSIÓN Y ADD-ONS -->
    <section id="calculadora" class="pricing-calc-section">
      <div class="section-header">
        <div class="section-tag">Calculadora en Tiempo Real</div>
        <h2 class="section-title">Calcula tu plan a la medida</h2>
        <p class="section-subtitle">
          Personaliza los números de WhatsApp y agentes que necesita tu operación y obtén una cotización al instante.
        </p>
      </div>

      <div class="calculator-box">
        <div class="calculator-inputs">
          <!-- Selector de Plan Base -->
          <div class="calc-group">
            <label class="calc-label">1. Selecciona tu Plan Base:</label>
            <div class="calc-plan-pills">
              <button
                type="button"
                class="calc-plan-pill"
                :class="{ 'calc-plan-pill--active': calcPlan === 'basic' }"
                @click="calcPlan = 'basic'"
              >
                <div class="calc-plan-name">Básico Starter</div>
                <div class="calc-plan-price">${{ billingCycle === 'monthly' ? '120' : '102' }}/mes</div>
                <div class="calc-plan-meta">1 WhatsApp • 3 agentes</div>
              </button>

              <button
                type="button"
                class="calc-plan-pill"
                :class="{ 'calc-plan-pill--active': calcPlan === 'pro' }"
                @click="calcPlan = 'pro'"
              >
                <div class="calc-plan-name">Pro Growth</div>
                <div class="calc-plan-price">${{ billingCycle === 'monthly' ? '230' : '195' }}/mes</div>
                <div class="calc-plan-meta">2 WhatsApp • 7 agentes</div>
              </button>

              <button
                type="button"
                class="calc-plan-pill"
                :class="{ 'calc-plan-pill--active': calcPlan === 'elite' }"
                @click="calcPlan = 'elite'"
              >
                <div class="calc-plan-name">Elite IA Suite</div>
                <div class="calc-plan-price">${{ billingCycle === 'monthly' ? '390' : '330' }}/mes</div>
                <div class="calc-plan-meta">4 WhatsApp • 15 agentes • IA RAG</div>
              </button>
            </div>
          </div>

          <!-- Selector de Números WhatsApp Adicionales (+ $20 c/u) -->
          <div class="calc-group">
            <div class="calc-label-row">
              <label class="calc-label">
                2. Números de WhatsApp adicionales (+<strong>$20 USD/mes</strong> c/u):
              </label>
              <span class="calc-counter-val">+{{ extraPhones }} líneas extra</span>
            </div>
            <div class="calc-stepper-row">
              <button
                type="button"
                class="stepper-btn"
                :disabled="extraPhones <= 0"
                @click="extraPhones = Math.max(0, extraPhones - 1)"
              >
                <q-icon name="sym_r_remove" size="18px" />
              </button>
              <div class="stepper-display">
                <span class="stepper-number">{{ currentTotalPhones }}</span>
                <span class="stepper-caption">
                  Líneas totales ({{ basePlanPhones }} base + {{ extraPhones }} extra)
                </span>
              </div>
              <button
                type="button"
                class="stepper-btn"
                @click="extraPhones++"
              >
                <q-icon name="sym_r_add" size="18px" />
              </button>
            </div>
          </div>

          <!-- Selector de Agentes Adicionales -->
          <div class="calc-group">
            <div class="calc-label-row">
              <label class="calc-label">
                3. Agentes / Operadores adicionales (+<strong>${{ agentAddonPrice }} USD/mes</strong> c/u):
              </label>
              <span class="calc-counter-val">+{{ extraAgents }} agentes extra</span>
            </div>
            <div class="calc-stepper-row">
              <button
                type="button"
                class="stepper-btn"
                :disabled="extraAgents <= 0"
                @click="extraAgents = Math.max(0, extraAgents - 1)"
              >
                <q-icon name="sym_r_remove" size="18px" />
              </button>
              <div class="stepper-display">
                <span class="stepper-number">{{ currentTotalAgents }}</span>
                <span class="stepper-caption">
                  Agentes totales ({{ basePlanAgents }} base + {{ extraAgents }} extra)
                </span>
              </div>
              <button
                type="button"
                class="stepper-btn"
                @click="extraAgents++"
              >
                <q-icon name="sym_r_add" size="18px" />
              </button>
            </div>
          </div>
        </div>

        <!-- Resumen de Cotización en Vivo -->
        <div class="calculator-summary">
          <div class="summary-header">
            <span class="summary-tag">Presupuesto Estimado</span>
            <h4 class="summary-title">Resumen de Inversión</h4>
          </div>

          <div class="summary-items">
            <div class="summary-line">
              <span>Plan Base ({{ currentPlanName }}):</span>
              <span class="summary-amount">${{ currentPlanBaseCost }} USD</span>
            </div>

            <div class="summary-line" v-if="extraPhones > 0">
              <span>{{ extraPhones }} Líneas extra WhatsApp ($20 c/u):</span>
              <span class="summary-amount">+${{ extraPhonesCost }} USD</span>
            </div>

            <div class="summary-line" v-if="extraAgents > 0">
              <span>{{ extraAgents }} Agentes extra (${{ agentAddonPrice }} c/u):</span>
              <span class="summary-amount">+${{ extraAgentsCost }} USD</span>
            </div>

            <div class="summary-line" v-if="billingCycle === 'annual'">
              <span class="text-positive">Ahorro anual aplicado (-15%):</span>
              <span class="summary-amount text-positive">-${{ totalAnnualDiscount }} USD</span>
            </div>
          </div>

          <div class="summary-total-box">
            <div class="summary-total-label">Total mensual estimado:</div>
            <div class="summary-total-price">
              <span class="currency">$</span>
              <span class="val">{{ calculatedMonthlyTotal }}</span>
              <span class="period">USD / mes</span>
            </div>
            <div class="summary-total-sub" v-if="billingCycle === 'annual'">
              Total anual: ${{ calculatedAnnualTotal }} USD (2 meses equivalentes de regalo)
            </div>
            <div class="summary-total-sub" v-else>
              Sin permanencia, cancelas o modificas cuando quieras.
            </div>
          </div>

          <button
            type="button"
            class="pricing-btn pricing-btn--primary q-mt-md"
            style="width: 100%; justify-content: center;"
            @click="openQuoteModal"
          >
            <q-icon name="sym_r_whatsapp" size="18px" />
            <span>Solicitar esta cotización por WhatsApp</span>
          </button>
        </div>
      </div>
    </section>

    <!-- 4. TABLA COMPARATIVA DETALLADA DE CARACTERÍSTICAS -->
    <section id="comparativa" class="pricing-matrix-section">
      <div class="section-header">
        <div class="section-tag">Comparativa Completa</div>
        <h2 class="section-title">Compara cada módulo lado a lado</h2>
        <p class="section-subtitle">
          Revisa a fondo las capacidades técnicas y límites de cada plan.
        </p>
      </div>

      <div class="matrix-table-wrapper">
        <table class="matrix-table">
          <thead>
            <tr>
              <th class="matrix-th-feature">Capacidad / Módulo</th>
              <th class="matrix-th-tier">Plan Básico</th>
              <th class="matrix-th-tier matrix-th-tier--featured">Plan Pro</th>
              <th class="matrix-th-tier">Plan Elite IA</th>
            </tr>
          </thead>
          <tbody>
            <!-- CATEGORÍA: CANALES & LÍMITES -->
            <tr class="matrix-category-row">
              <td colspan="4">Canales de Comunicación & Capacidad</td>
            </tr>
            <tr>
              <td class="feature-name">Líneas de WhatsApp incluidas</td>
              <td class="feature-val">1 Línea</td>
              <td class="feature-val feature-val--featured">2 Líneas</td>
              <td class="feature-val feature-val--bold">4 Líneas</td>
            </tr>
            <tr>
              <td class="feature-name">Costo por Línea WhatsApp extra</td>
              <td class="feature-val">+$20 USD / mes</td>
              <td class="feature-val feature-val--featured">+$20 USD / mes</td>
              <td class="feature-val feature-val--bold">+$20 USD / mes</td>
            </tr>
            <tr>
              <td class="feature-name">Agentes incluidos</td>
              <td class="feature-val">3 agentes</td>
              <td class="feature-val feature-val--featured">7 agentes</td>
              <td class="feature-val feature-val--bold">15 agentes</td>
            </tr>
            <tr>
              <td class="feature-name">Redes Sociales (Instagram & Messenger)</td>
              <td class="feature-val text-dim">—</td>
              <td class="feature-val feature-val--featured"><q-icon name="sym_r_check" color="positive" size="18px" /></td>
              <td class="feature-val"><q-icon name="sym_r_check" color="positive" size="18px" /></td>
            </tr>
            <tr>
              <td class="feature-name">Contactos en el CRM</td>
              <td class="feature-val">Hasta 3,000</td>
              <td class="feature-val feature-val--featured">Hasta 15,000</td>
              <td class="feature-val feature-val--bold">Ilimitados</td>
            </tr>

            <!-- CATEGORÍA: ATENCIÓN & AUTOMATIZACIÓN -->
            <tr class="matrix-category-row">
              <td colspan="4">Atención & Automatización</td>
            </tr>
            <tr>
              <td class="feature-name">Bandeja colaborativa con notas internas</td>
              <td class="feature-val"><q-icon name="sym_r_check" color="positive" size="18px" /></td>
              <td class="feature-val feature-val--featured"><q-icon name="sym_r_check" color="positive" size="18px" /></td>
              <td class="feature-val"><q-icon name="sym_r_check" color="positive" size="18px" /></td>
            </tr>
            <tr>
              <td class="feature-name">Filas & Departamentos (Soporte, Ventas...)</td>
              <td class="feature-val text-dim">—</td>
              <td class="feature-val feature-val--featured"><q-icon name="sym_r_check" color="positive" size="18px" /></td>
              <td class="feature-val"><q-icon name="sym_r_check" color="positive" size="18px" /></td>
            </tr>
            <tr>
              <td class="feature-name">Bot Flows (Menús interactivos 1, 2, 3...)</td>
              <td class="feature-val text-dim">—</td>
              <td class="feature-val feature-val--featured"><q-icon name="sym_r_check" color="positive" size="18px" /></td>
              <td class="feature-val"><q-icon name="sym_r_check" color="positive" size="18px" /></td>
            </tr>
            <tr>
              <td class="feature-name">Campañas masivas con Jitter anti-baneo</td>
              <td class="feature-val text-dim">—</td>
              <td class="feature-val feature-val--featured"><q-icon name="sym_r_check" color="positive" size="18px" /></td>
              <td class="feature-val"><q-icon name="sym_r_check" color="positive" size="18px" /></td>
            </tr>

            <!-- CATEGORÍA: INTELIGENCIA ARTIFICIAL -->
            <tr class="matrix-category-row">
              <td colspan="4">Inteligencia Artificial (Hentle-AI Suite)</td>
            </tr>
            <tr>
              <td class="feature-name">Agente IA Autónomo 24/7 en lenguaje natural</td>
              <td class="feature-val text-dim">—</td>
              <td class="feature-val text-dim feature-val--featured">—</td>
              <td class="feature-val feature-val--bold"><q-icon name="sym_r_auto_awesome" color="secondary" size="18px" /> Incluido</td>
            </tr>
            <tr>
              <td class="feature-name">Base de Conocimiento RAG (`pgvector` 768 dims)</td>
              <td class="feature-val text-dim">—</td>
              <td class="feature-val text-dim feature-val--featured">—</td>
              <td class="feature-val feature-val--bold"><q-icon name="sym_r_auto_awesome" color="secondary" size="18px" /> Ilimitada</td>
            </tr>
            <tr>
              <td class="feature-name">Copiloto IA en vivo para humanos (Next Best Action)</td>
              <td class="feature-val text-dim">—</td>
              <td class="feature-val feature-val--featured">Básico (resumen)</td>
              <td class="feature-val feature-val--bold"><q-icon name="sym_r_auto_awesome" color="secondary" size="18px" /> Completo</td>
            </tr>
            <tr>
              <td class="feature-name">Voice-to-Text de audios de WhatsApp con resumen</td>
              <td class="feature-val text-dim">—</td>
              <td class="feature-val feature-val--featured">100 audios/mes</td>
              <td class="feature-val feature-val--bold"><q-icon name="sym_r_auto_awesome" color="secondary" size="18px" /> Ilimitado</td>
            </tr>

            <!-- CATEGORÍA: AUDITORÍA & SOPORTE -->
            <tr class="matrix-category-row">
              <td colspan="4">Seguridad, Auditoría & Soporte</td>
            </tr>
            <tr>
              <td class="feature-name">Registro de Auditoría & Trazabilidad</td>
              <td class="feature-val text-dim">Básico</td>
              <td class="feature-val feature-val--featured">30 días</td>
              <td class="feature-val feature-val--bold">Auditoría completa</td>
            </tr>
            <tr>
              <td class="feature-name">Acceso a API REST & Webhooks</td>
              <td class="feature-val text-dim">—</td>
              <td class="feature-val text-dim feature-val--featured">—</td>
              <td class="feature-val"><q-icon name="sym_r_check" color="positive" size="18px" /></td>
            </tr>
            <tr>
              <td class="feature-name">Nivel de Soporte</td>
              <td class="feature-val">Ticket / Chat</td>
              <td class="feature-val feature-val--featured">Prioritario</td>
              <td class="feature-val feature-val--bold">VIP 24/7 + Calibración IA</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- 5. PREGUNTAS FRECUENTES (FAQ ACORDEÓN) -->
    <section id="faq" class="pricing-faq-section">
      <div class="section-header">
        <div class="section-tag">Dudas Comunes</div>
        <h2 class="section-title">Preguntas Frecuentes</h2>
        <p class="section-subtitle">
          Todo lo que necesitas saber antes de contratar tu plan.
        </p>
      </div>

      <div class="faq-accordion">
        <div
          v-for="(item, idx) in faqs"
          :key="idx"
          class="faq-card"
          :class="{ 'faq-card--open': openFaq === idx }"
          @click="openFaq = openFaq === idx ? null : idx"
        >
          <div class="faq-card__header">
            <span class="faq-card__question">{{ item.q }}</span>
            <q-icon
              :name="openFaq === idx ? 'sym_r_expand_less' : 'sym_r_expand_more'"
              size="20px"
              class="faq-icon"
            />
          </div>
          <div v-show="openFaq === idx" class="faq-card__answer">
            <p>{{ item.a }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- 6. BANNER DE CONVERSIÓN FINAL -->
    <section class="pricing-cta-banner">
      <div class="cta-banner-content">
        <h2 class="cta-title">¿Listo para transformar la atención de tu negocio?</h2>
        <p class="cta-subtitle">
          Prueba XpertiFlow CRM V2 hoy mismo. Configura tu número de WhatsApp en menos de 5 minutos.
        </p>
        <div class="cta-actions">
          <button
            type="button"
            class="pricing-btn pricing-btn--primary"
            @click="openContactModal('Consulta General / Demo')"
          >
            <q-icon name="sym_r_rocket_launch" size="18px" />
            <span>Solicitar Demo Gratuita</span>
          </button>
          <router-link to="/" class="pricing-btn pricing-btn--secondary">
            <span>Iniciar Sesión en el CRM</span>
          </router-link>
        </div>
      </div>
    </section>

    <!-- DIÁLOGO / MODAL DE CONTACTO & COTIZACIÓN -->
    <q-dialog v-model="showModal">
      <q-card class="modal-card">
        <q-card-section class="modal-header">
          <div class="row items-center justify-between no-wrap">
            <div class="modal-title-box">
              <span class="modal-tag">Cotización Directa</span>
              <h3 class="modal-title">{{ selectedPlanModal }}</h3>
            </div>
            <q-btn flat round dense icon="close" v-close-popup />
          </div>
        </q-card-section>

        <q-card-section class="q-pt-none">
          <p class="modal-desc">
            Déjanos tu contacto para coordinar la activación de tu plan o escribirte directamente por WhatsApp.
          </p>

          <q-form @submit.prevent="submitLead" class="column q-gutter-y-sm">
            <q-input
              v-model="leadForm.name"
              label="Nombre completo o Empresa"
              outlined
              dense
              required
            />
            <q-input
              v-model="leadForm.phone"
              label="Número de WhatsApp (con código de país)"
              outlined
              dense
              required
            />
            <q-input
              v-model="leadForm.email"
              label="Correo electrónico corporativo"
              outlined
              dense
              type="email"
            />

            <div class="modal-details-box q-pa-sm q-mt-sm">
              <div class="text-caption text-dim">Detalle de tu selección:</div>
              <div class="text-caption text-weight-bold text-ink">
                {{ selectedPlanModal }} • {{ billingCycle === 'annual' ? 'Facturación Anual (-15%)' : 'Facturación Mensual' }}
              </div>
              <div class="text-caption text-primary" v-if="extraPhones > 0 || extraAgents > 0">
                +{{ extraPhones }} líneas extra WhatsApp • +{{ extraAgents }} agentes extra
              </div>
            </div>

            <div class="row items-center justify-end q-gutter-x-sm q-mt-md">
              <q-btn flat label="Cancelar" v-close-popup />
              <q-btn
                color="primary"
                type="submit"
                :loading="submitting"
                label="Enviar por WhatsApp"
                icon="sym_r_send"
              />
            </div>
          </q-form>
        </q-card-section>
      </q-card>
    </q-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'

// Ciclo de facturación: 'monthly' | 'annual'
const billingCycle = ref<'monthly' | 'annual'>('monthly')

// Estado de la calculadora
const calcPlan = ref<'basic' | 'pro' | 'elite'>('pro')
const extraPhones = ref<number>(0)
const extraAgents = ref<number>(0)

// FAQ interactivo
const openFaq = ref<number | null>(0)

const faqs = [
  {
    q: '¿Cómo funciona el cobro de +$20 USD por cada número de WhatsApp adicional?',
    a: 'En cualquiera de nuestros planes puedes añadir tantas líneas de WhatsApp como requiera tu empresa (por ejemplo, para distintas sucursales, países o departamentos). Cada línea extra tiene un costo fijo transparente de solo $20 USD/mes sobre tu plan base.'
  },
  {
    q: '¿Puedo conectar WhatsApp Web QR y WhatsApp Cloud API oficial a la vez?',
    a: 'Sí, totalmente. XpertiFlow V2 cuenta con arquitectura híbrida. Puedes vincular líneas mediante escaneo de código QR (mediante nuestra integración de sesiones seguras) y líneas corporativas certificadas con Meta Graph API (WhatsApp Cloud API) en la misma bandeja.'
  },
  {
    q: '¿Cómo funciona el modelo de Inteligencia Artificial (Hentle-AI) en el Plan Elite?',
    a: 'El Plan Elite incluye nuestro motor de IA con base de datos vectorial (pgvector de 768 dimensiones). Puedes subir tus catálogos, PDFs, políticas y FAQs. El Agente Autónomo responde dudas complejas 24/7 en lenguaje natural, y además sugiere respuestas a tus operadores humanos en tiempo real.'
  },
  {
    q: '¿Puedo cambiar de plan o cancelar en cualquier momento?',
    a: 'Sí. No tenemos contratos forzosos en la modalidad mensual. Puedes actualizar tu plan (upgrade) o modificar el número de líneas y agentes en cualquier momento desde tu panel de administración.'
  },
  {
    q: '¿Qué ocurre con los mensajes de voz de WhatsApp?',
    a: 'En el Plan Pro cuentas con cuota de transcripción, y en el Plan Elite dispones de Voice-to-Text ilimitado con Whisper e IA. Cada audio recibido se convierte automáticamente en texto y genera un resumen ejecutivo para que tu equipo no pierda tiempo escuchando audios largos.'
  }
]

// Modal de contacto / Lead
const showModal = ref(false)
const selectedPlanModal = ref('Plan Pro Growth ($230/mes)')
const submitting = ref(false)
const leadForm = ref({
  name: '',
  phone: '',
  email: ''
})

function openContactModal(planName: string) {
  selectedPlanModal.value = planName
  showModal.value = true
}

function openQuoteModal() {
  selectedPlanModal.value = `Cotización Personalizada (${currentPlanName.value}: ${currentTotalPhones.value} líneas, ${currentTotalAgents.value} agentes)`
  showModal.value = true
}

function submitLead() {
  submitting.value = true
  const msg = `Hola! Quiero contratar o solicitar demo para ${selectedPlanModal.value}. Mi nombre es ${leadForm.value.name}, teléfono ${leadForm.value.phone}. Modalidad: ${billingCycle.value === 'annual' ? 'Anual (-15%)' : 'Mensual'}.`
  const encoded = encodeURIComponent(msg)

  setTimeout(() => {
    submitting.value = false
    showModal.value = false
    // Abrir WhatsApp con mensaje pre-cargado
    window.open(`https://wa.me/?text=${encoded}`, '_blank')
  }, 400)
}

// CÁLCULOS DINÁMICOS
const basePlanPhones = computed(() => {
  if (calcPlan.value === 'basic') return 1
  if (calcPlan.value === 'pro') return 2
  return 4
})

const basePlanAgents = computed(() => {
  if (calcPlan.value === 'basic') return 3
  if (calcPlan.value === 'pro') return 7
  return 15
})

const currentTotalPhones = computed(() => basePlanPhones.value + extraPhones.value)
const currentTotalAgents = computed(() => basePlanAgents.value + extraAgents.value)

const currentPlanName = computed(() => {
  if (calcPlan.value === 'basic') return 'Plan Básico'
  if (calcPlan.value === 'pro') return 'Plan Pro'
  return 'Plan Elite IA'
})

const agentAddonPrice = computed(() => {
  if (calcPlan.value === 'basic') return 18
  if (calcPlan.value === 'pro') return 15
  return 12
})

const currentPlanBaseCost = computed(() => {
  if (billingCycle.value === 'monthly') {
    if (calcPlan.value === 'basic') return 120
    if (calcPlan.value === 'pro') return 230
    return 390
  } else {
    if (calcPlan.value === 'basic') return 102
    if (calcPlan.value === 'pro') return 195
    return 330
  }
})

const extraPhonesCost = computed(() => extraPhones.value * 20)
const extraAgentsCost = computed(() => extraAgents.value * agentAddonPrice.value)

const calculatedMonthlyTotal = computed(() => {
  return currentPlanBaseCost.value + extraPhonesCost.value + extraAgentsCost.value
})

const calculatedAnnualTotal = computed(() => {
  return calculatedMonthlyTotal.value * 12
})

const totalAnnualDiscount = computed(() => {
  const fullMonthly = (calcPlan.value === 'basic' ? 120 : calcPlan.value === 'pro' ? 230 : 390) + extraPhonesCost.value + extraAgentsCost.value
  return (fullMonthly * 12) - calculatedAnnualTotal.value
})
</script>

<style scoped lang="scss">
.pricing-page {
  position: relative;
  max-width: 1240px;
  margin: 0 auto;
  padding: 40px 24px 80px;
  overflow: hidden;
}

// Luces ambientales de fondo
.pricing-glow-orb {
  position: absolute;
  border-radius: 50%;
  pointer-events: none;
  filter: blur(140px);
  z-index: 0;
  opacity: 0.12;
  transition: all 1s ease;
}

.pricing-glow-orb--1 {
  width: 500px;
  height: 500px;
  background: #10b981;
  top: 5%;
  left: 50%;
  transform: translateX(-50%);
}

.pricing-glow-orb--2 {
  width: 450px;
  height: 450px;
  background: #6366f1;
  top: 35%;
  left: 80%;
}

.pricing-glow-orb--3 {
  width: 400px;
  height: 400px;
  background: #10b981;
  top: 65%;
  left: -10%;
}

// 1. HERO SECTION
.pricing-hero {
  position: relative;
  z-index: 1;
  text-align: center;
  max-width: 860px;
  margin: 20px auto 50px;
}

.pricing-badge {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 6px 16px;
  border-radius: var(--crm-radius-pill);
  background: rgba(16, 185, 129, 0.08);
  border: 1px solid rgba(16, 185, 129, 0.25);
  color: var(--crm-color-primary);
  font-size: 0.82rem;
  font-weight: 600;
  margin-bottom: 24px;
}

.pricing-badge__dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #10b981;
  box-shadow: 0 0 10px #10b981;
  animation: pulse-dot 2s infinite;
}

@keyframes pulse-dot {
  0% { transform: scale(0.95); opacity: 0.8; }
  50% { transform: scale(1.25); opacity: 1; }
  100% { transform: scale(0.95); opacity: 0.8; }
}

.pricing-title {
  font-size: 2.8rem;
  font-weight: 800;
  line-height: 1.18;
  letter-spacing: -0.03em;
  color: var(--crm-color-ink);
  margin-bottom: 18px;

  @media (max-width: 768px) {
    font-size: 2rem;
  }
}

.text-gradient {
  background: linear-gradient(135deg, #10b981 0%, #34d399 50%, #6366f1 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
}

.pricing-subtitle {
  font-size: 1.1rem;
  line-height: 1.6;
  color: var(--crm-color-muted);
  max-width: 680px;
  margin: 0 auto 36px;

  @media (max-width: 768px) {
    font-size: 0.95rem;
  }
}

// TOGGLE MENSUAL / ANUAL
.billing-toggle-container {
  display: flex;
  justify-content: center;
}

.billing-toggle {
  display: inline-flex;
  padding: 4px;
  border-radius: var(--crm-radius-pill);
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid var(--crm-color-border);
  backdrop-filter: blur(8px);
}

.billing-toggle__btn {
  padding: 9px 22px;
  border-radius: var(--crm-radius-pill);
  border: none;
  background: transparent;
  color: var(--crm-color-muted);
  font-size: 0.88rem;
  font-weight: 600;
  cursor: pointer;
  transition: all var(--crm-transition);
  display: inline-flex;
  align-items: center;
  gap: 8px;

  &:hover {
    color: var(--crm-color-ink);
  }

  &--active {
    background: var(--crm-color-primary);
    color: #ffffff !important;
    box-shadow: 0 2px 10px rgba(16, 185, 129, 0.35);
  }
}

.billing-discount-badge {
  font-size: 11px;
  padding: 2px 7px;
  border-radius: var(--crm-radius-pill);
  background: rgba(255, 255, 255, 0.22);
  color: #ffffff;
  font-weight: 700;
}

// 2. GRID DE TARJETAS
.pricing-cards-section {
  position: relative;
  z-index: 1;
  margin-bottom: 70px;
}

.pricing-cards-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 28px;
  align-items: stretch;

  @media (max-width: 980px) {
    grid-template-columns: 1fr;
    max-width: 480px;
    margin: 0 auto;
  }
}

// TARJETA INDIVIDUAL
.pricing-card {
  background: var(--crm-bg-card);
  border: 1px solid var(--crm-color-border);
  border-radius: 20px;
  padding: 32px 28px;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  box-shadow: var(--crm-shadow-card);
  transition: all var(--crm-transition-smooth);
  position: relative;

  &:hover {
    transform: translateY(-6px);
    border-color: var(--crm-color-border-hover);
    box-shadow: 0 16px 36px -10px rgba(0, 0, 0, 0.45);
  }

  // PLAN DESTACADO (PRO)
  &--featured {
    border: 2px solid var(--crm-color-primary);
    background: linear-gradient(180deg, rgba(16, 185, 129, 0.05) 0%, var(--crm-bg-card) 40%);
    box-shadow: 0 8px 30px rgba(16, 185, 129, 0.15);

    &:hover {
      box-shadow: 0 18px 45px rgba(16, 185, 129, 0.28);
    }
  }

  // PLAN ELITE
  &--elite {
    border: 1px solid rgba(99, 102, 241, 0.35);
    background: linear-gradient(180deg, rgba(99, 102, 241, 0.06) 0%, var(--crm-bg-card) 45%);

    &:hover {
      border-color: rgba(99, 102, 241, 0.6);
      box-shadow: 0 16px 40px rgba(99, 102, 241, 0.22);
    }
  }
}

.featured-ribbon {
  position: absolute;
  top: -14px;
  left: 50%;
  transform: translateX(-50%);
  background: var(--crm-color-primary);
  color: #ffffff;
  font-size: 11px;
  font-weight: 800;
  letter-spacing: 0.08em;
  padding: 4px 14px;
  border-radius: var(--crm-radius-pill);
  display: inline-flex;
  align-items: center;
  gap: 5px;
  box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
}

.pricing-card__badge-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 12px;
}

.tier-pill {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  padding: 3px 10px;
  border-radius: var(--crm-radius-pill);

  &--basic {
    background: rgba(148, 163, 184, 0.12);
    color: var(--crm-color-muted);
  }
  &--pro {
    background: var(--crm-color-primary-soft);
    color: var(--crm-color-primary);
  }
  &--elite {
    background: rgba(99, 102, 241, 0.15);
    color: #818cf8;
  }
}

.tier-caption {
  font-size: 11px;
  font-weight: 600;
  color: var(--crm-color-dim);
}

.pricing-card__title {
  font-size: 1.45rem;
  font-weight: 700;
  color: var(--crm-color-ink);
  margin: 0 0 8px;
  letter-spacing: -0.02em;
}

.pricing-card__desc {
  font-size: 0.88rem;
  line-height: 1.5;
  color: var(--crm-color-muted);
  margin-bottom: 20px;
  min-height: 40px;
}

.pricing-price-box {
  margin-bottom: 20px;
}

.pricing-price {
  display: flex;
  align-items: baseline;
  gap: 4px;
}

.pricing-currency {
  font-size: 1.6rem;
  font-weight: 700;
  color: var(--crm-color-ink);
}

.pricing-amount {
  font-size: 3.1rem;
  font-weight: 800;
  letter-spacing: -0.03em;
  color: var(--crm-color-ink);
}

.pricing-period {
  font-size: 0.95rem;
  color: var(--crm-color-dim);
  font-weight: 500;
  margin-left: 4px;
}

.pricing-price-sub {
  font-size: 0.8rem;
  color: var(--crm-color-dim);
  margin-top: 4px;
}

// SPECS HIGHLIGHT BOX
.pricing-specs-highlight {
  background: rgba(255, 255, 255, 0.03);
  border: 1px solid var(--crm-color-border);
  border-radius: var(--crm-radius-card);
  padding: 14px 16px;
  margin-bottom: 20px;
  display: flex;
  flex-direction: column;
  gap: 8px;

  &--featured {
    background: rgba(16, 185, 129, 0.06);
    border-color: rgba(16, 185, 129, 0.2);
  }

  &--elite {
    background: rgba(99, 102, 241, 0.06);
    border-color: rgba(99, 102, 241, 0.2);
  }
}

.spec-item {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 0.86rem;
  color: var(--crm-color-ink);

  &--addon {
    padding-top: 6px;
    border-top: 1px dashed var(--crm-color-border);
    color: var(--crm-color-accent);
    font-size: 0.82rem;
  }
}

.pricing-card__divider {
  height: 1px;
  background: var(--crm-color-border);
  margin: 10px 0 20px;
}

// FEATURES LIST
.pricing-features {
  flex-grow: 1;
  margin-bottom: 28px;
}

.pricing-features__title {
  font-size: 0.8rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--crm-color-dim);
  margin-bottom: 14px;
}

.features-list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 12px;

  li {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    font-size: 0.87rem;
    line-height: 1.45;
    color: var(--crm-color-ink);
  }
}

.check-icon {
  color: var(--crm-color-primary);
  flex-shrink: 0;
  margin-top: 2px;

  &--ai {
    color: #818cf8;
  }
}

// BOTONES
.pricing-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 13px 20px;
  border-radius: var(--crm-radius-control);
  font-size: 0.92rem;
  font-weight: 700;
  cursor: pointer;
  border: none;
  transition: all var(--crm-transition);
  text-decoration: none;

  &--primary {
    background: var(--crm-color-primary);
    color: #ffffff;
    box-shadow: 0 4px 14px rgba(16, 185, 129, 0.35);

    &:hover {
      background: var(--crm-color-primary-hover);
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(16, 185, 129, 0.45);
    }
  }

  &--secondary {
    background: rgba(255, 255, 255, 0.05);
    color: var(--crm-color-ink);
    border: 1px solid var(--crm-color-border);

    &:hover {
      background: rgba(255, 255, 255, 0.1);
      border-color: var(--crm-color-border-hover);
      transform: translateY(-2px);
    }
  }

  &--elite {
    background: linear-gradient(135deg, #4f46e5 0%, #10b981 100%);
    color: #ffffff;
    box-shadow: 0 4px 16px rgba(79, 70, 229, 0.35);

    &:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 22px rgba(79, 70, 229, 0.5);
    }
  }
}

// 3. CALCULADORA INTERACTIVA
.pricing-calc-section {
  position: relative;
  z-index: 1;
  margin-bottom: 80px;
}

.section-header {
  text-align: center;
  max-width: 680px;
  margin: 0 auto 36px;
}

.section-tag {
  display: inline-block;
  font-size: 0.8rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: var(--crm-color-primary);
  margin-bottom: 8px;
}

.section-title {
  font-size: 2.1rem;
  font-weight: 800;
  color: var(--crm-color-ink);
  letter-spacing: -0.02em;
  margin: 0 0 10px;
}

.section-subtitle {
  font-size: 0.95rem;
  color: var(--crm-color-muted);
  line-height: 1.5;
}

.calculator-box {
  display: grid;
  grid-template-columns: 1.35fr 1fr;
  gap: 32px;
  background: var(--crm-bg-card);
  border: 1px solid var(--crm-color-border);
  border-radius: 20px;
  padding: 36px;
  box-shadow: var(--crm-shadow-card);

  @media (max-width: 860px) {
    grid-template-columns: 1fr;
    padding: 24px;
  }
}

.calc-group {
  margin-bottom: 24px;
}

.calc-label {
  font-size: 0.9rem;
  font-weight: 600;
  color: var(--crm-color-ink);
  display: block;
  margin-bottom: 12px;
}

.calc-label-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 12px;
}

.calc-counter-val {
  font-size: 0.85rem;
  font-weight: 700;
  color: var(--crm-color-primary);
}

.calc-plan-pills {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 12px;

  @media (max-width: 600px) {
    grid-template-columns: 1fr;
  }
}

.calc-plan-pill {
  background: rgba(255, 255, 255, 0.03);
  border: 1px solid var(--crm-color-border);
  border-radius: var(--crm-radius-card);
  padding: 14px 10px;
  text-align: center;
  cursor: pointer;
  transition: all var(--crm-transition);

  &:hover {
    border-color: var(--crm-color-border-hover);
  }

  &--active {
    border-color: var(--crm-color-primary);
    background: var(--crm-color-primary-soft);
  }
}

.calc-plan-name {
  font-size: 0.85rem;
  font-weight: 700;
  color: var(--crm-color-ink);
}

.calc-plan-price {
  font-size: 1.05rem;
  font-weight: 800;
  color: var(--crm-color-primary);
  margin: 4px 0;
}

.calc-plan-meta {
  font-size: 0.72rem;
  color: var(--crm-color-dim);
}

// STEPPERS
.calc-stepper-row {
  display: flex;
  align-items: center;
  gap: 14px;
  background: rgba(255, 255, 255, 0.02);
  border: 1px solid var(--crm-color-border);
  border-radius: var(--crm-radius-card);
  padding: 10px 16px;
}

.stepper-btn {
  width: 36px;
  height: 36px;
  border-radius: var(--crm-radius-control);
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid var(--crm-color-border);
  color: var(--crm-color-ink);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all var(--crm-transition);

  &:hover:not(:disabled) {
    background: var(--crm-color-primary);
    color: #ffffff;
  }

  &:disabled {
    opacity: 0.35;
    cursor: not-allowed;
  }
}

.stepper-display {
  flex-grow: 1;
  text-align: center;
}

.stepper-number {
  font-size: 1.35rem;
  font-weight: 800;
  color: var(--crm-color-ink);
  display: block;
}

.stepper-caption {
  font-size: 0.78rem;
  color: var(--crm-color-dim);
}

// RESUMEN CALCULADORA
.calculator-summary {
  background: rgba(255, 255, 255, 0.02);
  border: 1px solid var(--crm-color-border);
  border-radius: var(--crm-radius-card);
  padding: 24px;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
}

.summary-tag {
  font-size: 0.72rem;
  font-weight: 700;
  text-transform: uppercase;
  color: var(--crm-color-primary);
}

.summary-title {
  font-size: 1.2rem;
  font-weight: 700;
  color: var(--crm-color-ink);
  margin: 4px 0 16px;
}

.summary-items {
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin-bottom: 20px;
}

.summary-line {
  display: flex;
  justify-content: space-between;
  font-size: 0.86rem;
  color: var(--crm-color-muted);
}

.summary-amount {
  font-weight: 700;
  color: var(--crm-color-ink);
}

.summary-total-box {
  background: rgba(16, 185, 129, 0.08);
  border: 1px solid rgba(16, 185, 129, 0.25);
  border-radius: var(--crm-radius-control);
  padding: 16px;
  text-align: center;
  margin-bottom: 12px;
}

.summary-total-label {
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--crm-color-dim);
}

.summary-total-price {
  display: flex;
  align-items: baseline;
  justify-content: center;
  gap: 4px;
  margin: 4px 0;

  .currency { font-size: 1.3rem; font-weight: 700; color: var(--crm-color-primary); }
  .val { font-size: 2.2rem; font-weight: 800; color: var(--crm-color-primary); }
  .period { font-size: 0.85rem; color: var(--crm-color-dim); }
}

.summary-total-sub {
  font-size: 0.75rem;
  color: var(--crm-color-muted);
}

// 4. TABLA COMPARATIVA
.pricing-matrix-section {
  position: relative;
  z-index: 1;
  margin-bottom: 80px;
}

.matrix-table-wrapper {
  overflow-x: auto;
  border: 1px solid var(--crm-color-border);
  border-radius: 16px;
  background: var(--crm-bg-card);
}

.matrix-table {
  width: 100%;
  border-collapse: collapse;
  text-align: left;
  font-size: 0.88rem;

  th, td {
    padding: 14px 20px;
    border-bottom: 1px solid var(--crm-color-border);
  }

  th {
    background: rgba(255, 255, 255, 0.03);
    font-weight: 700;
    color: var(--crm-color-ink);
  }

  .matrix-th-feature {
    width: 40%;
  }

  .matrix-th-tier {
    width: 20%;
    text-align: center;

    &--featured {
      color: var(--crm-color-primary);
      background: var(--crm-color-primary-soft);
    }
  }

  .matrix-category-row td {
    background: rgba(255, 255, 255, 0.02);
    font-weight: 700;
    font-size: 0.82rem;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--crm-color-dim);
    padding-top: 20px;
    padding-bottom: 10px;
  }

  .feature-name {
    color: var(--crm-color-ink);
  }

  .feature-val {
    text-align: center;
    color: var(--crm-color-muted);

    &--featured {
      font-weight: 600;
      color: var(--crm-color-ink);
      background: rgba(16, 185, 129, 0.03);
    }

    &--bold {
      font-weight: 700;
      color: var(--crm-color-ink);
    }
  }
}

// 5. FAQ
.pricing-faq-section {
  position: relative;
  z-index: 1;
  margin-bottom: 80px;
}

.faq-accordion {
  max-width: 800px;
  margin: 0 auto;
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.faq-card {
  background: var(--crm-bg-card);
  border: 1px solid var(--crm-color-border);
  border-radius: var(--crm-radius-card);
  padding: 18px 24px;
  cursor: pointer;
  transition: all var(--crm-transition);

  &:hover {
    border-color: var(--crm-color-border-hover);
  }

  &--open {
    border-color: var(--crm-color-primary);
  }
}

.faq-card__header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
}

.faq-card__question {
  font-size: 1rem;
  font-weight: 600;
  color: var(--crm-color-ink);
}

.faq-icon {
  color: var(--crm-color-primary);
  flex-shrink: 0;
}

.faq-card__answer {
  margin-top: 14px;
  padding-top: 14px;
  border-top: 1px solid var(--crm-color-border);
  font-size: 0.9rem;
  line-height: 1.6;
  color: var(--crm-color-muted);

  p {
    margin: 0;
  }
}

// 6. BANNER FINAL
.pricing-cta-banner {
  position: relative;
  z-index: 1;
  background: linear-gradient(135deg, rgba(16, 185, 129, 0.12) 0%, rgba(99, 102, 241, 0.12) 100%);
  border: 1px solid rgba(16, 185, 129, 0.3);
  border-radius: 24px;
  padding: 60px 32px;
  text-align: center;
  box-shadow: 0 16px 40px rgba(0, 0, 0, 0.3);
}

.cta-banner-content {
  max-width: 650px;
  margin: 0 auto;
}

.cta-title {
  font-size: 2.2rem;
  font-weight: 800;
  letter-spacing: -0.02em;
  color: var(--crm-color-ink);
  margin: 0 0 14px;
}

.cta-subtitle {
  font-size: 1.05rem;
  color: var(--crm-color-muted);
  line-height: 1.6;
  margin: 0 0 28px;
}

.cta-actions {
  display: flex;
  justify-content: center;
  gap: 16px;
  flex-wrap: wrap;
}

// MODAL
.modal-card {
  background: var(--crm-bg-card) !important;
  border: 1px solid var(--crm-color-border);
  border-radius: 16px;
  width: 100%;
  max-width: 480px;
}

.modal-header {
  border-bottom: 1px solid var(--crm-color-border);
  padding-bottom: 12px;
}

.modal-tag {
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  color: var(--crm-color-primary);
}

.modal-title {
  font-size: 1.15rem;
  font-weight: 700;
  color: var(--crm-color-ink);
  margin: 2px 0 0;
}

.modal-desc {
  font-size: 0.85rem;
  color: var(--crm-color-muted);
  margin: 14px 0 12px;
}

.modal-details-box {
  background: rgba(255, 255, 255, 0.03);
  border: 1px solid var(--crm-color-border);
  border-radius: var(--crm-radius-control);
}
</style>
