<template>
  <div class="whaticket-wabot-page">
    <!-- Header -->
    <div class="row items-center justify-between q-mb-md">
      <div>
        <div class="row items-center q-gutter-x-xs">
          <h1 class="text-h5 text-bold text-white q-my-none">Base de Conocimiento</h1>
          <q-icon name="sym_r_info" size="18px" color="grey-5" />
        </div>
        <p class="text-caption text-grey-4 q-mt-xs q-mb-none">
          Artículos que Wäbot (Hentle-AI) consulta para responder a tus clientes con búsqueda semántica (pgvector).
        </p>
      </div>

      <div class="row items-center q-gutter-sm">
        <q-btn
          outline
          icon="sym_r_settings"
          label="Configurar IA"
          color="primary"
          no-caps
          class="whaticket-btn-outline"
          @click="isConfigModalOpen = true"
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
        <span>Atención por chat</span>
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

    <!-- Panel de Estado / Base de Conocimiento (Exacto a la Captura 4 de Whaticket) -->
    <div v-if="articles.length === 0" class="whaticket-ai-status-card">
      <div class="whaticket-ai-icon-box">
        <q-icon name="sym_r_auto_awesome" size="36px" color="amber-4" />
      </div>
      <div class="text-h6 text-bold text-white q-mt-md">
        La IA aún no está configurada.
      </div>
      <div class="text-caption text-grey-4 q-mt-xs q-mb-lg" style="max-width: 500px">
        Configúrala ahora para empezar a usarla en tus respuestas rápidas y flujos del chatbot con embeddings de pgvector.
      </div>
      <q-btn
        color="primary"
        icon="sym_r_settings"
        label="Configurar IA"
        unelevated
        no-caps
        class="whaticket-btn-primary"
        @click="isConfigModalOpen = true"
      />
    </div>

    <!-- Listado de Artículos Vectorizados cuando sí hay datos -->
    <div v-else class="row q-col-gutter-md">
      <div v-for="art in articles" :key="art.id" class="col-12 col-md-6">
        <q-card flat bordered class="whaticket-article-card">
          <q-card-section>
            <div class="row items-center justify-between">
              <div class="text-bold text-white text-subtitle1">{{ art.title }}</div>
              <q-badge color="positive" class="text-bold">Vectorizado (1536d)</q-badge>
            </div>
            <p class="text-caption text-grey-4 q-mt-sm">{{ art.content }}</p>
            <div class="row items-center justify-between text-caption text-grey-5 q-mt-md">
              <span>Chunks: {{ art.chunks_count }} fragmentos</span>
              <q-btn flat round dense icon="sym_r_delete" color="negative" size="sm" @click="deleteArticle(art.id)" />
            </div>
          </q-card-section>
        </q-card>
      </div>
    </div>

    <!-- Modal Configuración de IA -->
    <q-dialog v-model="isConfigModalOpen">
      <q-card style="width: 620px; max-width: 95vw" class="whaticket-modal-card q-pa-md">
        <q-card-section class="row items-center justify-between">
          <div class="text-h6 text-bold text-white">Configuración del Agente IA (Hentle)</div>
          <q-btn flat round dense icon="sym_r_close" color="grey-4" v-close-popup />
        </q-card-section>

        <q-card-section class="q-gutter-y-md">
          <q-select
            v-model="aiConfig.model"
            :options="['gpt-4o-mini', 'gpt-4o', 'deepseek-chat', 'claude-3-5-sonnet']"
            label="Modelo de Lenguaje (LLM)"
            outlined
            dark
            dense
          />

          <q-input
            v-model="aiConfig.apiKey"
            label="API Key (OpenAI / Hentle-AI)"
            type="password"
            outlined
            dark
            dense
          />

          <q-input
            v-model="aiConfig.systemPrompt"
            label="System Prompt (Instrucciones del Agente)"
            type="textarea"
            rows="4"
            outlined
            dark
          />

          <div class="q-px-xs">
            <div class="row justify-between text-caption text-grey-4 q-mb-xs">
              <span>Umbral de Similitud Vectorial:</span>
              <span class="text-bold text-primary">{{ aiConfig.similarityThreshold }}</span>
            </div>
            <q-slider
              v-model="aiConfig.similarityThreshold"
              :min="0.5"
              :max="0.95"
              :step="0.05"
              color="primary"
              dark
            />
          </div>
        </q-card-section>

        <q-card-actions align="right">
          <q-btn flat label="Cancelar" color="grey-4" no-caps v-close-popup />
          <q-btn
            unelevated
            label="Guardar Configuración"
            color="primary"
            no-caps
            class="whaticket-btn-primary"
            @click="saveAiConfig"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Modal Nuevo Artículo -->
    <q-dialog v-model="isArticleModalOpen">
      <q-card style="width: 580px; max-width: 95vw" class="whaticket-modal-card q-pa-md">
        <q-card-section>
          <div class="text-h6 text-bold text-white">Nuevo Artículo de Conocimiento</div>
          <div class="text-caption text-grey-4">El texto se dividirá en fragmentos y se generarán embeddings semánticos.</div>
        </q-card-section>

        <q-card-section class="q-gutter-y-md">
          <q-input v-model="newArticle.title" label="Título del Artículo / Tema" outlined dark dense />
          <q-input v-model="newArticle.content" label="Contenido detallado (FAQ, precios, políticas)" type="textarea" rows="5" outlined dark />
        </q-card-section>

        <q-card-actions align="right">
          <q-btn flat label="Cancelar" color="grey-4" no-caps v-close-popup />
          <q-btn
            unelevated
            label="Vectorizar y Guardar"
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
import { reactive, ref } from 'vue'
import { useAppNotify } from '@/shared/composables/useAppNotify'

const notify = useAppNotify()

const activeTab = ref<'chat' | 'booking'>('chat')
const isConfigModalOpen = ref(false)
const isArticleModalOpen = ref(false)

const aiConfig = reactive({
  model: 'gpt-4o-mini',
  apiKey: '••••••••••••••••••••••••••••••••',
  systemPrompt: 'Eres el copiloto y asistente oficial de atención de UNITEPC. Responde cordialmente, con información verídica de la base de conocimiento y ofrece agendar citas con asesores.',
  similarityThreshold: 0.75,
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

function saveAiConfig() {
  isConfigModalOpen.value = false
  notify.success({ message: 'Configuración del Agente IA actualizada exitosamente.' })
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
  notify.success({ message: 'Artículo vectorizado y agregado a la base de conocimiento.' })
}

function deleteArticle(id: string) {
  articles.value = articles.value.filter((a) => a.id !== id)
  notify.warning({ message: 'Artículo eliminado.' })
}
</script>

<style scoped lang="scss">
.whaticket-wabot-page {
  padding: 24px 32px;
  background-color: var(--crm-bg-app);
  min-height: calc(100vh - 52px);
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
