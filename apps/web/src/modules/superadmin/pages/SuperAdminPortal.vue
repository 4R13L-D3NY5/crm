<template>
  <div class="superadmin-portal q-pa-md">
    <!-- Header Principal -->
    <div class="row items-center justify-between q-mb-lg">
      <div>
        <div class="row items-center q-gutter-x-sm">
          <h1 class="text-h5 text-weight-bolder text-ink q-my-none">Centro de Control SuperAdmin SaaS</h1>
          <span class="saas-badge-pill">Master Control</span>
        </div>
        <p class="text-caption text-muted q-mt-xs q-mb-none">
          Gestión centralizada de licencias, facturación MRR, prorrateo automático y cobranza asistida con <strong>Hentle-AI</strong>.
        </p>
      </div>

      <div class="row items-center q-gutter-x-sm">
        <q-btn
          flat
          dense
          no-caps
          icon="sym_r_refresh"
          label="Actualizar Datos"
          class="xf-btn-glass"
          :loading="loading"
          @click="loadData"
        />
      </div>
    </div>

    <!-- TARJETAS DE KPIS FINANCIEROS Y SAAS -->
    <div class="row q-col-gutter-md q-mb-lg">
      <!-- 1. MRR -->
      <div class="col-12 col-sm-6 col-md-3">
        <q-card flat class="kpi-card kpi-card--emerald">
          <div class="row items-center justify-between q-mb-xs">
            <span class="kpi-label">MRR Consolidado</span>
            <div class="kpi-icon-box text-positive">
              <q-icon name="sym_r_payments" size="20px" />
            </div>
          </div>
          <div class="kpi-value text-positive">
            ${{ summary.total_mrr_usd.toLocaleString('en-US', { minimumFractionDigits: 2 }) }}
            <span class="text-caption text-dim">USD/mes</span>
          </div>
          <div class="kpi-subtext text-muted">
            ARR Proyectado: ${{ summary.total_arr_usd.toLocaleString('en-US', { minimumFractionDigits: 2 }) }} USD
          </div>
        </q-card>
      </div>

      <!-- 2. Clientes / Empresas -->
      <div class="col-12 col-sm-6 col-md-3">
        <q-card flat class="kpi-card">
          <div class="row items-center justify-between q-mb-xs">
            <span class="kpi-label">Empresas Inquilinas</span>
            <div class="kpi-icon-box text-primary">
              <q-icon name="sym_r_domain" size="20px" />
            </div>
          </div>
          <div class="kpi-value text-ink">
            {{ summary.total_tenants }}
            <span class="text-caption text-dim">Empresas</span>
          </div>
          <div class="row items-center q-gutter-x-sm kpi-subtext">
            <span class="text-positive">● {{ summary.active_count }} Activas</span>
            <span class="text-warning">● {{ summary.warning_count }} Por vencer</span>
            <span class="text-negative">● {{ summary.expired_count + summary.suspended_count }} Vencidas</span>
          </div>
        </q-card>
      </div>

      <!-- 3. Líneas WhatsApp Arrendadas -->
      <div class="col-12 col-sm-6 col-md-3">
        <q-card flat class="kpi-card">
          <div class="row items-center justify-between q-mb-xs">
            <span class="kpi-label">Líneas WhatsApp Totales</span>
            <div class="kpi-icon-box text-secondary">
              <q-icon name="sym_r_hub" size="20px" />
            </div>
          </div>
          <div class="kpi-value text-ink">
            {{ summary.total_phones_leased }}
            <span class="text-caption text-dim">Líneas activas</span>
          </div>
          <div class="kpi-subtext text-muted">
            Canales oficiales Cloud API y QR
          </div>
        </q-card>
      </div>

      <!-- 4. Asistente Hentle-AI -->
      <div class="col-12 col-sm-6 col-md-3">
        <q-card flat class="kpi-card kpi-card--ai">
          <div class="row items-center justify-between q-mb-xs">
            <span class="kpi-label">Copiloto Financiero IA</span>
            <div class="kpi-icon-box text-indigo-4">
              <q-icon name="sym_r_auto_awesome" size="20px" />
            </div>
          </div>
          <div class="kpi-value text-indigo-4" style="font-size: 1.3rem;">
            Hentle-AI Activo
          </div>
          <div class="kpi-subtext text-muted">
            Cobranza & análisis de retención en vivo
          </div>
        </q-card>
      </div>
    </div>

    <!-- TABS DE NAVEGACIÓN -->
    <q-tabs
      v-model="activeTab"
      dense
      align="left"
      active-color="primary"
      indicator-color="primary"
      class="superadmin-tabs q-mb-md"
      no-caps
    >
      <q-tab name="tenants" icon="sym_r_list" label="Matriz de Empresas & Licencias" />
      <q-tab name="copilot" icon="sym_r_auto_awesome" label="Cobrador Inteligente Hentle-AI" />
      <q-tab name="proration" icon="sym_r_calculate" label="Simulador de Prorrateo" />
    </q-tabs>

    <!-- TAB 1: MATRIZ DE EMPRESAS & LICENCIAS -->
    <div v-show="activeTab === 'tenants'">
      <!-- Filtros Rápidos -->
      <div class="row items-center justify-between q-mb-md">
        <div class="row items-center q-gutter-x-sm">
          <q-btn-toggle
            v-model="filterStatus"
            toggle-color="primary"
            flat
            dense
            no-caps
            :options="[
              { label: 'Todas (' + tenants.length + ')', value: 'all' },
              { label: 'Al día', value: 'active' },
              { label: 'Por vencer', value: 'warning' },
              { label: 'Vencidas / Suspendidas', value: 'expired' },
            ]"
            class="filter-toggle"
          />
        </div>

        <q-input
          v-model="searchQuery"
          placeholder="Buscar por empresa o contacto..."
          outlined
          dense
          class="search-input"
        >
          <template #prepend>
            <q-icon name="sym_r_search" size="18px" class="text-dim" />
          </template>
        </q-input>
      </div>

      <!-- Tabla de Tenants -->
      <q-card flat class="table-card">
        <q-table
          :rows="filteredTenants"
          :columns="columns"
          row-key="id"
          flat
          dense
          :pagination="{ rowsPerPage: 10 }"
          class="superadmin-table"
        >
          <!-- Celda: Empresa y Contacto -->
          <template #body-cell-name="props">
            <q-td :props="props">
              <div class="column">
                <span class="text-weight-bold text-ink" style="font-size: 0.95rem;">{{ props.row.name }}</span>
                <span class="text-caption text-dim">
                  {{ props.row.contact_name }} • {{ props.row.contact_phone || 'Sin WhatsApp' }}
                </span>
              </div>
            </q-td>
          </template>

          <!-- Celda: Plan & Ciclo -->
          <template #body-cell-plan="props">
            <q-td :props="props">
              <div class="row items-center q-gutter-x-xs">
                <span :class="['plan-pill', `plan-pill--${props.row.plan}`]">
                  {{ props.row.plan_name }}
                </span>
                <span class="text-caption text-dim" v-if="props.row.billing_cycle === 'annual'">
                  (Anual -15%)
                </span>
              </div>
            </q-td>
          </template>

          <!-- Celda: MRR -->
          <template #body-cell-monthly_mrr_usd="props">
            <q-td :props="props">
              <span class="text-weight-bolder text-positive">
                ${{ props.row.monthly_mrr_usd.toFixed(2) }} USD
              </span>
            </q-td>
          </template>

          <!-- Celda: Líneas & Agentes -->
          <template #body-cell-resources="props">
            <q-td :props="props">
              <div class="text-caption text-ink">
                📱 <strong>{{ props.row.phones_total }}</strong> WhatsApp
                <span class="text-dim" v-if="props.row.extra_phones > 0">(+{{ props.row.extra_phones }} extra)</span>
              </div>
              <div class="text-caption text-ink">
                🌐 <strong>{{ props.row.social_total || 8 }}</strong> Redes
                <span class="text-dim" v-if="props.row.extra_social > 0">(+{{ props.row.extra_social }} extra)</span>
              </div>
              <div class="text-caption text-dim">
                👥 <strong>{{ props.row.users_count }}</strong> / {{ props.row.agents_total }} agentes
              </div>
            </q-td>
          </template>

          <!-- Celda: Vigencia y Semáforo -->
          <template #body-cell-validity="props">
            <q-td :props="props">
              <div class="column">
                <div class="row items-center q-gutter-x-xs">
                  <span :class="['status-dot', `status-dot--${props.row.health_status}`]" />
                  <span class="text-caption text-weight-bold" :class="getHealthTextColor(props.row.health_status)">
                    {{ getHealthLabel(props.row) }}
                  </span>
                </div>
                <span class="text-caption text-dim" style="font-size: 11px;">
                  Vence: {{ props.row.expires_at }}
                </span>
              </div>
            </q-td>
          </template>

          <!-- Celda: Acciones de Gestión -->
          <template #body-cell-actions="props">
            <q-td :props="props" class="text-right">
              <div class="row items-center justify-end q-gutter-xs no-wrap">
                <!-- Renovar -->
                <q-btn
                  flat
                  dense
                  round
                  size="sm"
                  color="positive"
                  icon="sym_r_autorenew"
                  @click="openRenewModal(props.row)"
                >
                  <q-tooltip>Renovar Suscripción (+1 mes / +1 año)</q-tooltip>
                </q-btn>

                <!-- Cambiar Plan & Prorrateo -->
                <q-btn
                  flat
                  dense
                  round
                  size="sm"
                  color="primary"
                  icon="sym_r_published_with_changes"
                  @click="openPlanModal(props.row)"
                >
                  <q-tooltip>Cambiar Plan & Calcular Prorrateo</q-tooltip>
                </q-btn>

                <!-- Ajustar Cuotas (+/- Canales y Usuarios) -->
                <q-btn
                  flat
                  dense
                  round
                  size="sm"
                  color="teal"
                  icon="sym_r_tune"
                  @click="openQuotaModal(props.row)"
                >
                  <q-tooltip>Ajustar Cuotas (+/- Canales y Operadores)</q-tooltip>
                </q-btn>

                <!-- Extender / Configurar Periodo de Prueba -->
                <q-btn
                  flat
                  dense
                  round
                  size="sm"
                  color="amber-9"
                  icon="sym_r_hourglass_top"
                  @click="openTrialModal(props.row)"
                >
                  <q-tooltip>Ampliar o Reconfigurar Período de Prueba</q-tooltip>
                </q-btn>

                <!-- Token de Licencia -->
                <q-btn
                  flat
                  dense
                  round
                  size="sm"
                  color="warning"
                  icon="sym_r_vpn_key"
                  @click="openTokenModal(props.row)"
                >
                  <q-tooltip>Ver / Regenerar Token de Licencia</q-tooltip>
                </q-btn>

                <!-- Recordatorio Hentle-AI -->
                <q-btn
                  flat
                  dense
                  round
                  size="sm"
                  color="secondary"
                  icon="sym_r_auto_awesome"
                  @click="openAiModal(props.row)"
                >
                  <q-tooltip>Redactar Recordatorio con Hentle-AI</q-tooltip>
                </q-btn>

                <!-- Suspender / Reactivar -->
                <q-btn
                  flat
                  dense
                  round
                  size="sm"
                  :color="props.row.status === 'suspended' ? 'positive' : 'negative'"
                  :icon="props.row.status === 'suspended' ? 'sym_r_play_arrow' : 'sym_r_pause'"
                  @click="toggleStatus(props.row)"
                >
                  <q-tooltip>{{ props.row.status === 'suspended' ? 'Reactivar Servicio' : 'Pausar Servicio' }}</q-tooltip>
                </q-btn>
              </div>
            </q-td>
          </template>
        </q-table>
      </q-card>
    </div>

    <!-- TAB 2: COPILOTO FINANCIERO HENTLE-AI -->
    <div v-show="activeTab === 'copilot'">
      <div class="row q-col-gutter-lg">
        <!-- Columna Izquierda: Parámetros del Asistente -->
        <div class="col-12 col-md-5">
          <q-card flat class="q-pa-md saas-card">
            <div class="text-subtitle1 text-weight-bold text-ink q-mb-xs">
              🤖 Generador de Cobranza & Mensajes Persuasivos
            </div>
            <p class="text-caption text-muted q-mb-md">
              Hentle-AI redacta mensajes adaptados para WhatsApp con el saldo exacto, fecha de corte y argumentos de ahorro anual.
            </p>

            <div class="column q-gutter-y-sm">
              <q-select
                v-model="selectedTenantAi"
                :options="tenantOptions"
                label="Seleccionar Empresa Cliente"
                outlined
                dense
                emit-value
                map-options
              />

              <div>
                <label class="text-caption text-weight-bold text-ink block q-mb-xs">Tono de la Comunicación:</label>
                <q-btn-toggle
                  v-model="aiTone"
                  toggle-color="primary"
                  flat
                  dense
                  no-caps
                  :options="[
                    { label: 'Amable (Preventivo)', value: 'friendly' },
                    { label: 'Formal (Ejecutivo)', value: 'formal' },
                    { label: 'Urgente (Último aviso)', value: 'urgent' },
                  ]"
                  class="filter-toggle"
                />
              </div>

              <q-btn
                color="secondary"
                label="Generar Mensaje con Hentle-AI"
                icon="sym_r_auto_awesome"
                unelevated
                no-caps
                :loading="generatingAi"
                class="q-mt-sm"
                @click="triggerAiReminder"
              />
            </div>
          </q-card>
        </div>

        <!-- Columna Derecha: Vista Previa y Botón de WhatsApp -->
        <div class="col-12 col-md-7">
          <q-card flat class="q-pa-md saas-card">
            <div class="row items-center justify-between q-mb-sm">
              <span class="text-subtitle1 text-weight-bold text-ink">Mensaje Generado para WhatsApp</span>
              <q-btn
                v-if="aiMessageText"
                flat
                dense
                size="sm"
                icon="sym_r_content_copy"
                label="Copiar"
                @click="copyText(aiMessageText)"
              />
            </div>

            <div v-if="aiMessageText" class="ai-preview-box q-pa-md q-mb-md">
              <pre class="ai-preview-text">{{ aiMessageText }}</pre>
            </div>
            <div v-else class="text-center text-dim q-pa-xl">
              <q-icon name="sym_r_chat" size="48px" class="q-mb-sm text-dim" />
              <div>Selecciona una empresa y presiona "Generar Mensaje con Hentle-AI"</div>
            </div>

            <div v-if="aiMessageText" class="row justify-end q-gutter-x-sm">
              <q-btn
                color="positive"
                label="Abrir WhatsApp y Enviar"
                icon="sym_r_send"
                unelevated
                no-caps
                @click="openWhatsappUrl(aiWhatsappUrl)"
              />
            </div>
          </q-card>
        </div>
      </div>
    </div>

    <!-- TAB 3: SIMULADOR DE PRORRATEO LIBRE -->
    <div v-show="activeTab === 'proration'">
      <q-card flat class="q-pa-lg saas-card" style="max-width: 800px; margin: 0 auto;">
        <div class="text-subtitle1 text-weight-bold text-ink q-mb-xs">
          📐 Calculadora de Ajuste & Prorrateo Matemático
        </div>
        <p class="text-caption text-muted q-mb-lg">
          Calcula con exactitud cuánto cobrar hoy a un cliente que solicita un upgrade o cambio de plan a mitad de ciclo sin perder dinero.
        </p>

        <div class="row q-col-gutter-md q-mb-md">
          <div class="col-12 col-sm-6">
            <q-select
              v-model="simTenantId"
              :options="tenantOptions"
              label="Empresa a simular"
              outlined
              dense
              emit-value
              map-options
              @update:model-value="runProrationSim"
            />
          </div>
          <div class="col-12 col-sm-6">
            <q-select
              v-model="simNewPlan"
              :options="[
                { label: 'Plan Básico ($120/mes)', value: 'basic' },
                { label: 'Plan Pro ($230/mes)', value: 'pro' },
                { label: 'Plan Elite IA ($390/mes)', value: 'elite' },
              ]"
              label="Nuevo Plan deseado"
              outlined
              dense
              emit-value
              map-options
              @update:model-value="runProrationSim"
            />
          </div>
        </div>

        <div class="row q-col-gutter-md q-mb-md">
          <div class="col-6">
            <q-input
              v-model.number="simExtraPhones"
              type="number"
              label="Líneas WhatsApp extra (+$20 c/u)"
              outlined
              dense
              @update:model-value="runProrationSim"
            />
          </div>
          <div class="col-6">
            <q-input
              v-model.number="simExtraAgents"
              type="number"
              label="Agentes extra"
              outlined
              dense
              @update:model-value="runProrationSim"
            />
          </div>
        </div>

        <!-- Desglose de Prorrateo -->
        <div v-if="prorationResult" class="proration-breakdown q-pa-md q-mt-md">
          <div class="row justify-between q-mb-xs text-caption text-muted">
            <span>Días ya consumidos:</span>
            <span class="text-ink text-weight-bold">{{ prorationResult.days_used }} días</span>
          </div>
          <div class="row justify-between q-mb-xs text-caption text-muted">
            <span>Días restantes de servicio en el ciclo:</span>
            <span class="text-ink text-weight-bold">{{ prorationResult.days_remaining }} días</span>
          </div>
          <div class="row justify-between q-mb-xs text-caption text-muted">
            <span>Crédito a favor del cliente (Plan anterior no usado):</span>
            <span class="text-positive text-weight-bold">-${{ prorationResult.unused_credit_usd.toFixed(2) }} USD</span>
          </div>
          <div class="row justify-between q-mb-xs text-caption text-muted">
            <span>Costo proporcional del nuevo plan (por días restantes):</span>
            <span class="text-ink text-weight-bold">${{ prorationResult.new_plan_cost_remaining_days_usd.toFixed(2) }} USD</span>
          </div>
          <q-separator class="q-my-sm" />
          <div class="row justify-between items-center text-subtitle1">
            <span class="text-weight-bold text-ink">Total a cobrar hoy por el cambio:</span>
            <span class="text-weight-bolder text-positive" style="font-size: 1.4rem;">
              ${{ prorationResult.prorated_charge_due_today_usd.toFixed(2) }} USD
            </span>
          </div>
        </div>
      </q-card>
    </div>

    <!-- MODAL 1: RENOVAR SUSCRIPCIÓN -->
    <q-dialog v-model="renewModalOpen">
      <q-card style="width: 460px; max-width: 95vw;" class="saas-modal-card q-pa-md">
        <q-card-section>
          <div class="text-h6 text-weight-bold text-ink">Renovar Suscripción</div>
          <div class="text-caption text-muted">{{ activeTenant?.name }} • Plan {{ activeTenant?.plan_name }}</div>
        </q-card-section>

        <q-card-section class="q-gutter-y-md">
          <div>
            <label class="text-caption text-weight-bold text-ink block q-mb-xs">Periodo a extender:</label>
            <q-select
              v-model="renewMonths"
              :options="[
                { label: '1 Mes (Mensual)', value: 1 },
                { label: '3 Meses (Trimestral)', value: 3 },
                { label: '6 Meses (Semestral)', value: 6 },
                { label: '1 Año / 12 Meses (Ahorra 15% - 2 meses gratis)', value: 12 },
              ]"
              outlined
              dense
              emit-value
              map-options
            />
          </div>

          <div>
            <label class="text-caption text-weight-bold text-ink block q-mb-xs">Método de pago:</label>
            <q-select
              v-model="renewMethod"
              :options="[
                { label: 'Transferencia Bancaria', value: 'transfer' },
                { label: 'Efectivo', value: 'cash' },
                { label: 'Tarjeta / Pasarela', value: 'card' },
                { label: 'Cripto / USDT', value: 'crypto' },
              ]"
              outlined
              dense
              emit-value
              map-options
            />
          </div>

          <q-input
            v-model="renewRef"
            label="Código / Referencia de comprobante"
            placeholder="Ej: TRF-90234 o Recibo #12"
            outlined
            dense
          />

          <div class="renew-estimate-box q-pa-sm">
            <div class="row justify-between text-caption text-muted">
              <span>Total a liquidar:</span>
              <span class="text-weight-bold text-positive text-subtitle2">${{ calculatedRenewTotal }} USD</span>
            </div>
          </div>
        </q-card-section>

        <q-card-actions align="right">
          <q-btn flat label="Cancelar" v-close-popup no-caps />
          <q-btn
            color="positive"
            label="Confirmar Renovación"
            icon="sym_r_check"
            unelevated
            no-caps
            :loading="actionLoading"
            @click="submitRenew"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- MODAL 2: CAMBIAR PLAN & PRORRATEO -->
    <q-dialog v-model="planModalOpen">
      <q-card style="width: 520px; max-width: 95vw;" class="saas-modal-card q-pa-md">
        <q-card-section>
          <div class="text-h6 text-weight-bold text-ink">Cambiar Plan & Aplicar Prorrateo</div>
          <div class="text-caption text-muted">{{ activeTenant?.name }} • Plan actual: {{ activeTenant?.plan_name }}</div>
        </q-card-section>

        <q-card-section class="q-gutter-y-md">
          <q-select
            v-model="editPlan"
            :options="[
              { label: 'Plan Básico ($120/mes)', value: 'basic' },
              { label: 'Plan Pro ($230/mes)', value: 'pro' },
              { label: 'Plan Elite IA ($390/mes)', value: 'elite' },
            ]"
            label="Nuevo Plan"
            outlined
            dense
            emit-value
            map-options
            @update:model-value="fetchModalProration"
          />

          <div class="row q-col-gutter-sm">
            <div class="col-4">
              <q-input
                v-model.number="editExtraPhones"
                type="number"
                label="Líneas WA (+20$)"
                outlined
                dense
                @update:model-value="fetchModalProration"
              />
            </div>
            <div class="col-4">
              <q-input
                v-model.number="editExtraSocial"
                type="number"
                label="Redes (+10$)"
                outlined
                dense
                @update:model-value="fetchModalProration"
              />
            </div>
            <div class="col-4">
              <q-input
                v-model.number="editExtraAgents"
                type="number"
                label="Agentes extra"
                outlined
                dense
                @update:model-value="fetchModalProration"
              />
            </div>
          </div>

          <div v-if="modalProration" class="proration-breakdown q-pa-sm">
            <div class="row justify-between text-caption text-muted">
              <span>Días restantes en el mes:</span>
              <span class="text-ink text-weight-bold">{{ modalProration.days_remaining }} días</span>
            </div>
            <div class="row justify-between text-caption text-muted">
              <span>Ajuste prorrateado a cobrar hoy:</span>
              <span class="text-positive text-weight-bold">${{ modalProration.prorated_charge_due_today_usd.toFixed(2) }} USD</span>
            </div>
          </div>
        </q-card-section>

        <q-card-actions align="right">
          <q-btn flat label="Cancelar" v-close-popup no-caps />
          <q-btn
            color="primary"
            label="Aplicar Cambio de Plan"
            icon="sym_r_published_with_changes"
            unelevated
            no-caps
            :loading="actionLoading"
            @click="submitPlanChange"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- MODAL 3: TOKEN DE LICENCIA -->
    <q-dialog v-model="tokenModalOpen">
      <q-card style="width: 480px; max-width: 95vw;" class="saas-modal-card q-pa-md">
        <q-card-section>
          <div class="text-h6 text-weight-bold text-ink">Token de Licencia Criptográfico</div>
          <div class="text-caption text-muted">{{ activeTenant?.name }} • Verificación de Vigencia</div>
        </q-card-section>

        <q-card-section>
          <div class="token-display-box q-pa-sm q-mb-md">
            <pre class="token-code">{{ activeTenant?.license_token || 'Token no generado aún.' }}</pre>
          </div>
          <p class="text-caption text-dim">
            Este token está firmado con HMAC-SHA256 y contiene la fecha de caducidad del cliente. Si la fecha expira, el CRM bloquea el envío de WhatsApp automáticamente hasta su renovación.
          </p>
        </q-card-section>

        <q-card-actions align="between">
          <q-btn
            flat
            label="Regenerar Token"
            icon="sym_r_sync"
            color="warning"
            no-caps
            :loading="actionLoading"
            @click="regenerateToken"
          />
          <div class="row q-gutter-x-sm">
            <q-btn flat label="Cerrar" v-close-popup no-caps />
            <q-btn
              color="primary"
              label="Copiar Token"
              icon="sym_r_content_copy"
              unelevated
              no-caps
              @click="copyText(activeTenant?.license_token || '')"
            />
          </div>
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- MODAL 4: AJUSTAR CUOTAS (+/- CANALES Y USUARIOS) -->
    <q-dialog v-model="quotaModalOpen">
      <q-card style="width: 540px; max-width: 95vw; max-height: 90vh;" class="saas-modal-card q-pa-md column no-wrap">
        <q-card-section class="col-auto">
          <div class="row items-center justify-between">
            <div>
              <div class="text-h6 text-weight-bold text-ink">Ajustar Cuotas (+/- Canales y Operadores)</div>
              <div class="text-caption text-muted">{{ activeTenant?.name }} • Plan {{ activeTenant?.plan_name }}</div>
            </div>
            <q-badge color="teal" text-color="white" label="Prorrateo en Vivo" />
          </div>
        </q-card-section>

        <q-card-section class="q-gutter-y-md col scroll">
          <!-- Control de Líneas WhatsApp / Canales -->
          <div class="quota-control-box q-pa-sm rounded-borders bg-surface-subtle">
            <div class="row items-center justify-between q-mb-xs">
              <div class="text-weight-bold text-ink text-body2">
                📱 Líneas WhatsApp / Canales
              </div>
              <span class="text-caption text-primary">+$20.00 USD/mes cada una</span>
            </div>
            <div class="text-caption text-dim q-mb-sm">
              Incluidas en Plan: <strong>{{ getIncludedPhones(activeTenant?.plan) }}</strong> • Líneas Extra actuales: <strong>{{ activeTenant?.extra_phones || 0 }}</strong>
            </div>

            <div class="row items-center justify-between">
              <span class="text-caption text-ink">Líneas Adicionales Deseadas:</span>
              <div class="row items-center q-gutter-x-sm">
                <q-btn
                  round
                  dense
                  flat
                  color="negative"
                  icon="sym_r_remove"
                  :disable="quotaExtraPhones <= 0"
                  @click="decrementPhones"
                />
                <span class="text-subtitle1 text-weight-bolder text-ink" style="min-width: 28px; text-align: center;">
                  {{ quotaExtraPhones }}
                </span>
                <q-btn
                  round
                  dense
                  flat
                  color="positive"
                  icon="sym_r_add"
                  @click="incrementPhones"
                />
              </div>
            </div>
            <div class="text-right text-caption text-teal text-weight-bold q-mt-xs">
              Total resultante: {{ getIncludedPhones(activeTenant?.plan) + quotaExtraPhones }} números WhatsApp habilitados
            </div>
          </div>

          <!-- Control de Cuentas de Redes Sociales (Facebook, Instagram, TikTok) -->
          <div class="quota-control-box q-pa-sm rounded-borders bg-surface-subtle">
            <div class="row items-center justify-between q-mb-xs">
              <div class="text-weight-bold text-ink text-body2">
                🌐 Cuentas de Redes Sociales (FB, IG, TikTok)
              </div>
              <span class="text-caption text-blue-4">+$10.00 USD/mes cada una</span>
            </div>
            <div class="text-caption text-dim q-mb-sm">
              Incluidas en Plan: <strong>{{ getIncludedSocial(activeTenant?.plan) }}</strong> • Redes Extra actuales: <strong>{{ activeTenant?.extra_social || 0 }}</strong>
            </div>

            <div class="row items-center justify-between">
              <span class="text-caption text-ink">Redes Adicionales Deseadas:</span>
              <div class="row items-center q-gutter-x-sm">
                <q-btn
                  round
                  dense
                  flat
                  color="negative"
                  icon="sym_r_remove"
                  :disable="quotaExtraSocial <= 0"
                  @click="decrementSocial"
                />
                <span class="text-subtitle1 text-weight-bolder text-ink" style="min-width: 28px; text-align: center;">
                  {{ quotaExtraSocial }}
                </span>
                <q-btn
                  round
                  dense
                  flat
                  color="positive"
                  icon="sym_r_add"
                  @click="incrementSocial"
                />
              </div>
            </div>
            <div class="text-right text-caption text-blue-4 text-weight-bold q-mt-xs">
              Total resultante: {{ getIncludedSocial(activeTenant?.plan) + quotaExtraSocial }} cuentas de redes habilitadas
            </div>
          </div>

          <!-- Control de Usuarios / Operadores -->
          <div class="quota-control-box q-pa-sm rounded-borders bg-surface-subtle">
            <div class="row items-center justify-between q-mb-xs">
              <div class="text-weight-bold text-ink text-body2">
                👥 Operadores / Agentes
              </div>
              <span class="text-caption text-secondary">+${{ getAgentRate(activeTenant?.plan) }}.00 USD/mes cada uno</span>
            </div>
            <div class="text-caption text-dim q-mb-sm">
              Incluidos en Plan: <strong>{{ getIncludedAgents(activeTenant?.plan) }}</strong> • Agentes Extra actuales: <strong>{{ activeTenant?.extra_agents || 0 }}</strong>
            </div>

            <div class="row items-center justify-between">
              <span class="text-caption text-ink">Agentes Adicionales Deseados:</span>
              <div class="row items-center q-gutter-x-sm">
                <q-btn
                  round
                  dense
                  flat
                  color="negative"
                  icon="sym_r_remove"
                  :disable="quotaExtraAgents <= 0"
                  @click="decrementAgents"
                />
                <span class="text-subtitle1 text-weight-bolder text-ink" style="min-width: 28px; text-align: center;">
                  {{ quotaExtraAgents }}
                </span>
                <q-btn
                  round
                  dense
                  flat
                  color="positive"
                  icon="sym_r_add"
                  @click="incrementAgents"
                />
              </div>
            </div>
            <div class="text-right text-caption text-secondary text-weight-bold q-mt-xs">
              Total resultante: {{ getIncludedAgents(activeTenant?.plan) + quotaExtraAgents }} operadores habilitados
            </div>
          </div>

          <!-- Desglose de Prorrateo Matemático -->
          <div v-if="quotaProration" class="proration-breakdown q-pa-sm">
            <div class="row justify-between text-caption text-muted q-mb-xs">
              <span>Días restantes en el mes:</span>
              <span class="text-ink text-weight-bold">{{ quotaProration.days_remaining }} días</span>
            </div>
            <div class="row justify-between text-caption text-muted q-mb-xs">
              <span>Crédito a favor (no devengado):</span>
              <span class="text-positive text-weight-bold">-${{ quotaProration.unused_credit_usd.toFixed(2) }} USD</span>
            </div>
            <div class="row justify-between text-caption text-muted q-mb-xs">
              <span>Costo nuevo por días restantes:</span>
              <span class="text-ink text-weight-bold">${{ quotaProration.new_plan_cost_remaining_days_usd.toFixed(2) }} USD</span>
            </div>
            <q-separator class="q-my-xs" />
            <div class="row justify-between items-center text-subtitle1">
              <span class="text-weight-bold text-ink">Total prorrateado a cobrar hoy:</span>
              <span class="text-weight-bolder text-teal" style="font-size: 1.3rem;">
                ${{ quotaProration.prorated_charge_due_today_usd.toFixed(2) }} USD
              </span>
            </div>
            <div class="text-caption text-dim text-right">
              Nuevo MRR mensual a partir del próximo ciclo: ${{ quotaProration.new_monthly_price.toFixed(2) }} USD/mes
            </div>
          </div>

          <!-- Datos de Pago para el Ajuste -->
          <div v-if="quotaProration && quotaProration.prorated_charge_due_today_usd > 0" class="q-gutter-y-sm">
            <div class="row q-col-gutter-sm">
              <div class="col-6">
                <q-select
                  v-model="quotaPaymentMethod"
                  :options="[
                    { label: 'Transferencia Bancaria', value: 'transfer' },
                    { label: 'Efectivo', value: 'cash' },
                    { label: 'Tarjeta / Pasarela', value: 'card' },
                    { label: 'Cripto / USDT', value: 'crypto' },
                  ]"
                  label="Método de pago"
                  outlined
                  dense
                  emit-value
                  map-options
                />
              </div>
              <div class="col-6">
                <q-input
                  v-model="quotaReference"
                  label="Referencia / Comprobante"
                  placeholder="Ej: REC-204"
                  outlined
                  dense
                />
              </div>
            </div>
          </div>
        </q-card-section>

        <q-card-actions align="right" class="col-auto">
          <q-btn flat label="Cancelar" v-close-popup no-caps />
          <q-btn
            color="teal"
            label="Confirmar y Aplicar Cuotas"
            icon="sym_r_done_all"
            unelevated
            no-caps
            :loading="actionLoading"
            @click="submitQuotaAdjustment"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- MODAL: AMPLIAR O PARAMETRIZAR PERIODO DE PRUEBA -->
    <q-dialog v-model="trialModalOpen">
      <q-card style="width: 480px; max-width: 95vw;" class="saas-modal-card q-pa-md">
        <q-card-section>
          <div class="row items-center q-gutter-x-sm">
            <q-icon name="sym_r_hourglass_top" color="amber-9" size="24px" />
            <div class="text-h6 text-weight-bold text-ink">Gestionar Período de Prueba</div>
          </div>
          <div class="text-caption text-muted q-mt-xs">
            {{ activeTenant?.name }} • Estado: <strong>{{ activeTenant?.status?.toUpperCase() }}</strong> ({{ activeTenant?.days_remaining }} días restantes)
          </div>
        </q-card-section>

        <q-card-section class="q-gutter-y-md">
          <!-- Días a añadir -->
          <div>
            <label class="text-caption text-weight-bold text-ink block q-mb-xs">Días a sumar a la vigencia:</label>
            <div class="row q-gutter-xs q-mb-sm">
              <q-btn
                v-for="opt in [3, 7, 14, 30]"
                :key="opt"
                dense
                no-caps
                :color="trialDaysToAdd === opt ? 'amber-9' : 'grey-8'"
                :label="`+${opt} días`"
                class="q-px-sm"
                @click="trialDaysToAdd = opt"
              />
            </div>
            <q-input
              v-model.number="trialDaysToAdd"
              type="number"
              min="1"
              label="O escribe el número exacto de días"
              outlined
              dense
            />
          </div>

          <!-- Plan de prueba asignado -->
          <div>
            <label class="text-caption text-weight-bold text-ink block q-mb-xs">Plan de Prueba Asignado:</label>
            <q-select
              v-model="trialPlan"
              :options="[
                { label: 'Plan Básico (1 WA, 8 Redes, 3 Agentes)', value: 'basic' },
                { label: 'Plan Pro (2 WA, 12 Redes, 7 Agentes)', value: 'pro' },
                { label: 'Plan Elite IA (4 WA, 16 Redes, 15 Agentes)', value: 'elite' },
              ]"
              outlined
              dense
              emit-value
              map-options
            />
          </div>

          <!-- Canales y Agentes de prueba adicionales -->
          <div class="row q-col-gutter-sm">
            <div class="col-4">
              <q-input
                v-model.number="trialExtraPhones"
                type="number"
                min="0"
                label="Líneas WA extra"
                outlined
                dense
              />
            </div>
            <div class="col-4">
              <q-input
                v-model.number="trialExtraSocial"
                type="number"
                min="0"
                label="Redes extra"
                outlined
                dense
              />
            </div>
            <div class="col-4">
              <q-input
                v-model.number="trialExtraAgents"
                type="number"
                min="0"
                label="Agentes extra"
                outlined
                dense
              />
            </div>
          </div>
        </q-card-section>

        <q-card-actions align="right" class="q-pt-none">
          <q-btn flat label="Cancelar" color="grey-6" no-caps v-close-popup />
          <q-btn
            unelevated
            label="Guardar y Ampliar Prueba"
            color="amber-9"
            icon="sym_r_check_circle"
            no-caps
            :loading="actionLoading"
            @click="submitTrialExtension"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useQuasar } from 'quasar'
