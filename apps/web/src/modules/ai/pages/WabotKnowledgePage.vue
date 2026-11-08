<template>
  <div class="whaticket-wabot-page">
    <!-- Header -->
    <div class="row items-center justify-between q-col-gutter-sm q-mb-md">
      <div class="col-12 col-md-auto">
        <div class="row items-center q-gutter-x-sm">
          <h1 class="text-h5 text-bold text-white q-my-none">Base de Conocimiento & Hentle-AI</h1>
          <q-badge
            :color="aiConfig.has_api_key ? 'positive' : 'warning'"
            :label="aiConfig.has_api_key ? `Activo: ${activeProviderLabel} (${aiConfig.model})` : 'Configuración Pendiente'"
            class="text-weight-bold"
          />
          <q-badge
            v-if="aiConfig.budget_enabled"
            :color="aiConfig.is_budget_exceeded ? 'negative' : 'teal-8'"
            :label="aiConfig.is_budget_exceeded ? `Límite alcanzado: $${(aiConfig.current_spending_usd || 0).toFixed(4)} / $${(aiConfig.spending_limit_usd || 0.20).toFixed(2)} USD` : `Consumo: $${(aiConfig.current_spending_usd || 0).toFixed(4)} / $${(aiConfig.spending_limit_usd || 0.20).toFixed(2)} USD`"
            class="text-weight-bold"
          />
        </div>
        <p class="text-caption text-grey-4 q-mt-xs q-mb-none">
          Motor cognitivo para respuestas inteligentes en la Bandeja Multicanal y búsqueda semántica RAG (pgvector 16).
        </p>
      </div>

      <div class="col-12 col-md-auto row items-center q-gutter-sm">
        <q-btn
          v-if="isAdmin"
          outline
          icon="sym_r_tune"
          label="Configurar Motor IA"
          color="primary"
          no-caps
          class="whaticket-btn-outline"
          @click="openConfigModal"
        />
        <q-btn
          outline
          icon="sym_r_upload_file"
          label="Subir Archivo con IA"
          color="teal-4"
          no-caps
          class="whaticket-btn-outline"
          @click="openExtractorModal"
        />
        <q-btn
          color="primary"
          label="+ Nuevo artículo"
          unelevated
          no-caps
          class="whaticket-btn-primary"
          @click="isArticleModalOpen = true"
        />
      </div>
    </div>

    <!-- Pestañas Superiores: Atención por Chat | Agenda tu Reunión -->
    <div class="whaticket-subnav-tabs q-mb-lg">
      <button
        class="whaticket-subnav-btn"
        :class="{ 'whaticket-subnav-btn--active': activeTab === 'chat' }"
        @click="activeTab = 'chat'"
      >
        <q-icon name="sym_r_chat" size="18px" />
        <span>Atención por chat (Copiloto)</span>
      </button>

      <button
        class="whaticket-subnav-btn"
        :class="{ 'whaticket-subnav-btn--active': activeTab === 'booking' }"
        @click="activeTab = 'booking'"
      >
        <q-icon name="sym_r_calendar_month" size="18px" />
        <span>Agenda tu reunión</span>
      </button>
    </div>

    <!-- Pestaña 1: Atención por Chat (Copiloto) -->
    <div v-if="activeTab === 'chat'">
      <!-- Banner Informativo del Proveedor Activo -->
      <div class="q-mb-md">
        <q-banner rounded class="bg-dark text-white border-subtle q-pa-md">
          <template #avatar>
            <q-icon name="sym_r_psychology" color="primary" size="32px" />
          </template>
          <div class="row items-center justify-between">
            <div>
              <div class="text-subtitle2 text-bold text-white">
                Proveedor Actual: {{ activeProviderLabel }} • Modelo: <span class="text-primary">{{ aiConfig.model }}</span>
              </div>
              <div class="text-caption text-grey-4">
                {{ aiConfig.has_api_key ? `API Key enlazada (${aiConfig.api_key_masked}). Listo para responder en la Bandeja Multicanal.` : 'Aún no se ha enlazado una API Key. Pulsa en "Configurar Motor IA" para ingresar tu clave.' }}
              </div>
            </div>
            <q-btn
              v-if="isAdmin"
              flat
              dense
              color="primary"
              label="Probar o Cambiar Modelo"
              icon="sym_r_bolt"
              no-caps
              class="q-px-sm"
              @click="openConfigModal"
            />
          </div>
        </q-banner>
      </div>

      <!-- Listado de Artículos Vectorizados cuando sí hay datos -->
      <div v-if="articles.length > 0" class="row q-col-gutter-md">
        <div v-for="art in articles" :key="art.id" class="col-12 col-md-6">
          <q-card flat bordered class="whaticket-article-card">
            <q-card-section>
              <div class="row items-center justify-between">
                <div class="text-bold text-white text-subtitle1">{{ art.title }}</div>
                <div class="row items-center q-gutter-x-xs">
                  <q-chip
                    v-if="art.metadata?.source_file"
                    dense
                    size="sm"
                    icon="sym_r_description"
                    color="blue-grey-9"
                    text-color="teal-2"
                  >
                    {{ art.metadata.source_file }}
                  </q-chip>
                  <q-badge color="positive" class="text-bold">Vectorizado (pgvector)</q-badge>
                </div>
              </div>
              <p class="text-caption text-grey-4 q-mt-sm">{{ art.content }}</p>
              <div v-if="art.metadata?.tags && art.metadata.tags.length > 0" class="row items-center q-gutter-xs q-mt-xs">
                <q-badge
                  v-for="tag in art.metadata.tags"
                  :key="tag"
                  color="dark"
                  text-color="teal-3"
                  class="border-subtle"
                >
                  #{{ tag }}
                </q-badge>
              </div>
              <div class="row items-center justify-between text-caption text-grey-5 q-mt-md">
                <div class="row items-center q-gutter-x-xs ellipsis" style="max-width: 80%">
                  <q-avatar size="20px" color="teal-9" text-color="teal-2" class="text-weight-bold" style="font-size: 10px">
                    {{ getInitials(art.user?.name) }}
                  </q-avatar>
                  <span>Registrado por: <strong class="text-grey-3">{{ art.user?.name || 'Administración' }}</strong></span>
                  <span v-if="art.created_at" class="text-grey-6 font-mono">• {{ formatDate(art.created_at) }}</span>
                </div>
                <q-btn
                  v-if="canDeleteArticle(art)"
                  flat
                  round
                  dense
                  icon="sym_r_delete"
                  color="negative"
                  size="sm"
                  @click="deleteArticle(art.id)"
                >
                  <q-tooltip>Eliminar artículo</q-tooltip>
                </q-btn>
              </div>
            </q-card-section>
          </q-card>
        </div>
      </div>

      <!-- Estado vacío -->
      <div v-else class="whaticket-ai-status-card">
        <div class="whaticket-ai-icon-box">
          <q-icon name="sym_r_auto_awesome" size="36px" color="amber-4" />
        </div>
        <div class="text-h6 text-bold text-white q-mt-md">
          No hay artículos en la Base de Conocimiento.
        </div>
        <div class="text-caption text-grey-4 q-mt-xs q-mb-lg" style="max-width: 500px">
          Sube un documento con la IA o agrega preguntas frecuentes para que el copiloto de IA pueda citar datos precisos.
        </div>
        <div class="row items-center q-gutter-sm">
          <q-btn
            outline
            color="teal-4"
            icon="sym_r_upload_file"
            label="Subir Archivo con IA"
            no-caps
            class="whaticket-btn-outline"
            @click="openExtractorModal"
          />
          <q-btn
            color="primary"
            icon="sym_r_add"
            label="Nuevo Artículo Manual"
            unelevated
            no-caps
            class="whaticket-btn-primary"
            @click="isArticleModalOpen = true"
          />
        </div>
      </div>
    </div>

    <!-- Pestaña 2: Agenda tu Reunión (Roadmap/Integración) -->
    <div v-else-if="activeTab === 'booking'" class="whaticket-ai-status-card">
      <div class="whaticket-ai-icon-box" style="background: rgba(14, 165, 233, 0.12)">
        <q-icon name="sym_r_calendar_month" size="36px" color="light-blue-4" />
      </div>
      <div class="text-h6 text-bold text-white q-mt-md">
        Agenda de Reuniones y Citas Automatizadas
      </div>
      <div class="text-caption text-grey-4 q-mt-xs q-mb-lg" style="max-width: 560px">
        Permite a tus clientes y prospectos agendar reuniones de consultoría o demos directamente a través del chat de WhatsApp, sincronizándolo automáticamente con Google Calendar o Cal.com.
      </div>
      <q-badge color="teal-9" text-color="teal-2" label="Módulo en Roadmap v2.2 — Próximamente" class="q-py-xs q-px-sm text-weight-bold" />
    </div>

    <!-- Modal Configuración de IA (Multi-proveedor: MiniMax, Gemini, OpenAI, DeepSeek) -->
    <q-dialog v-model="isConfigModalOpen" persistent>
      <q-card style="width: 680px; max-width: 95vw; max-height: 90vh;" class="whaticket-modal-card q-pa-md column no-wrap">
        <q-card-section class="row items-center justify-between col-auto">
          <div>
            <div class="text-h6 text-bold text-white">Configuración del Motor IA (Hentle-AI)</div>
            <div class="text-caption text-grey-4">Enlaza tu API Key física y selecciona tu modelo favorito en cualquier momento.</div>
          </div>
          <q-btn flat round dense icon="sym_r_close" color="grey-4" v-close-popup />
        </q-card-section>

        <q-card-section class="q-gutter-y-md col scroll">
          <!-- 1. Selector de Proveedor -->
          <div class="row q-col-gutter-sm">
            <div class="col-12 col-sm-6">
              <q-select
                v-model="configForm.provider"
                :options="providerOptions"
                emit-value
                map-options
                label="Proveedor de Inteligencia Artificial"
                outlined
                dark
                dense
                @update:model-value="onProviderChange"
              >
                <template #prepend>
                  <q-icon name="sym_r_smart_toy" color="primary" size="18px" />
                </template>
              </q-select>
            </div>

            <!-- 2. Selector / Input de Modelo -->
            <div class="col-12 col-sm-6">
              <q-select
                v-model="configForm.model"
                :options="currentModelOptions"
                label="Modelo LLM"
                use-input
                new-value-mode="add-unique"
                outlined
                dark
                dense
              >
                <template #prepend>
                  <q-icon name="sym_r_model_training" color="teal-4" size="18px" />
                </template>
              </q-select>
            </div>
          </div>

          <!-- 3. Base URL (API Endpoint) -->
          <q-input
            v-model="configForm.base_url"
            label="API Base URL (Endpoint)"
            outlined
            dark
            dense
            hint="Autocompletado para el proveedor seleccionado."
          >
            <template #prepend>
              <q-icon name="sym_r_link" color="grey-4" size="18px" />
            </template>
          </q-input>

          <div v-if="configForm.provider === 'minimax'" class="row items-center q-gutter-x-xs q-mt-xs">
            <span class="text-caption text-grey-5" style="font-size: 11px;">Región MiniMax:</span>
            <q-btn flat dense no-caps size="xs" color="teal-4" label="Global (api.minimax.io)" @click="configForm.base_url = 'https://api.minimax.io/v1'" />
            <q-btn flat dense no-caps size="xs" color="grey-4" label="Alternativo (api.minimaxi.chat)" @click="configForm.base_url = 'https://api.minimaxi.chat/v1'" />
            <q-btn flat dense no-caps size="xs" color="grey-4" label="Doméstico (api.minimax.chat)" @click="configForm.base_url = 'https://api.minimax.chat/v1'" />
          </div>

          <!-- Indicador visual de clave guardada y activa -->
          <div v-if="aiConfig.has_api_key" class="q-pa-sm rounded-borders row items-center justify-between" style="background: rgba(16, 185, 129, 0.12); border: 1px solid rgba(16, 185, 129, 0.35);">
            <div class="row items-center q-gutter-x-sm">
              <q-icon name="sym_r_verified_user" color="positive" size="22px" />
              <div>
                <div class="text-caption text-weight-bold text-white">Clave API guardada y conectada</div>
                <div class="text-caption text-teal-3 font-mono" style="font-size: 0.75rem">{{ aiConfig.api_key_masked }}</div>
              </div>
            </div>
            <q-badge color="positive" label="✓ Enlazada en Base de Datos" class="text-weight-bold q-px-sm" />
          </div>

          <!-- 4. API Key (para ingresar nueva clave o cambiarla) -->
          <q-input
            v-model="configForm.api_key"
            :label="aiConfig.has_api_key ? 'Reemplazar API Key (opcional, deja vacío para conservar la actual)' : 'Ingresa tu API Key de ' + configForm.provider"
            :type="showApiKey ? 'text' : 'password'"
            outlined
            dark
            dense
            :placeholder="aiConfig.has_api_key ? `Conservando: ${aiConfig.api_key_masked}` : 'Pega aquí tu clave API'"
            :hint="aiConfig.has_api_key ? 'Tu clave actual sigue activa. Solo escribe aquí si deseas cambiarla por otra.' : 'Tu clave se almacena de forma segura en la base de datos.'"
          >
            <template #prepend>
              <q-icon name="sym_r_key" color="amber-4" size="18px" />
            </template>
            <template #append>
              <q-btn
                flat
                round
                dense
                :icon="showApiKey ? 'sym_r_visibility_off' : 'sym_r_visibility'"
                color="grey-4"
                @click="showApiKey = !showApiKey"
              />
            </template>
          </q-input>

          <!-- 5. System Prompt (Instrucciones) -->
          <q-input
            v-model="configForm.system_prompt"
            label="Instrucciones Generales del Asistente (System Prompt)"
            type="textarea"
            rows="3"
            outlined
            dark
            hint="Directrices para que el copiloto responda en el tono deseado."
          />

          <!-- 6. Umbral de similitud -->
          <div class="q-px-xs q-pt-sm">
            <div class="row justify-between text-caption text-grey-4 q-mb-xs">
              <span>Umbral de Similitud Semántica (pgvector):</span>
              <span class="text-bold text-primary">{{ configForm.similarity_threshold }}</span>
            </div>
            <q-slider
              v-model="configForm.similarity_threshold"
              :min="0.5"
              :max="0.95"
              :step="0.05"
              color="primary"
              dark
            />
          </div>

          <!-- 7. Control de Presupuesto y Consumo de API (Kill-Switch en USD) -->
          <div class="q-pa-sm rounded-borders q-mt-sm" style="background: rgba(255, 255, 255, 0.03); border: 1px solid var(--crm-color-border)">
            <div class="row items-center justify-between">
              <div class="row items-center q-gutter-x-xs">
                <q-icon name="sym_r_attach_money" color="positive" size="18px" />
                <span class="text-caption text-weight-bold text-white">Límite de Consumo de API (Kill-Switch)</span>
              </div>
              <q-toggle
                v-model="configForm.budget_enabled"
                color="positive"
                dense
              />
            </div>
            <div class="text-caption text-grey-4 q-mt-xs" style="font-size: 0.72rem">
              Deshabilita automáticamente las consultas de IA cuando el costo acumulado alcance el presupuesto fijado.
            </div>

            <div v-if="configForm.budget_enabled" class="q-mt-sm q-gutter-y-xs">
              <q-input
                v-model.number="configForm.spending_limit_usd"
                type="number"
                label="Límite máximo de gasto (USD)"
                step="0.05"
                min="0.01"
                max="1000"
                outlined
                dark
                dense
                prefix="$"
                suffix="USD"
                hint="ej: 0.20 para detener el consumo al alcanzar 20 centavos de dólar"
              />

              <!-- Medidor en vivo del consumo -->
              <div class="q-mt-md q-pa-sm rounded-borders" style="background: rgba(0,0,0,0.25)">
                <div class="row items-center justify-between text-caption q-mb-xs">
                  <span class="text-grey-4">Consumo actual:</span>
                  <span class="font-mono text-weight-bold" :class="aiConfig.is_budget_exceeded ? 'text-negative' : 'text-teal-3'">
                    ${{ (aiConfig.current_spending_usd || 0).toFixed(4) }} / ${{ (configForm.spending_limit_usd || 0.20).toFixed(2) }} USD
                  </span>
                </div>

                <q-linear-progress
                  :value="Math.min(1, (aiConfig.current_spending_usd || 0) / (configForm.spending_limit_usd || 0.20))"
                  :color="aiConfig.is_budget_exceeded ? 'negative' : ((aiConfig.current_spending_usd || 0) / (configForm.spending_limit_usd || 0.20) > 0.8 ? 'warning' : 'positive')"
                  rounded
                  size="6px"
                />

                <div class="row items-center justify-between text-caption text-grey-5 q-mt-xs" style="font-size: 0.7rem">
                  <span>{{ (aiConfig.total_tokens_used || 0).toLocaleString() }} tokens consumidos ({{ aiConfig.total_requests_count || 0 }} peticiones)</span>
                  <q-btn
                    flat
                    dense
                    no-caps
                    size="xs"
                    color="amber-4"
                    icon="sym_r_restart_alt"
                    label="Reiniciar a $0.00"
                    :loading="isResettingBudget"
                    @click="handleResetBudget"
                  />
                </div>
              </div>

              <div v-if="aiConfig.is_budget_exceeded" class="q-pa-xs rounded-borders bg-negative text-white text-caption q-mt-xs row items-center q-gutter-x-xs">
                <q-icon name="sym_r_warning" size="16px" />
                <span>¡Presupuesto alcanzado! El motor de IA está pausado para no generar más costos.</span>
              </div>
            </div>
          </div>

          <!-- 8. Módulos de Automatización Omnicanal & Copiloto (Fases 2, 3, 4, 5) -->
          <div class="q-pa-sm rounded-borders q-mt-sm" style="background: rgba(255, 255, 255, 0.03); border: 1px solid var(--crm-color-border)">
            <div class="row items-center q-gutter-x-xs q-mb-sm">
              <q-icon name="sym_r_smart_toy" color="amber-4" size="18px" />
              <span class="text-caption text-weight-bold text-white">Módulos de Automatización & Copiloto</span>
            </div>

            <!-- Auto-Respuestas Semi-Inmediatas -->
            <div class="q-py-xs">
              <div class="row items-center justify-between">
                <div>
                  <div class="text-caption text-weight-medium text-white">Respuestas Semi-Inmediatas con Buffer Humano</div>
                  <div class="text-caption text-grey-4" style="font-size: 0.72rem">
                    Responde automáticamente con datos de Notas & Base de Conocimiento tras un retardo natural, cancelándose si un asesor responde primero.
                  </div>
                </div>
                <q-toggle v-model="configForm.smart_reply_enabled" color="teal-4" dense />
              </div>

              <div v-if="configForm.smart_reply_enabled" class="q-mt-sm q-pl-md q-gutter-y-xs" style="border-left: 2px solid #0d9488">
                <div class="row justify-between text-caption text-grey-4">
                  <span>Buffer de retardo natural:</span>
                  <span class="text-weight-bold text-teal-3">{{ configForm.smart_reply_delay_seconds }} segundos</span>
                </div>
                <q-slider
                  v-model="configForm.smart_reply_delay_seconds"
                  :min="8"
                  :max="45"
                  :step="1"
                  color="teal-4"
                  dark
                />

                <div class="row justify-between text-caption text-grey-4 q-mt-xs">
                  <span>Umbral de certeza mínima para auto-enviar:</span>
                  <span class="text-weight-bold text-amber-4">{{ Math.round(configForm.smart_reply_confidence_threshold * 100) }}%</span>
                </div>
                <q-slider
                  v-model="configForm.smart_reply_confidence_threshold"
                  :min="0.70"
                  :max="0.95"
                  :step="0.05"
                  color="amber-4"
                  dark
                />
              </div>
            </div>

            <q-separator dark class="q-my-sm" style="border-color: rgba(255,255,255,0.06)" />

            <!-- Recomendador de Estados y Etiquetas -->
            <div class="row items-center justify-between q-py-xs">
              <div>
                <div class="text-caption text-weight-medium text-white">Recomendador de Estados y Etiquetas</div>
                <div class="text-caption text-grey-4" style="font-size: 0.72rem">
                  Sugiere etiquetas temáticas (#Medicina, #Cotización) y cambios de estado (ej: 'No interesado') con 1 clic.
                </div>
              </div>
              <q-toggle v-model="configForm.auto_tag_recommendation_enabled" color="teal-4" dense />
            </div>

            <q-separator dark class="q-my-sm" style="border-color: rgba(255,255,255,0.06)" />

            <!-- Extracción de Datos a la Ficha de Contacto -->
            <div class="row items-center justify-between q-py-xs">
              <div>
                <div class="text-caption text-weight-medium text-white">Extracción de Datos a la Ficha de Contacto</div>
                <div class="text-caption text-grey-4" style="font-size: 0.72rem">
                  Detecta CI, email, nombre y ciudad en la conversación y permite actualizar la ficha en 1 clic.
                </div>
              </div>
              <q-toggle v-model="configForm.entity_extraction_enabled" color="teal-4" dense />
            </div>

            <q-separator dark class="q-my-sm" style="border-color: rgba(255,255,255,0.06)" />

            <!-- Radar de Sentimiento y Alertas de Frustración -->
            <div class="row items-center justify-between q-py-xs">
              <div>
                <div class="text-caption text-weight-medium text-white">Radar de Sentimiento y Alertas de Frustración</div>
                <div class="text-caption text-grey-4" style="font-size: 0.72rem">
                  Monitorea en vivo el estado emocional y alerta de inmediato si un cliente expresa queja o molestia.
                </div>
              </div>
              <q-toggle v-model="configForm.sentiment_radar_enabled" color="teal-4" dense />
            </div>
          </div>

          <!-- Banner de Resultado de Prueba en Vivo -->
          <transition name="q-transition--fade">
            <div v-if="testResult" class="q-mt-sm">
              <q-banner
                rounded
                :class="testResult.status === 'success' ? 'bg-positive text-white' : 'bg-negative text-white'"
                class="q-pa-sm"
              >
                <template #avatar>
                  <q-icon :name="testResult.status === 'success' ? 'sym_r_check_circle' : 'sym_r_error'" size="22px" />
                </template>
                <div class="text-weight-bold text-caption">
                  {{ testResult.message }} ({{ testResult.latency_ms }}ms)
                </div>
                <div v-if="testResult.reply" class="text-caption text-italic q-mt-xs">
                  "{{ testResult.reply }}"
                </div>
              </q-banner>
            </div>
          </transition>
        </q-card-section>

        <q-card-actions align="between" class="q-px-md q-pb-md col-auto">
          <q-btn
            outline
            icon="sym_r_bolt"
            label="Probar Conexión en Vivo"
            color="amber-4"
            no-caps
            :loading="isTesting"
            @click="runConnectionTest"
          />

          <div class="row items-center q-gutter-x-sm">
            <q-btn flat label="Cancelar" color="grey-4" no-caps v-close-popup />
            <q-btn
              unelevated
              label="Guardar Configuración"
              color="primary"
              no-caps
              class="whaticket-btn-primary"
              :loading="isSaving"
              @click="submitAiConfig"
            />
          </div>
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Modal Nuevo Artículo -->
    <q-dialog v-model="isArticleModalOpen">
      <q-card style="width: 580px; max-width: 95vw; max-height: 90vh;" class="whaticket-modal-card q-pa-md column no-wrap">
        <q-card-section class="col-auto">
          <div class="text-h6 text-bold text-white">Nuevo Artículo de Conocimiento</div>
          <div class="text-caption text-grey-4">El contenido se indexará semánticamente en PostgreSQL con pgvector.</div>
        </q-card-section>

        <q-card-section class="q-gutter-y-md col scroll">
          <q-input v-model="newArticle.title" label="Título del Artículo / Tema" outlined dark dense />
          <q-input v-model="newArticle.content" label="Contenido detallado (FAQ, requisitos, productos, precios, políticas)" type="textarea" rows="5" outlined dark />
        </q-card-section>

        <q-card-actions align="right" class="col-auto">
          <q-btn flat label="Cancelar" color="grey-4" no-caps v-close-popup />
          <q-btn
            unelevated
            label="Guardar y Vectorizar"
            color="primary"
            no-caps
            class="whaticket-btn-primary"
            @click="saveArticle"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Modal Extracción Inteligente de Documentos con IA -->
    <q-dialog v-model="isExtractorModalOpen" persistent>
      <q-card style="width: 820px; max-width: 95vw; max-height: 90vh;" class="whaticket-modal-card q-pa-md column no-wrap">
        <q-card-section class="row items-center justify-between col-auto">
          <div>
            <div class="text-h6 text-bold text-white row items-center q-gutter-x-sm">
              <q-icon name="sym_r_auto_awesome" color="teal-4" size="24px" />
              <span>{{ extractionStep === 1 ? 'Importar Documento con IA' : `Revisar Artículos Detectados (${extractedArticles.length})` }}</span>
            </div>
            <div class="text-caption text-grey-4">
              {{
                extractionStep === 1
                  ? 'Sube un archivo Excel (.xlsx, .xls, .csv) o documento (.pdf, .docx, .txt, .md). La IA estructurará automáticamente catálogos, listas de precios y conocimientos.'
                  : `Archivo: ${extractedFilename} • Revisa, edita o elimina fragmentos antes de guardarlos y vectorizarlos.`
              }}
            </div>
          </div>
          <q-btn flat round dense icon="sym_r_close" color="grey-4" v-close-popup :disable="isExtracting || isSavingBatch" />
        </q-card-section>

        <!-- Paso 1: Carga de archivo o texto -->
        <q-card-section v-if="extractionStep === 1" class="q-gutter-y-md col scroll">
          <div class="q-pa-md rounded-borders" style="background: rgba(0, 168, 132, 0.05); border: 2px dashed rgba(0, 168, 132, 0.35);">
            <div class="text-subtitle2 text-bold text-teal-3 q-mb-sm row items-center q-gutter-x-xs">
              <q-icon name="sym_r_upload_file" size="20px" />
              <span>Opción 1: Subir Archivo Excel, Documento o Catálogo (.xlsx, .xls, .csv, .pdf, .docx, .txt, .md)</span>
            </div>
            <q-file
              v-model="documentFile"
              label="Seleccionar o soltar archivo (Excel, PDF, Word o Texto - máx. 15MB)"
              outlined
              dark
              dense
              clearable
              accept=".xlsx,.xls,.csv,.pdf,.docx,.doc,.txt,.md,.json"
              max-file-size="15728640"
            >
              <template #prepend>
                <q-icon name="sym_r_attach_file" color="teal-4" />
              </template>
            </q-file>
            <div v-if="documentFile" class="text-caption text-teal-2 q-mt-xs font-mono">
              Archivo seleccionado: {{ documentFile.name }} ({{ (documentFile.size / 1024).toFixed(1) }} KB)
            </div>
          </div>

          <div class="text-center text-caption text-grey-5 font-bold">— O TAMBIÉN PUEDES PEGAR TEXTO DIRECTAMENTE —</div>

          <div>
            <div class="text-subtitle2 text-bold text-grey-3 q-mb-xs">
              Opción 2: Pegar Texto o Contenido Extenso
            </div>
            <q-input
              v-model="documentRawText"
              label="Pega aquí el contenido (catálogos, listas de precios, políticas, notas, etc.)"
              type="textarea"
              rows="6"
              outlined
              dark
              dense
              hint="Mínimo 20 caracteres si no seleccionaste un archivo."
            />
          </div>

          <div class="q-pa-sm rounded-borders bg-dark text-grey-4 text-caption row items-start q-gutter-x-sm border-subtle">
            <q-icon name="sym_r_lightbulb" color="amber-4" size="18px" class="q-mt-xs" />
            <div>
              <strong>¿Cómo funciona con Excel y catálogos?</strong> La IA activa ({{ activeProviderLabel }}) interpreta las tablas, columnas de precios, códigos de producto y especificaciones de las hojas de cálculo (.xlsx, .xls, .csv). Agrupa y genera artículos limpios por producto o categoría para que el Copiloto pueda cotizar y responder con precisión en WhatsApp.
            </div>
          </div>
        </q-card-section>

        <!-- Paso 2: Vista previa y edición de artículos extraídos -->
        <q-card-section v-else class="q-gutter-y-md col scroll">
          <div class="row items-center justify-between">
            <span class="text-caption text-grey-4">
              Asegúrate de que la información sea correcta. Puedes modificar títulos, contenido o etiquetas libremente.
            </span>
            <q-btn
              flat
              dense
              no-caps
              icon="sym_r_add"
              color="teal-4"
              label="+ Agregar Fragmento Manual"
              @click="addManualExtractedArticle"
            />
          </div>

          <div v-if="extractedArticles.length === 0" class="text-center text-grey-5 q-py-lg">
            No hay artículos para guardar.
          </div>

          <div
            v-for="(art, idx) in extractedArticles"
            :key="idx"
            class="q-pa-md rounded-borders border-subtle q-mb-sm"
            style="background: #182229;"
          >
            <div class="row items-center justify-between q-mb-sm">
              <span class="text-caption text-bold text-teal-4">Artículo #{{ idx + 1 }}</span>
              <q-btn
                flat
                round
                dense
                size="sm"
                icon="sym_r_delete"
                color="negative"
                @click="removeExtractedArticle(idx)"
              >
                <q-tooltip>Eliminar este fragmento</q-tooltip>
              </q-btn>
            </div>

            <q-input
              v-model="art.title"
              label="Título del artículo"
              outlined
              dark
              dense
              class="q-mb-sm"
            />

            <q-input
              v-model="art.content"
              label="Contenido"
              type="textarea"
              rows="3"
              outlined
              dark
              dense
              class="q-mb-sm"
            />

            <q-select
              v-model="art.tags"
              label="Etiquetas (presiona Enter para agregar)"
              use-input
              use-chips
              multiple
              hide-dropdown-icon
              new-value-mode="add-unique"
              outlined
              dark
              dense
            />
          </div>
        </q-card-section>

        <!-- Acciones del Modal -->
        <q-card-actions align="between" class="q-px-md q-pb-md col-auto">
          <div>
            <q-btn
              v-if="extractionStep === 2"
              flat
              color="grey-4"
              icon="sym_r_arrow_back"
              label="Volver a subir"
              no-caps
              :disable="isSavingBatch"
              @click="extractionStep = 1"
            />
          </div>

          <div class="row items-center q-gutter-x-sm">
            <q-btn flat label="Cancelar" color="grey-4" no-caps v-close-popup :disable="isExtracting || isSavingBatch" />
            <q-btn
              v-if="extractionStep === 1"
              unelevated
              color="teal-5"
              icon="sym_r_auto_awesome"
              label="Analizar con IA"
              no-caps
              class="whaticket-btn-primary"
              :loading="isExtracting"
              :disable="!documentFile && !documentRawText.trim()"
              @click="analyzeDocumentWithAi"
            />
            <q-btn
              v-else
              unelevated
              color="primary"
              icon="sym_r_check_circle"
              :label="`Guardar y Vectorizar (${extractedArticles.length})`"
              no-caps
              class="whaticket-btn-primary"
              :loading="isSavingBatch"
              :disable="extractedArticles.length === 0"
              @click="saveExtractedBatch"
            />
          </div>
        </q-card-actions>
      </q-card>
    </q-dialog>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useQuasar } from 'quasar'
