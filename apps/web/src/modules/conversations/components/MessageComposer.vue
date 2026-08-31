<template>
  <div class="message-composer" :class="{ 'message-composer--internal': activeMode === 'internal' }">
    <!-- Header del Composer: selector de modo y atajos -->
    <div class="message-composer__header">
      <q-btn-toggle
        v-model="activeMode"
        toggle-color="primary"
        dense
        rounded
        unelevated
        :options="[
          { label: 'WhatsApp', value: 'whatsapp', icon: 'sym_r_chat' },
          { label: 'Nota Interna', value: 'internal', icon: 'sym_r_lock' },
        ]"
      />

      <div class="message-composer__shortcuts-hint">
        <q-btn
          flat
          dense
          size="sm"
          color="grey-4"
          icon="sym_r_bolt"
          label="Respuestas Rápidas (/)"
          @click="showQuickMessages = true"
        />
      </div>
    </div>

    <!-- Área de Entrada de Texto -->
    <div class="message-composer__input-row">
      <q-input
        v-model="body"
        type="textarea"
        autogrow
        outlined
        dense
        :rows="1"
        :placeholder="activeMode === 'whatsapp' ? 'Escribe un mensaje o escribe / para atajos...' : 'Escribe una nota interna para el equipo...'"
        class="message-composer__input"
        @keydown.enter.exact.prevent="submit"
        @update:model-value="onInput"
      >
        <template #after>
          <q-btn
            round
            unelevated
            :color="activeMode === 'whatsapp' ? 'positive' : 'amber-8'"
            :icon="activeMode === 'whatsapp' ? 'sym_r_send' : 'sym_r_note_add'"
            :loading="loading"
            :disable="!body.trim()"
            @click="submit"
          >
            <q-tooltip>{{ activeMode === 'whatsapp' ? 'Enviar (Enter)' : 'Guardar Nota (Enter)' }}</q-tooltip>
          </q-btn>
        </template>
      </q-input>
    </div>

    <!-- Diálogo / Popover de Respuestas Rápidas -->
    <q-dialog v-model="showQuickMessages">
      <q-card style="min-width: 400px">
        <q-card-section class="q-pb-none">
          <div class="text-h6">Respuestas Rápidas</div>
          <q-input
            v-model="quickSearch"
            dense
            outlined
            placeholder="Buscar por atajo..."
            class="q-mt-sm"
          >
            <template #prepend>
              <q-icon name="sym_r_search" />
            </template>
          </q-input>
        </q-card-section>

        <q-card-section style="max-height: 320px" class="scroll">
          <q-list separator>
            <q-item
              v-for="qm in filteredQuickMessages"
              :key="qm.id"
              clickable
              v-close-popup
              @click="insertQuickMessage(qm.message)"
            >
              <q-item-section>
                <q-item-label class="text-bold text-primary">/{{ qm.shortcut }}</q-item-label>
                <q-item-label caption lines="2">{{ qm.message }}</q-item-label>
              </q-item-section>
            </q-item>
          </q-list>
        </q-card-section>
      </q-card>
    </q-dialog>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import type { QuickMessage } from '../types/conversation.types'

const props = defineProps<{
  loading?: boolean
  initialMode?: 'internal' | 'whatsapp'
  quickMessages?: QuickMessage[]
  suggestedReply?: string | null
}>()

const emit = defineEmits<{
  submit: [body: string, mode: 'internal' | 'whatsapp']
}>()

const activeMode = ref<'internal' | 'whatsapp'>(props.initialMode || 'whatsapp')
const body = ref('')
const showQuickMessages = ref(false)
const quickSearch = ref('')

watch(
  () => props.loading,
  (loading) => {
    if (!loading) {
      body.value = ''
    }
  },
)

watch(
  () => props.suggestedReply,
  (suggestion) => {
    if (suggestion) {
      body.value = suggestion
      activeMode.value = 'whatsapp'
    }
  },
)

const filteredQuickMessages = computed(() => {
  if (!props.quickMessages) return []
  if (!quickSearch.value) return props.quickMessages
  const term = quickSearch.value.toLowerCase()
  return props.quickMessages.filter(
    (qm) => qm.shortcut.toLowerCase().includes(term) || qm.message.toLowerCase().includes(term),
  )
})

function onInput(val: string | number | null) {
  const str = String(val || '')
  if (str.startsWith('/') && str.length === 1) {
    showQuickMessages.value = true
  }
}

function insertQuickMessage(text: string) {
  body.value = text
  showQuickMessages.value = false
}

function submit() {
  if (!body.value.trim()) return
  emit('submit', body.value.trim(), activeMode.value)
}
</script>

<style scoped lang="scss">
.message-composer {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 12px 16px;
  border-radius: 16px;
  background: rgba(14, 27, 43, 0.95);
  border: 1px solid rgba(118, 198, 255, 0.1);
  transition: all 0.2s ease;

  &--internal {
    background: rgba(40, 32, 10, 0.95);
    border-color: rgba(234, 179, 8, 0.3);
  }
}

.message-composer__header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.message-composer__input-row {
  display: flex;
  align-items: flex-end;
  gap: 8px;
}

.message-composer__input {
  flex: 1;
}
</style>