import { useAuthStore } from '@/modules/auth/stores/auth.store'
import {
  fetchSuperAdminData,
  renewTenantSubscription,
  previewProration,
  applyPlanChange,
  adjustTenantQuotas,
  toggleTenantSuspension,
  generateTenantToken,
  generateAiReminder,
  extendTenantTrial,
  type TenantSummary,
  type TenantItem,
  type ProrationData,
} from '../api/superadmin.api'

const $q = useQuasar()

const loading = ref(false)
const actionLoading = ref(false)
const generatingAi = ref(false)
const activeTab = ref('tenants')
const filterStatus = ref('all')
const searchQuery = ref('')

const summary = ref<TenantSummary>({
  total_tenants: 0,
  total_mrr_usd: 0,
  total_arr_usd: 0,
  total_phones_leased: 0,
  active_count: 0,
  warning_count: 0,
  expired_count: 0,
  suspended_count: 0,
})

const tenants = ref<TenantItem[]>([])
const activeTenant = ref<TenantItem | null>(null)

// Columnas de la Tabla
const columns = [
  { name: 'name', label: 'Empresa / Contacto', field: 'name', align: 'left' as const },
  { name: 'plan', label: 'Plan & Modalidad', field: 'plan', align: 'left' as const },
  { name: 'monthly_mrr_usd', label: 'MRR Facturado', field: 'monthly_mrr_usd', align: 'left' as const },
  { name: 'resources', label: 'Líneas & Agentes', field: 'resources', align: 'left' as const },
  { name: 'validity', label: 'Vigencia / Fecha Corte', field: 'validity', align: 'left' as const },
  { name: 'actions', label: 'Gestión', field: 'actions', align: 'right' as const },
]