import { useAuthStore } from '@/modules/auth/stores/auth.store'
import { http } from '@/shared/api/http'
import {
  type AiConfigData,
  type AiTestResult,
  getAiConfig,
  resetAiBudget,
  testAiConnection,
  updateAiConfig,
} from '../api/ai-config.api'

const $q = useQuasar()
const route = useRoute()
const authStore = useAuthStore()
const isAdmin = computed(() => authStore.isAdmin || authStore.currentRole === 'admin' || authStore.currentRole === 'owner')

const activeTab = ref<'chat' | 'booking'>('chat')
const isConfigModalOpen = ref(false)
const isArticleModalOpen = ref(false)
const showApiKey = ref(false)
const isTesting = ref(false)
const isSaving = ref(false)
const isResettingBudget = ref(false)
const testResult = ref<AiTestResult | null>(null)

interface ExtractedArticle {
  title: string
  content: string
  tags: string[]
}

const isExtractorModalOpen = ref(false)
const extractionStep = ref<1 | 2>(1)
const documentFile = ref<File | null>(null)
const documentRawText = ref('')
const isExtracting = ref(false)
const isSavingBatch = ref(false)
const extractedFilename = ref('')
const extractedArticles = ref<ExtractedArticle[]>([])

const providerOptions = [
  { label: 'Bluesminds AI Gateway (Recomendado)', value: 'bluesminds' },
  { label: 'MiniMax', value: 'minimax' },
  { label: 'Google Gemini', value: 'gemini' },
  { label: 'OpenAI (ChatGPT)', value: 'openai' },
  { label: 'DeepSeek', value: 'deepseek' },
  { label: 'Personalizado (OpenAI-compatible)', value: 'custom' },
]

