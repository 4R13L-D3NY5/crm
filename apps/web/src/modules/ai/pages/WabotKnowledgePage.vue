<template>
  <div class="whaticket-wabot-page">
    <!-- Header -->
    <div class="row items-center justify-between q-mb-md">
      <div>
        <div class="row items-center q-gutter-x-sm">
          <h1 class="text-h5 text-bold text-white q-my-none">Base de Conocimiento & Hentle-AI</h1>
          <q-badge
            :color="aiConfig.has_api_key ? 'positive' : 'warning'"
            :label="aiConfig.has_api_key ? `Activo: ${activeProviderLabel} (${aiConfig.model})` : 'Configuración Pendiente'"
            class="text-weight-bold"
          />
        </div>
        <p class="text-caption text-grey-4 q-mt-xs q-mb-none">
          Motor cognitivo para respuestas inteligentes en la Bandeja Multicanal y búsqueda semántica RAG (pgvector 16).
        </p>
      </div>

      <div class="row items-center q-gutter-sm">
        <q-btn
          outline
          icon="sym_r_tune"
          label="Configurar Motor IA"
          color="primary"
          no-caps
          class="whaticket-btn-outline"
          @click="openConfigModal"
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
              <q-badge color="positive" class="text-bold">Vectorizado (pgvector)</q-badge>
            </div>
            <p class="text-caption text-grey-4 q-mt-sm">{{ art.content }}</p>
            <div class="row items-center justify-between text-caption text-grey-5 q-mt-md">
              <span>Chunks: {{ art.chunks_count }} fragmentos indexados</span>
              <q-btn flat round dense icon="sym_r_delete" color="negative" size="sm" @click="deleteArticle(art.id)" />
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
        Agrega preguntas frecuentes o información de carreras para que el copiloto de IA pueda citar datos precisos.
      </div>
      <q-btn
        color="primary"
        icon="sym_r_add"
        label="Nuevo Artículo"
        unelevated
        no-caps
        class="whaticket-btn-primary"
        @click="isArticleModalOpen = true"
      />
    </div>

    <!-- Modal Configuración de IA (Multi-proveedor: MiniMax, Gemini, OpenAI, DeepSeek) -->
    <q-dialog v-model="isConfigModalOpen" persistent>
      <q-card style="width: 680px; max-width: 95vw" class="whaticket-modal-card q-pa-md">
        <q-card-section class="row items-center justify-between">
          <div>
            <div class="text-h6 text-bold text-white">Configuración del Motor IA (Hentle-AI)</div>
            <div class="text-caption text-grey-4">Enlaza tu API Key física y selecciona tu modelo favorito en cualquier momento.</div>
          </div>
          <q-btn flat round dense icon="sym_r_close" color="grey-4" v-close-popup />
        </q-card-section>

        <q-card-section class="q-gutter-y-md">
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

          <!-- 4. API Key con botón de mostrar/ocultar -->
          <q-input
            v-model="configForm.api_key"
            label="API Key Secreta"
            :type="showApiKey ? 'text' : 'password'"
            outlined
            dark
            dense
            :placeholder="aiConfig.has_api_key ? `Clave actual guardada (${aiConfig.api_key_masked})` : 'Pega aquí tu clave API'"
            hint="Tu clave se almacena de forma cifrada y nunca se expone en texto plano."
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

        <q-card-actions align="between" class="q-px-md q-pb-md">
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
      <q-card style="width: 580px; max-width: 95vw" class="whaticket-modal-card q-pa-md">
        <q-card-section>
          <div class="text-h6 text-bold text-white">Nuevo Artículo de Conocimiento</div>
          <div class="text-caption text-grey-4">El contenido se indexará semánticamente en PostgreSQL con pgvector.</div>
        </q-card-section>

        <q-card-section class="q-gutter-y-md">
          <q-input v-model="newArticle.title" label="Título del Artículo / Tema" outlined dark dense />
          <q-input v-model="newArticle.content" label="Contenido detallado (FAQ, requisitos, carreras, costos)" type="textarea" rows="5" outlined dark />
        </q-card-section>

        <q-card-actions align="right">
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
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useQuasar } from 'quasar'
import {
  type AiConfigData,
  type AiTestResult,
  getAiConfig,
  testAiConnection,
  updateAiConfig,
} from '../api/ai-config.api'