// Opciones de empresas para selects
const tenantOptions = computed(() => {
  return tenants.value.map(t => ({ label: `${t.name} (${t.plan_name})`, value: t.id }))
})

// Tenants filtrados
const filteredTenants = computed(() => {
  return tenants.value.filter(t => {
    const matchesSearch = t.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      (t.contact_name && t.contact_name.toLowerCase().includes(searchQuery.value.toLowerCase()))

    if (!matchesSearch) return false

    if (filterStatus.value === 'all') return true
    if (filterStatus.value === 'active') return t.health_status === 'active'
    if (filterStatus.value === 'warning') return t.health_status === 'warning'
    if (filterStatus.value === 'expired') return t.health_status === 'expired' || t.health_status === 'suspended'
    return true
  })
})

// Modales
const renewModalOpen = ref(false)
const renewMonths = ref(1)
const renewMethod = ref('transfer')
const renewRef = ref('')

const planModalOpen = ref(false)
const editPlan = ref<'basic' | 'pro' | 'elite'>('pro')
const editExtraPhones = ref(0)
const editExtraSocial = ref(0)
const editExtraAgents = ref(0)
const modalProration = ref<ProrationData | null>(null)

const tokenModalOpen = ref(false)

const trialModalOpen = ref(false)
const trialDaysToAdd = ref(7)
const trialPlan = ref<'basic' | 'pro' | 'elite'>('pro')
const trialExtraPhones = ref(0)
const trialExtraSocial = ref(0)
const trialExtraAgents = ref(0)