const providerModelPresets: Record<string, string[]> = {
  bluesminds: [
    'google/diffusiongemma-26b-a4b-it',
    'nvidia/nemotron-3-nano-omni-30b-a3b-reasoning',
    'nvidia/nemotron-3.5-lightning-30b-a3b',
    'meta/muse-glimmer-30b',
  ],
  minimax: ['MiniMax-Text-01', 'abab6.5s-chat', 'abab6.5-chat'],
  gemini: ['gemini-1.5-flash', 'gemini-2.0-flash', 'gemini-1.5-pro'],
  openai: ['gpt-4o-mini', 'gpt-4o', 'gpt-3.5-turbo'],
  deepseek: ['deepseek-chat', 'deepseek-coder'],
  custom: ['custom-model'],
}

const defaultBaseUrls: Record<string, string> = {
  bluesminds: 'https://api.bluesminds.com/v1',
  minimax: 'https://api.minimax.io/v1',
  gemini: 'https://generativelanguage.googleapis.com/v1beta',
  openai: 'https://api.openai.com/v1',
  deepseek: 'https://api.deepseek.com/v1',
  custom: '',
}

const aiConfig = ref<AiConfigData>({
  provider: 'minimax',
  model: 'MiniMax-Text-01',
  base_url: 'https://api.minimax.io/v1',
  has_api_key: false,
  api_key_masked: '',
  system_prompt: 'Eres el Copiloto de Inteligencia Artificial para UNITEPC.',
  similarity_threshold: 0.75,
  budget_enabled: false,
  spending_limit_usd: 0.20,
  current_spending_usd: 0.0,
  total_tokens_used: 0,
  total_requests_count: 0,
  is_budget_exceeded: false,
})

