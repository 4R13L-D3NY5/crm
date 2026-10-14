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

      <div class="message-composer__shortcuts-hint row items-center q-gutter-x-xs">
        <q-btn
          flat
          dense
          size="sm"
          color="deep-purple-3"
          icon="sym_r_sticky_note_2"
          label="Notas (#)"
          @click="openKeepNotes"
        />
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

    <!-- Diálogo de Notas Keep -->
    <q-dialog v-model="showKeepNotes">
      <q-card style="min-width: 440px; max-width: 95vw">
        <q-card-section class="q-pb-none">
          <div class="row items-center justify-between">
            <div class="text-h6 row items-center q-gutter-x-xs">
              <q-icon name="sym_r_sticky_note_2" color="deep-purple-4" size="22px" />
              <span>Notas de Conocimiento</span>
            </div>
            <q-btn flat round dense icon="sym_r_close" color="grey-4" v-close-popup />
          </div>
          <q-input
            v-model="keepSearch"
            dense
            outlined
            placeholder="Buscar por #atajo, título o palabras clave..."
            class="q-mt-sm"
          >
            <template #prepend>
              <q-icon name="sym_r_search" />
            </template>
          </q-input>
        </q-card-section>

        <q-card-section style="max-height: 340px" class="scroll">
          <div v-if="loadingKeepNotes" class="text-center q-py-md">
            <q-spinner-dots size="32px" color="primary" />
          </div>
          <div v-else-if="filteredKeepNotes.length === 0" class="text-center text-grey-5 q-py-md">
            No se encontraron notas disponibles.
          </div>
          <q-list v-else separator>
            <q-item
              v-for="note in filteredKeepNotes"
              :key="note.id"
              clickable
              v-close-popup
              @click="insertKeepNote(note.content)"
            >
              <q-item-section>
                <div class="row items-center q-gutter-x-xs">
                  <span class="text-bold text-deep-purple-4">{{ note.title || 'Nota sin título' }}</span>
                  <q-badge v-if="note.shortcut" color="deep-purple-9" text-color="deep-purple-1">#{{ note.shortcut }}</q-badge>
                </div>
                <q-item-label caption lines="2" class="q-mt-xs">{{ note.content }}</q-item-label>
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
import { getNotes } from '@/modules/notes/api/notes.api'
import type { Note } from '@/modules/notes/types/note.types'
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

const showKeepNotes = ref(false)
const keepSearch = ref('')
const keepNotesList = ref<Note[]>([])
const loadingKeepNotes = ref(false)

async function openKeepNotes() {
  showKeepNotes.value = true
  if (keepNotesList.value.length === 0) {
    loadingKeepNotes.value = true
    try {
      keepNotesList.value = await getNotes({ is_archived: false })
    } catch (e) {
      console.error('Error fetching keep notes in composer', e)
    } finally {
      loadingKeepNotes.value = false
    }
  }
}

const filteredKeepNotes = computed(() => {
  if (!keepSearch.value.trim()) return keepNotesList.value
  const term = keepSearch.value.trim().toLowerCase().replace(/^#+/, '')
  return keepNotesList.value.filter(
    (n) =>
      (n.shortcut && n.shortcut.toLowerCase().includes(term)) ||
      (n.title && n.title.toLowerCase().includes(term)) ||
      n.content.toLowerCase().includes(term),
  )
})

function insertKeepNote(text: string) {
  if (body.value.startsWith('#')) {
    body.value = text
  } else if (body.value.trim()) {
    body.value += '\n' + text
  } else {
    body.value = text
  }
  showKeepNotes.value = false
}

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
  } else if (str.startsWith('#') && str.length === 1) {
    openKeepNotes()
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