const quotaModalOpen = ref(false)
const quotaExtraPhones = ref(0)
const quotaExtraSocial = ref(0)
const quotaExtraAgents = ref(0)
const quotaPaymentMethod = ref('transfer')
const quotaReference = ref('')
const quotaProration = ref<ProrationData | null>(null)

// Estado del Copiloto Hentle-AI
const selectedTenantAi = ref<string>('')
const aiTone = ref<'friendly' | 'formal' | 'urgent'>('friendly')
const aiMessageText = ref('')
const aiWhatsappUrl = ref('')

// Estado del Simulador Libre
const simTenantId = ref<string>('')
const simNewPlan = ref('pro')
const simExtraPhones = ref(0)
const simExtraAgents = ref(0)
const prorationResult = ref<ProrationData | null>(null)

const calculatedRenewTotal = computed(() => {
  if (!activeTenant.value) return 0
  const monthly = activeTenant.value.monthly_mrr_usd
  if (renewMonths.value >= 12) {
    return (monthly * 12 * 0.85).toFixed(2)
  }
  return (monthly * renewMonths.value).toFixed(2)
})

function getHealthLabel(row: TenantItem): string {
  if (row.health_status === 'suspended') return 'Suspendido'
  if (row.days_remaining < 0) return `Vencido (${Math.abs(row.days_remaining)}d)`
  if (row.days_remaining === 0) return 'Vence hoy'
  return `${row.days_remaining} días restantes`
}