const configForm = reactive({
  provider: 'minimax',
  model: 'MiniMax-Text-01',
  base_url: 'https://api.minimax.io/v1',
  api_key: '',
  system_prompt: '',
  similarity_threshold: 0.75,
  budget_enabled: false,
  spending_limit_usd: 0.20,
  smart_reply_enabled: false,
  smart_reply_delay_seconds: 15,
  smart_reply_confidence_threshold: 0.85,
  auto_tag_recommendation_enabled: true,
  entity_extraction_enabled: true,
  sentiment_radar_enabled: true,
})

const currentModelOptions = computed(() => {
  return providerModelPresets[configForm.provider] || ['default']
})

const activeProviderLabel = computed(() => {
  const opt = providerOptions.find((p) => p.value === aiConfig.value.provider)
  return opt ? opt.label : aiConfig.value.provider.toUpperCase()
})

interface ArticleItem {
  id: string
  title: string
  content: string
  chunks_count?: number
  user_id?: string
  user?: {
    id: string
    name: string
    email: string
  }
  metadata?: {
    tags?: string[]
    source_file?: string
    imported_at?: string
    [key: string]: any
  }
  created_at?: string
}

const articles = ref<ArticleItem[]>([
  {
    id: '1',
    title: 'Catálogo de Productos y Servicios Principales',
    content: 'Contamos con un portafolio integral de soluciones corporativas, software cloud y servicios de implementación con cobertura y soporte a nivel nacional.',
    chunks_count: 3,
  },
  {
    id: '2',
    title: 'Políticas Comerciales, Métodos de Pago y Garantía',
    content: 'Aceptamos transferencias bancarias, códigos QR y pagos con tarjeta. Ofrecemos facturación formal, planes a medida y garantía de satisfacción.',
    chunks_count: 2,
  },
])

