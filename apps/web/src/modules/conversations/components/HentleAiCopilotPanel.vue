<template>
  <div class="hentle-ai-copilot">
    <div class="hentle-ai-copilot__header">
      <div class="row items-center q-gutter-x-sm">
        <q-icon name="sym_r_auto_awesome" color="amber" size="20px" />
        <span class="text-subtitle1 text-bold text-white">Copiloto Hentle-AI</span>
      </div>
      <q-btn flat round dense icon="sym_r_close" color="grey" @click="emit('close')" />
    </div>

    <div class="hentle-ai-copilot__body q-pa-md q-gutter-y-md">
      <!-- Sección: Sugerencia Rápida -->
      <q-card flat bordered class="hentle-ai-card">
        <q-card-section>
          <div class="row justify-between items-center q-mb-sm">
            <span class="text-caption text-bold text-amber">Sugerencia Inteligente</span>
            <q-btn
              flat
              dense
              size="sm"
              color="primary"
              icon="sym_r_refresh"
              label="Regenerar"
              :loading="loadingSuggestion"
              @click="fetchSuggestion"
            />
          </div>

          <div v-if="suggestion" class="hentle-ai-suggestion-text">
            {{ suggestion }}
          </div>
          <div v-else class="text-caption text-grey">
            Haz clic en generar para obtener una sugerencia automática basada en la conversación.
          </div>
        </q-card-section>

        <q-card-actions v-if="suggestion" align="right">
          <q-btn
            unelevated
            size="sm"
            color="positive"
            icon="sym_r_done"
            label="Usar en el Chat"
            @click="emit('use-suggestion', suggestion)"
          />
        </q-card-actions>
      </q-card>

      <!-- Sección: Resumen del Ticket -->
      <q-card flat bordered class="hentle-ai-card">
        <q-card-section>
          <div class="row justify-between items-center q-mb-sm">
            <span class="text-caption text-bold text-cyan">Resumen del Ticket</span>
            <q-btn
              flat
              dense
              size="sm"
              color="cyan"
              icon="sym_r_summarize"
              label="Resumir"
              :loading="loadingSummary"
              @click="fetchSummary"
            />
          </div>

          <div v-if="summary" class="hentle-ai-summary-text">
            <p class="q-mb-xs text-body2">{{ summary.summary }}</p>
            <ul class="q-pl-md q-my-xs text-caption text-grey-4">
              <li v-for="(point, idx) in summary.key_points" :key="idx">{{ point }}</li>
            </ul>
          </div>
          <div v-else class="text-caption text-grey">
            Genera un resumen conciso para transferir el ticket o consultar antecedentes.
          </div>
        </q-card-section>
      </q-card>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { getHentleAiSuggestion, getHentleAiSummary } from '../api/conversations.api'

const props = defineProps<{
  conversationId: string
}>()

const emit = defineEmits<{
  close: []
  'use-suggestion': [text: string]
}>()

const suggestion = ref<string | null>(null)
const summary = ref<{ summary: string; key_points: string[] } | null>(null)
const loadingSuggestion = ref(false)
const loadingSummary = ref(false)

async function fetchSuggestion() {
  if (!props.conversationId) return
  try {
    loadingSuggestion.value = true
    const res = await getHentleAiSuggestion(props.conversationId)
    suggestion.value = res.suggestion
  } finally {
    loadingSuggestion.value = false
  }
}

async function fetchSummary() {
  if (!props.conversationId) return
  try {
    loadingSummary.value = true
    const res = await getHentleAiSummary(props.conversationId)
    summary.value = res
  } finally {
    loadingSummary.value = false
  }
}
</script>

<style scoped lang="scss">
.hentle-ai-copilot {
  display: flex;
  flex-direction: column;
  height: 100%;
  border-left: 1px solid rgba(118, 198, 255, 0.12);
  background: rgba(11, 22, 35, 0.98);
}

.hentle-ai-copilot__header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 14px 18px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

.hentle-ai-card {
  border-radius: 14px;
  background: rgba(18, 32, 50, 0.9);
  border: 1px solid rgba(118, 198, 255, 0.1);
}

.hentle-ai-suggestion-text {
  font-size: 0.88rem;
  line-height: 1.45;
  color: #f1f5f9;
  background: rgba(0, 0, 0, 0.25);
  padding: 10px;
  border-radius: 8px;
  border-left: 3px solid #f59e0b;
}

.hentle-ai-summary-text {
  color: #e2e8f0;
}
</style>