function getHealthTextColor(status: string): string {
  if (status === 'active') return 'text-positive'
  if (status === 'warning') return 'text-warning'
  return 'text-negative'
}

async function loadData() {
  loading.value = true
  try {
    const data = await fetchSuperAdminData()
    summary.value = data.summary
    tenants.value = data.tenants
    if (tenants.value.length > 0 && !selectedTenantAi.value) {
      selectedTenantAi.value = tenants.value[0].id
      simTenantId.value = tenants.value[0].id
      runProrationSim()
    }
  } catch (e: any) {
    $q.notify({ type: 'negative', message: 'Error cargando datos de SuperAdmin: ' + (e.message || 'Error de red') })
  } finally {
    loading.value = false
  }
}

// RENOVAR
function openRenewModal(tenant: TenantItem) {
  activeTenant.value = tenant
  renewMonths.value = 1
  renewRef.value = ''
  renewModalOpen.value = true
}

async function submitRenew() {
  if (!activeTenant.value) return
  actionLoading.value = true
  try {
    await renewTenantSubscription(activeTenant.value.id, {
      months: renewMonths.value,
      payment_method: renewMethod.value,
      reference_code: renewRef.value,
    })
    $q.notify({ type: 'positive', message: '¡Suscripción renovada exitosamente!' })
    renewModalOpen.value = false
    await loadData()
  } catch (e: any) {
    $q.notify({ type: 'negative', message: 'Error renovando suscripción: ' + e.message })
  } finally {
    actionLoading.value = false
  }
}