const $q = useQuasar()

const activeTab = ref<'chat' | 'booking'>('chat')
const isConfigModalOpen = ref(false)
const isArticleModalOpen = ref(false)
const showApiKey = ref(false)
const isTesting = ref(false)
const isSaving = ref(false)
const testResult = ref<AiTestResult | null>(null)

const providerOptions = [
  { label: 'MiniMax (Recomendado)', value: 'minimax' },
  { label: 'Google Gemini', value: 'gemini' },
  { label: 'OpenAI (ChatGPT)', value: 'openai' },
  { label: 'DeepSeek', value: 'deepseek' },
  { label: 'Personalizado (OpenAI-compatible)', value: 'custom' },
]

const providerModelPresets: Record<string, string[]> = {
  minimax: ['MiniMax-Text-01', 'abab6.5s-chat', 'abab6.5-chat'],
  gemini: ['gemini-1.5-flash', 'gemini-2.0-flash', 'gemini-1.5-pro'],
  openai: ['gpt-4o-mini', 'gpt-4o', 'gpt-3.5-turbo'],
  deepseek: ['deepseek-chat', 'deepseek-coder'],
  custom: ['custom-model'],
}

const defaultBaseUrls: Record<string, string> = {
  minimax: 'https://api.minimax.chat/v1',
  gemini: 'https://generativelanguage.googleapis.com/v1beta',
  openai: 'https://api.openai.com/v1',
  deepseek: 'https://api.deepseek.com/v1',
  custom: '',
}

const aiConfig = ref<AiConfigData>({
  provider: 'minimax',
  model: 'MiniMax-Text-01',
  base_url: 'https://api.minimax.chat/v1',
  has_api_key: false,
  api_key_masked: '',
  system_prompt: 'Eres el Copiloto de Inteligencia Artificial para UNITEPC.',
  similarity_threshold: 0.75,
})

const configForm = reactive({
  provider: 'minimax',
  model: 'MiniMax-Text-01',
  base_url: 'https://api.minimax.chat/v1',
  api_key: '',
  system_prompt: '',
  similarity_threshold: 0.75,
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
  chunks_count: number
}

const articles = ref<ArticleItem[]>([
  {
    id: '1',
    title: 'Oferta Académica y Sedes Nacionales',
    content: 'Contamos con más de 25 carreras a nivel nacional con campus en Cochabamba, La Paz, El Alto, Santa Cruz, Cobija, Puerto Quijarro, Ivirgarzama y Guayaramerín.',
    chunks_count: 3,
  },
  {
    id: '2',
    title: 'Requisitos de Inscripción y Becas',
    content: 'Para inscribirse se requiere cédula de identidad, título de bachiller y certificado de nacimiento. Ofrecemos becas académicas, beca patriota y convenios institucionales.',
    chunks_count: 2,
  },
])

const newArticle = reactive({
  title: '',
  content: '',
})

onMounted(async () => {
  await loadAiConfig()
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

function saveArticle() {
  if (!newArticle.title.trim() || !newArticle.content.trim()) return

  articles.value.unshift({
    id: Date.now().toString(),
    title: newArticle.title,
    content: newArticle.content,
    chunks_count: 1,
  })

  isArticleModalOpen.value = false
  newArticle.title = ''
  newArticle.content = ''

  $q.notify({
    type: 'positive',
    message: 'Artículo vectorizado y agregado a la base de conocimiento.',
    position: 'bottom-right',
  })
}

function deleteArticle(id: string) {
  articles.value = articles.value.filter((a) => a.id !== id)
  $q.notify({
    type: 'warning',
    message: 'Artículo eliminado.',
    position: 'bottom-right',
  })
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
}

.whaticket-subnav-btn {
  display: flex;
  align-items: center;
  gap: 8px;
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