const newArticle = reactive({
  title: '',
  content: '',
})

onMounted(async () => {
  await Promise.all([loadAiConfig(), loadArticles()])
  if (route.path.includes('config') || route.query.config === 'true') {
    openConfigModal()
  }
})

async function loadAiConfig() {
  try {
    const data = await getAiConfig()
    aiConfig.value = data
  } catch (err: any) {
    console.warn('Error cargando configuración de IA:', err)
  }
}

function openConfigModal() {
  configForm.provider = aiConfig.value.provider || 'minimax'
  configForm.model = aiConfig.value.model || 'MiniMax-Text-01'
  configForm.base_url = aiConfig.value.base_url || defaultBaseUrls[configForm.provider] || ''
  configForm.api_key = ''
  configForm.system_prompt = aiConfig.value.system_prompt
  configForm.similarity_threshold = aiConfig.value.similarity_threshold || 0.75
  configForm.budget_enabled = aiConfig.value.budget_enabled ?? false
  configForm.spending_limit_usd = aiConfig.value.spending_limit_usd ?? 0.20
  configForm.smart_reply_enabled = aiConfig.value.smart_reply_enabled ?? false
  configForm.smart_reply_delay_seconds = aiConfig.value.smart_reply_delay_seconds ?? 15
  configForm.smart_reply_confidence_threshold = aiConfig.value.smart_reply_confidence_threshold ?? 0.85
  configForm.auto_tag_recommendation_enabled = aiConfig.value.auto_tag_recommendation_enabled ?? true
  configForm.entity_extraction_enabled = aiConfig.value.entity_extraction_enabled ?? true
  configForm.sentiment_radar_enabled = aiConfig.value.sentiment_radar_enabled ?? true
  testResult.value = null
  isConfigModalOpen.value = true
}