// GESTIÓN DE PERIODO DE PRUEBA
function openTrialModal(tenant: TenantItem) {
  activeTenant.value = tenant
  trialDaysToAdd.value = 7
  trialPlan.value = tenant.plan
  trialExtraPhones.value = tenant.extra_phones || 0
  trialExtraSocial.value = tenant.extra_social || 0
  trialExtraAgents.value = tenant.extra_agents || 0
  trialModalOpen.value = true
}

async function submitTrialExtension() {
  if (!activeTenant.value) return
  actionLoading.value = true
  try {
    const res = await extendTenantTrial(activeTenant.value.id, {
      days: trialDaysToAdd.value,
      plan: trialPlan.value,
      extra_phones: trialExtraPhones.value,
      extra_social: trialExtraSocial.value,
      extra_agents: trialExtraAgents.value,
    })
    $q.notify({
      type: 'positive',
      message: res.message || 'Período de prueba ampliado con éxito.',
      position: 'bottom-right',
    })
    trialModalOpen.value = false
    await loadData()
  } catch (err: any) {
    $q.notify({
      type: 'negative',
      message: err?.response?.data?.message || err?.message || 'Error al ampliar período de prueba',
      position: 'bottom-right',
    })
  } finally {
    actionLoading.value = false
  }
}