function onProviderChange(prov: string) {
  configForm.base_url = defaultBaseUrls[prov] || ''
  const models = providerModelPresets[prov]
  if (models && models.length > 0) {
    configForm.model = models[0]
  }
}

async function handleResetBudget() {
  isResettingBudget.value = true
  try {
    await resetAiBudget()
    await loadAiConfig()
    $q.notify({
      type: 'positive',
      message: 'Contador de consumo reiniciado a $0.00 USD.',
      position: 'bottom-right',
    })
  } catch (err: any) {
    $q.notify({
      type: 'negative',
      message: err?.response?.data?.message || 'Error al reiniciar contador de consumo.',
      position: 'bottom-right',
    })
  } finally {
    isResettingBudget.value = false
  }
}

async function runConnectionTest() {
  isTesting.value = true
  testResult.value = null

  try {
    const res = await testAiConnection({
      provider: configForm.provider,
      model: configForm.model,
      api_key: configForm.api_key,
      base_url: configForm.base_url,
    })
    testResult.value = res
    if (res?.status === 'success') {
      await loadAiConfig()
    }
  } catch (err: any) {
    testResult.value = {
      status: 'error',
      message: err?.response?.data?.message || err?.message || 'Error de conexión con el proveedor.',
      latency_ms: 0,
    }
  } finally {
    isTesting.value = false
  }
}

async function submitAiConfig() {
  isSaving.value = true
  try {
    const updated = await updateAiConfig({
      provider: configForm.provider as any,
      model: configForm.model,
      base_url: configForm.base_url,
      api_key: configForm.api_key || undefined,
      system_prompt: configForm.system_prompt,
      similarity_threshold: configForm.similarity_threshold,
      budget_enabled: configForm.budget_enabled,
      spending_limit_usd: configForm.spending_limit_usd,
      smart_reply_enabled: configForm.smart_reply_enabled,
      smart_reply_delay_seconds: configForm.smart_reply_delay_seconds,
      smart_reply_confidence_threshold: configForm.smart_reply_confidence_threshold,
      auto_tag_recommendation_enabled: configForm.auto_tag_recommendation_enabled,
      entity_extraction_enabled: configForm.entity_extraction_enabled,
      sentiment_radar_enabled: configForm.sentiment_radar_enabled,
    })

    aiConfig.value = updated
    isConfigModalOpen.value = false

    $q.notify({
      type: 'positive',
      message: 'Configuración de Inteligencia Artificial guardada con éxito.',
      position: 'bottom-right',
    })
  } catch (err: any) {
    $q.notify({
      type: 'negative',
      message: err?.response?.data?.message || 'Error al guardar la configuración.',
      position: 'bottom-right',
    })
  } finally {
    isSaving.value = false
  }
}

async function loadArticles() {
  try {
    const res = await http.get('/ai/knowledge-chunks')
    if (res.data?.data && Array.isArray(res.data.data)) {
      articles.value = res.data.data
    }
  } catch (err) {
    console.warn('Error cargando fragmentos de conocimiento:', err)
  }
}

async function saveArticle() {
  if (!newArticle.title.trim() || !newArticle.content.trim()) return

  try {
    const res = await http.post('/ai/knowledge-chunks', {
      title: newArticle.title.trim(),
      content: newArticle.content.trim(),
    })
    const saved = res.data?.data
    if (saved) {
      articles.value.unshift(saved)
    } else {
      articles.value.unshift({
        id: Date.now().toString(),
        title: newArticle.title,
        content: newArticle.content,
        user: { id: authStore.user?.id || '', name: authStore.user?.name || 'Usuario', email: authStore.user?.email || '' },
        created_at: new Date().toISOString(),
      })
    }

    isArticleModalOpen.value = false
    newArticle.title = ''
    newArticle.content = ''

    $q.notify({
      type: 'positive',
      message: 'Artículo registrado y vectorizado en la base de conocimiento.',
      position: 'bottom-right',
    })
  } catch (err: any) {
    $q.notify({
      type: 'negative',
      message: err?.response?.data?.message || 'Error al guardar el artículo.',
      position: 'bottom-right',
    })
  }
}

async function deleteArticle(id: string) {
  try {
    await http.delete(`/ai/knowledge-chunks/${id}`)
    articles.value = articles.value.filter((a) => a.id !== id)
    $q.notify({
      type: 'warning',
      message: 'Artículo eliminado de la base de conocimiento.',
      position: 'bottom-right',
    })
  } catch (err: any) {
    $q.notify({
      type: 'negative',
      message: err?.response?.data?.message || 'Error al eliminar artículo.',
      position: 'bottom-right',
    })
  }
}