// CAMBIAR PLAN
function openPlanModal(tenant: TenantItem) {
  activeTenant.value = tenant
  editPlan.value = tenant.plan
  editExtraPhones.value = tenant.extra_phones
  editExtraSocial.value = tenant.extra_social || 0
  editExtraAgents.value = tenant.extra_agents
  planModalOpen.value = true
  fetchModalProration()
}

async function fetchModalProration() {
  if (!activeTenant.value) return
  try {
    modalProration.value = await previewProration(activeTenant.value.id, {
      new_plan: editPlan.value,
      extra_phones: editExtraPhones.value,
      extra_social: editExtraSocial.value,
      extra_agents: editExtraAgents.value,
    })
  } catch (e) {
    // Silencioso
  }
}

async function submitPlanChange() {
  if (!activeTenant.value) return
  actionLoading.value = true
  try {
    await applyPlanChange(activeTenant.value.id, {
      new_plan: editPlan.value,
      extra_phones: editExtraPhones.value,
      extra_social: editExtraSocial.value,
      extra_agents: editExtraAgents.value,
      payment_method: 'transfer',
    })
    $q.notify({ type: 'positive', message: '¡Plan y prorrateo aplicados correctamente!' })
    planModalOpen.value = false
    await loadData()
  } catch (e: any) {
    $q.notify({ type: 'negative', message: 'Error aplicando cambio de plan: ' + e.message })
  } finally {
    actionLoading.value = false
  }
}

// GESTIÓN DE CUOTAS (+/- CANALES, REDES Y OPERADORES)
function getIncludedPhones(plan?: string): number {
  if (plan === 'pro') return 2
  if (plan === 'elite') return 4
  return 1
}

function getIncludedSocial(plan?: string): number {
  if (plan === 'pro') return 12
  if (plan === 'elite') return 16
  return 8
}

function getIncludedAgents(plan?: string): number {
  if (plan === 'pro') return 7
  if (plan === 'elite') return 15
  return 3
}

function getAgentRate(plan?: string): number {
  if (plan === 'pro') return 15
  if (plan === 'elite') return 12
  return 18
}

function incrementPhones() {
  quotaExtraPhones.value++
  fetchQuotaProration()
}

function decrementPhones() {
  if (quotaExtraPhones.value > 0) {
    quotaExtraPhones.value--
    fetchQuotaProration()
  }
}

function incrementSocial() {
  quotaExtraSocial.value++
  fetchQuotaProration()
}

function decrementSocial() {
  if (quotaExtraSocial.value > 0) {
    quotaExtraSocial.value--
    fetchQuotaProration()
  }
}

function incrementAgents() {
  quotaExtraAgents.value++
  fetchQuotaProration()
}

function decrementAgents() {
  if (quotaExtraAgents.value > 0) {
    quotaExtraAgents.value--
    fetchQuotaProration()
  }
}

function openQuotaModal(tenant: TenantItem) {
  activeTenant.value = tenant
  quotaExtraPhones.value = tenant.extra_phones
  quotaExtraSocial.value = tenant.extra_social || 0
  quotaExtraAgents.value = tenant.extra_agents
  quotaPaymentMethod.value = 'transfer'
  quotaReference.value = ''
  quotaModalOpen.value = true
  fetchQuotaProration()
}

async function fetchQuotaProration() {
  if (!activeTenant.value) return
  try {
    quotaProration.value = await previewProration(activeTenant.value.id, {
      new_plan: activeTenant.value.plan,
      extra_phones: quotaExtraPhones.value,
      extra_social: quotaExtraSocial.value,
      extra_agents: quotaExtraAgents.value,
    })
  } catch (e) {
    // Silencioso
  }
}

async function submitQuotaAdjustment() {
  if (!activeTenant.value) return
  actionLoading.value = true
  try {
    await adjustTenantQuotas(activeTenant.value.id, {
      extra_phones: quotaExtraPhones.value,
      extra_social: quotaExtraSocial.value,
      extra_agents: quotaExtraAgents.value,
      payment_method: quotaPaymentMethod.value,
      reference_code: quotaReference.value,
    })
    $q.notify({
      type: 'positive',
      message: '¡Cuotas de canales, redes y operadores actualizadas con prorrateo aplicado!',
      actions: [
        {
          label: 'Avisar por WhatsApp',
          color: 'white',
          handler: () => {
            openAiModal(activeTenant.value!)
          },
        },
      ],
    })
    quotaModalOpen.value = false
    await loadData()
  } catch (e: any) {
    $q.notify({ type: 'negative', message: 'Error ajustando cuotas: ' + (e.message || 'Error del servidor') })
  } finally {
    actionLoading.value = false
  }
}

// TOKEN
function openTokenModal(tenant: TenantItem) {
  activeTenant.value = tenant
  tokenModalOpen.value = true
}

async function regenerateToken() {
  if (!activeTenant.value) return
  actionLoading.value = true
  try {
    const res = await generateTenantToken(activeTenant.value.id)
    activeTenant.value.license_token = res.license_token
    $q.notify({ type: 'positive', message: 'Nuevo token generado y firmado con éxito.' })
    await loadData()
  } catch (e: any) {
    $q.notify({ type: 'negative', message: 'Error generando token: ' + e.message })
  } finally {
    actionLoading.value = false
  }
}

// TOGGLE SUSPENSIÓN
async function toggleStatus(tenant: TenantItem) {
  try {
    const res = await toggleTenantSuspension(tenant.id)
    $q.notify({ type: 'info', message: res.message })
    await loadData()
  } catch (e: any) {
    $q.notify({ type: 'negative', message: 'Error modificando estado: ' + e.message })
  }
}

// HENTLE-AI RECORDATORIOS
function openAiModal(tenant: TenantItem) {
  selectedTenantAi.value = tenant.id
  activeTab.value = 'copilot'
  triggerAiReminder()
}

async function triggerAiReminder() {
  if (!selectedTenantAi.value) return
  generatingAi.value = true
  try {
    const res = await generateAiReminder(selectedTenantAi.value, aiTone.value)
    aiMessageText.value = res.message_text
    aiWhatsappUrl.value = res.whatsapp_url
    $q.notify({ type: 'positive', message: 'Mensaje redactado por Hentle-AI con éxito.' })
  } catch (e: any) {
    $q.notify({ type: 'negative', message: 'Error generando mensaje IA: ' + e.message })
  } finally {
    generatingAi.value = false
  }
}

// SIMULADOR PRORRATEO LIBRE
async function runProrationSim() {
  if (!simTenantId.value) return
  try {
    prorationResult.value = await previewProration(simTenantId.value, {
      new_plan: simNewPlan.value,
      extra_phones: simExtraPhones.value,
      extra_agents: simExtraAgents.value,
    })
  } catch (e) {
    // Silencioso
  }
}

function copyText(txt: string) {
  navigator.clipboard.writeText(txt)
  $q.notify({ type: 'positive', message: 'Copiado al portapapeles.' })
}

function openWhatsappUrl(url: string) {
  if (!url) return
  window.open(url, '_blank')
}

const router = useRouter()
const authStore = useAuthStore()

onMounted(() => {
  if (authStore.user && !authStore.isSuperAdmin) {
    router.replace('/app/conversations')
    return
  }
  loadData()
})
</script>

<style scoped lang="scss">
.superadmin-portal {
  max-width: 1400px;
  margin: 0 auto;
}

.saas-badge-pill {
  font-size: 10px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  padding: 3px 8px;
  border-radius: var(--crm-radius-pill);
  background: rgba(99, 102, 241, 0.15);
  color: #818cf8;
  border: 1px solid rgba(99, 102, 241, 0.3);
}

// KPI CARDS
.kpi-card {
  background: var(--crm-bg-card);
  border: 1px solid var(--crm-color-border);
  border-radius: var(--crm-radius-card);
  padding: 18px 20px;
  transition: all var(--crm-transition);

  &:hover {
    border-color: var(--crm-color-border-hover);
    transform: translateY(-2px);
  }

  &--emerald {
    border-color: rgba(16, 185, 129, 0.25);
    background: linear-gradient(180deg, rgba(16, 185, 129, 0.05) 0%, var(--crm-bg-card) 60%);
  }

  &--ai {
    border-color: rgba(99, 102, 241, 0.3);
    background: linear-gradient(180deg, rgba(99, 102, 241, 0.06) 0%, var(--crm-bg-card) 60%);
  }
}

.kpi-label {
  font-size: 0.78rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--crm-color-dim);
}

.kpi-icon-box {
  width: 32px;
  height: 32px;
  border-radius: var(--crm-radius-sm);
  background: rgba(255, 255, 255, 0.03);
  display: flex;
  align-items: center;
  justify-content: center;
}

.kpi-value {
  font-size: 1.85rem;
  font-weight: 800;
  letter-spacing: -0.02em;
  margin: 4px 0;
}

.kpi-subtext {
  font-size: 0.76rem;
}

// TABS
.superadmin-tabs {
  background: rgba(255, 255, 255, 0.02);
  border-radius: var(--crm-radius-control);
  border: 1px solid var(--crm-color-border);
}

// FILTROS
.filter-toggle {
  background: rgba(255, 255, 255, 0.03);
  border: 1px solid var(--crm-color-border);
  border-radius: var(--crm-radius-control);
}

.search-input {
  width: 280px;
}

// TABLA
.table-card {
  background: var(--crm-bg-card);
  border: 1px solid var(--crm-color-border);
  border-radius: var(--crm-radius-card);
}

.plan-pill {
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  padding: 2px 7px;
  border-radius: var(--crm-radius-pill);

  &--basic { background: rgba(148, 163, 184, 0.15); color: var(--crm-color-muted); }
  &--pro { background: var(--crm-color-primary-soft); color: var(--crm-color-primary); }
  &--elite { background: rgba(99, 102, 241, 0.18); color: #818cf8; }
}

.status-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;

  &--active { background: #10b981; box-shadow: 0 0 6px #10b981; }
  &--warning { background: #f59e0b; box-shadow: 0 0 6px #f59e0b; }
  &--expired, &--suspended { background: #ef4444; box-shadow: 0 0 6px #ef4444; }
}

// CARDS GENERALES
.saas-card {
  background: var(--crm-bg-card);
  border: 1px solid var(--crm-color-border);
  border-radius: var(--crm-radius-card);
}

.ai-preview-box {
  background: rgba(0, 0, 0, 0.25);
  border: 1px solid var(--crm-color-border);
  border-radius: var(--crm-radius-control);
  max-height: 280px;
  overflow-y: auto;
}

.ai-preview-text {
  font-family: inherit;
  font-size: 0.88rem;
  line-height: 1.55;
  color: var(--crm-color-ink);
  white-space: pre-wrap;
  margin: 0;
}

.proration-breakdown {
  background: rgba(16, 185, 129, 0.05);
  border: 1px solid rgba(16, 185, 129, 0.2);
  border-radius: var(--crm-radius-control);
}

.renew-estimate-box {
  background: rgba(255, 255, 255, 0.03);
  border: 1px solid var(--crm-color-border);
  border-radius: var(--crm-radius-control);
}

.token-display-box {
  background: rgba(0, 0, 0, 0.3);
  border: 1px solid var(--crm-color-border);
  border-radius: var(--crm-radius-control);
  overflow-x: auto;
}

.token-code {
  font-family: var(--crm-font-mono);
  font-size: 11px;
  color: #38bdf8;
  margin: 0;
  word-break: break-all;
}

.quota-control-box {
  background: rgba(255, 255, 255, 0.03);
  border: 1px solid var(--crm-color-border);
  border-radius: var(--crm-radius-control);
}

.saas-modal-card {
  background: var(--crm-bg-card) !important;
  border: 1px solid var(--crm-color-border);
  border-radius: var(--crm-radius-card);
}
</style>