function openExtractorModal() {
  extractionStep.value = 1
  documentFile.value = null
  documentRawText.value = ''
  extractedArticles.value = []
  extractedFilename.value = ''
  isExtractorModalOpen.value = true
}

function removeExtractedArticle(index: number) {
  extractedArticles.value.splice(index, 1)
}

function addManualExtractedArticle() {
  extractedArticles.value.push({
    title: 'Nuevo artículo complementario',
    content: '',
    tags: ['general'],
  })
}

async function analyzeDocumentWithAi() {
  if (!documentFile.value && !documentRawText.value.trim()) {
    $q.notify({
      type: 'warning',
      message: 'Selecciona un archivo (.xlsx, .xls, .csv, .pdf, .docx, .txt, .md) o pega texto para analizar.',
      position: 'bottom-right',
    })
    return
  }

  isExtracting.value = true
  try {
    const formData = new FormData()
    if (documentFile.value) {
      formData.append('file', documentFile.value)
    }
    if (documentRawText.value.trim()) {
      formData.append('raw_text', documentRawText.value.trim())
    }

    const res = await http.post('/ai/knowledge/extract-document', formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    })

    const data = res.data?.data
    if (data && Array.isArray(data.articles)) {
      extractedArticles.value = data.articles.map((a: any) => ({
        title: a.title || 'Sin título',
        content: a.content || '',
        tags: Array.isArray(a.tags) ? a.tags : ['documento'],
      }))
      extractedFilename.value = data.filename || (documentFile.value ? documentFile.value.name : 'Texto importado')
      extractionStep.value = 2
      $q.notify({
        type: 'positive',
        message: res.data?.message || `Se extrajeron ${extractedArticles.value.length} artículos exitosamente.`,
        position: 'bottom-right',
      })
    } else {
      throw new Error('La IA no devolvió artículos utilizables a partir del documento.')
    }
  } catch (err: any) {
    $q.notify({
      type: 'negative',
      message: err?.response?.data?.message || err?.message || 'Error al analizar el documento con IA.',
      position: 'bottom-right',
    })
  } finally {
    isExtracting.value = false
  }
}

async function saveExtractedBatch() {
  const validArticles = extractedArticles.value.filter(
    (a) => a.title.trim() && a.content.trim()
  )

  if (validArticles.length === 0) {
    $q.notify({
      type: 'warning',
      message: 'Todos los artículos deben tener al menos un título y contenido.',
      position: 'bottom-right',
    })
    return
  }

  isSavingBatch.value = true
  try {
    const res = await http.post('/ai/knowledge-chunks/batch', {
      articles: validArticles,
      filename: extractedFilename.value || (documentFile.value ? documentFile.value.name : 'importacion_ia'),
    })

    const created = res.data?.data
    if (created && Array.isArray(created)) {
      articles.value.unshift(...created)
    } else {
      await loadArticles()
    }

    isExtractorModalOpen.value = false
    $q.notify({
      type: 'positive',
      message: res.data?.message || `${validArticles.length} artículos indexados y vectorizados en la base de datos.`,
      position: 'bottom-right',
    })
  } catch (err: any) {
    $q.notify({
      type: 'negative',
      message: err?.response?.data?.message || err?.message || 'Error al guardar fragmentos.',
      position: 'bottom-right',
    })
  } finally {
    isSavingBatch.value = false
  }
}

function canDeleteArticle(art: ArticleItem): boolean {
  if (isAdmin.value) return true
  const uid = authStore.user?.id
  return Boolean(uid && (art.user_id === uid || art.user?.id === uid))
}

function getInitials(name?: string | null): string {
  if (!name) return 'U'
  const parts = name.trim().split(/\s+/)
  if (parts.length === 1) return parts[0].substring(0, 2).toUpperCase()
  return (parts[0][0] + parts[1][0]).toUpperCase()
}

function formatDate(iso?: string): string {
  if (!iso) return ''
  try {
    const d = new Date(iso)
    return d.toLocaleDateString('es-ES', { day: '2-digit', month: 'short' })
  } catch {
    return ''
  }
}
</script>

<style scoped lang="scss">
.whaticket-wabot-page {
  padding: 24px 32px;
  background-color: var(--crm-bg-app);
  min-height: calc(100vh - 52px);
}

.border-subtle {
  border: 1px solid rgba(255, 255, 255, 0.08);
}

.whaticket-btn-primary {
  background-color: #00a884 !important;
  color: #ffffff !important;
  font-weight: 600;
  border-radius: 8px;
  height: 36px;
}

.whaticket-btn-outline {
  border: 1px solid var(--crm-color-border);
  color: #ffffff;
  border-radius: 8px;
  height: 36px;
}

.whaticket-subnav-tabs {
  display: flex;
  gap: 8px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
  padding-bottom: 8px;
  overflow-x: auto;
  white-space: nowrap;
  -webkit-overflow-scrolling: touch;

  &::-webkit-scrollbar {
    height: 4px;
  }
}

.whaticket-subnav-btn {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-shrink: 0;
  background: transparent;
  border: none;
  color: #8696a0;
  padding: 8px 16px;
  border-radius: 8px;
  font-size: 0.88rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.15s ease;

  &:hover {
    background: rgba(255, 255, 255, 0.04);
    color: #e9edef;
  }

  &--active {
    background: #182229;
    color: #ffffff;
    font-weight: 600;
  }
}

.whaticket-ai-status-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 70px 24px;
  background-color: #182229;
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 14px;
  text-align: center;
}

.whaticket-ai-icon-box {
  width: 72px;
  height: 72px;
  border-radius: 50%;
  background: rgba(245, 158, 11, 0.12);
  display: flex;
  align-items: center;
  justify-content: center;
}

.whaticket-article-card {
  background-color: #182229 !important;
  border: 1px solid rgba(255, 255, 255, 0.08) !important;
  border-radius: 12px !important;
}

.whaticket-modal-card {
  background-color: #111b21 !important;
  border: 1px solid rgba(255, 255, 255, 0.1) !important;
  border-radius: 16px !important;
}
</style>
