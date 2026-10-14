<template>
  <q-dialog
    v-model="isOpen"
    persistent
    maximized-mobile
    transition-show="fade"
    transition-hide="fade"
  >
    <q-card class="xf-note-dialog-card column no-wrap" style="width: 720px; max-width: 95vw; max-height: 90vh;">
      <!-- Dialog Header -->
      <q-card-section class="xf-note-dialog__header row items-center justify-between q-py-sm q-px-md">
        <div class="row items-center q-gutter-x-sm">
          <q-avatar size="32px" :style="{ backgroundColor: form.color || '#1e293b' }">
            <q-icon name="sym_r_sticky_note_2" size="18px" color="white" />
          </q-avatar>
          <div>
            <div class="text-subtitle1 text-weight-bold text-white">
              {{ isEditing ? 'Editar Nota Keep' : 'Nueva Nota de Conocimiento' }}
            </div>
            <div class="text-caption text-grey-4" style="font-size: 0.72rem">
              Organiza micro-conocimiento, guías y respuestas para el equipo
            </div>
          </div>
        </div>

        <div class="row items-center q-gutter-x-xs">
          <!-- Toggle Pin Rápido -->
          <q-btn
            flat
            round
            dense
            size="sm"
            :color="form.is_pinned ? 'amber-4' : 'grey-5'"
            icon="sym_r_push_pin"
            @click="form.is_pinned = !form.is_pinned"
          >
            <q-tooltip>{{ form.is_pinned ? 'Nota fijada en la parte superior' : 'Fijar en la parte superior' }}</q-tooltip>
          </q-btn>

          <!-- Cerrar Modal -->
          <q-btn
            flat
            round
            dense
            size="sm"
            color="grey-4"
            icon="sym_r_close"
            v-close-popup
          />
        </div>
      </q-card-section>

      <q-separator dark />

      <!-- Dialog Body con scroll garantizado -->
      <q-card-section class="xf-note-dialog__body col scroll q-pa-md q-gutter-y-md">
        <!-- Título -->
        <q-input
          v-model="form.title"
          outlined
          dense
          label="Título de la nota (opcional)"
          placeholder="Ej: Requisitos de Inscripción - Medicina 2026"
          class="xf-input-field"
        >
          <template #prepend>
            <q-icon name="sym_r_title" size="18px" color="teal-3" />
          </template>
        </q-input>

        <!-- Barra de Herramientas de Inteligencia Artificial y Detección -->
        <div class="ai-toolbar row items-center justify-between q-pa-sm rounded-borders">
          <div class="row items-center q-gutter-x-xs">
            <!-- Botón Asistente IA -->
            <q-btn
              unelevated
              dense
              size="sm"
              color="amber-8"
              text-color="dark"
              icon="sym_r_auto_awesome"
              label="Asistente IA"
              class="q-px-sm text-weight-bold"
              :loading="isRefining"
            >
              <q-tooltip>Revisar ortografía, sintetizar o adaptar el tono con IA</q-tooltip>
              <q-menu anchor="bottom start" self="top start" class="ai-menu-dropdown">
                <q-list dense style="min-width: 220px;">
                  <q-item-label header class="text-caption text-weight-bold text-amber-5">
                    ✨ Herramientas de IA
                  </q-item-label>

                  <q-item clickable v-close-popup @click="handleAiRefine('spellcheck')">
                    <q-item-section avatar style="min-width: 28px;">
                      <q-icon name="sym_r_spellcheck" size="18px" color="teal-4" />
                    </q-item-section>
                    <q-item-section>
                      <q-item-label>Corregir Ortografía</q-item-label>
                      <q-item-label caption>Puntuación y gramática en español</q-item-label>
                    </q-item-section>
                  </q-item>

                  <q-item clickable v-close-popup @click="handleAiRefine('summarize')">
                    <q-item-section avatar style="min-width: 28px;">
                      <q-icon name="sym_r_short_text" size="18px" color="light-blue-4" />
                    </q-item-section>
                    <q-item-section>
                      <q-item-label>Sintetizar / Resumir</q-item-label>
                      <q-item-label caption>Hacer más ágil y conciso</q-item-label>
                    </q-item-section>
                  </q-item>

                  <q-item clickable v-close-popup @click="handleAiRefine('tone_formal')">
                    <q-item-section avatar style="min-width: 28px;">
                      <q-icon name="sym_r_business_center" size="18px" color="indigo-4" />
                    </q-item-section>
                    <q-item-section>
                      <q-item-label>Tono Formal</q-item-label>
                      <q-item-label caption>Institucional y respetuoso</q-item-label>
                    </q-item-section>
                  </q-item>

                  <q-item clickable v-close-popup @click="handleAiRefine('tone_persuasive')">
                    <q-item-section avatar style="min-width: 28px;">
                      <q-icon name="sym_r_trending_up" size="18px" color="purple-4" />
                    </q-item-section>
                    <q-item-section>
                      <q-item-label>Tono Persuasivo</q-item-label>
                      <q-item-label caption>Orientado a ventas y captación</q-item-label>
                    </q-item-section>
                  </q-item>
                </q-list>
              </q-menu>
            </q-btn>

            <!-- Botón Detección de Entidades -->
            <q-btn
              unelevated
              dense
              size="sm"
              color="teal-8"
              icon="sym_r_troubleshoot"
              label="Escanear Entidades"
              class="q-px-sm"
              :loading="isAnalyzing"
              :disable="!form.content.trim()"
              @click="scanEntities"
            >
              <q-tooltip>Detectar si el texto menciona Carreras, Estados de Lead o Etiquetas</q-tooltip>
            </q-btn>
          </div>

          <div class="text-caption text-grey-4 font-mono" style="font-size: 0.72rem">
            {{ form.content.length }} caracteres
          </div>
        </div>

        <!-- Alerta de Entidades Detectadas con Opción de Enlazar -->
        <div v-if="detectedResult && detectedResult.total_detected > 0" class="detected-box q-pa-sm rounded-borders">
          <div class="row items-center justify-between q-mb-xs">
            <span class="text-caption text-weight-bold text-teal-3 row items-center q-gutter-x-xs">
              <q-icon name="sym_r_check_circle" size="16px" color="teal-3" />
              <span>Entidades detectadas en el texto ({{ detectedResult.total_detected }}):</span>
            </span>
            <q-btn
              flat
              dense
              size="xs"
              color="teal-3"
              label="Vincular todo con 1 clic"
              @click="linkAllDetected"
            />
          </div>

          <div class="row items-center q-gutter-xs">
            <q-chip
              v-for="cat in detectedResult.detected_categories"
              :key="cat.id"
              clickable
              dense
              size="sm"
              :color="isCategorySelected(cat.id) ? 'positive' : 'grey-9'"
              :text-color="isCategorySelected(cat.id) ? 'white' : 'teal-3'"
              icon="sym_r_school"
              @click="toggleDetectedCategory(cat.id)"
            >
              {{ cat.name }}
              <q-tooltip>{{ isCategorySelected(cat.id) ? 'Ya vinculada' : 'Haz clic para vincular esta categoría' }}</q-tooltip>
            </q-chip>

            <q-chip
              v-for="st in detectedResult.detected_custom_statuses"
              :key="st.id"
              clickable
              dense
              size="sm"
              :color="isStatusSelected(st.id) ? 'positive' : 'grey-9'"
              :text-color="isStatusSelected(st.id) ? 'white' : 'blue-3'"
              icon="sym_r_flag"
              @click="toggleDetectedStatus(st.id)"
            >
              {{ st.name }}
              <q-tooltip>{{ isStatusSelected(st.id) ? 'Ya vinculado' : 'Haz clic para vincular este estado' }}</q-tooltip>
            </q-chip>

            <q-chip
              v-for="tg in detectedResult.detected_tags"
              :key="tg.id"
              clickable
              dense
              size="sm"
              :color="isTagSelected(tg.id) ? 'positive' : 'grey-9'"
              :text-color="isTagSelected(tg.id) ? 'white' : 'green-3'"
              icon="sym_r_label"
              @click="toggleDetectedTag(tg.id)"
            >
              {{ tg.name }}
              <q-tooltip>{{ isTagSelected(tg.id) ? 'Ya vinculada' : 'Haz clic para vincular esta etiqueta' }}</q-tooltip>
            </q-chip>
          </div>
        </div>

        <!-- Contenido de la Nota -->
        <q-input
          v-model="form.content"
          type="textarea"
          outlined
          autogrow
          :rows="6"
          label="Contenido de la nota *"
          placeholder="Escribe el apunte, instrucciones, costos, requisitos o respuesta predefinida..."
          class="xf-input-field"
        />

        <!-- Atajo & Visibilidad -->
        <div class="row q-col-gutter-sm">
          <div class="col-12 col-sm-6">
            <q-input
              v-model="form.shortcut"
              outlined
              dense
              label="Atajo rápido (#)"
              placeholder="Ej: medicina, requisitos, pagos"
              prefix="#"
              class="xf-input-field"
            >
              <template #append>
                <q-tooltip>Escribe #{{ form.shortcut || 'atajo' }} en el chat para insertarla al instante</q-tooltip>
              </template>
            </q-input>
          </div>

          <div class="col-12 col-sm-6">
            <q-select
              v-model="form.visibility"
              outlined
              dense
              emit-value
              map-options
              :options="visibilityOptions"
              label="Visibilidad de la Nota"
              class="xf-input-field"
            >
              <template #prepend>
                <q-icon name="sym_r_visibility" size="18px" color="teal-3" />
              </template>
            </q-select>
          </div>
        </div>

        <!-- Paleta de Colores Keep -->
        <div>
          <div class="text-caption text-weight-bold text-grey-4 q-mb-xs">Color de fondo</div>
          <div class="row items-center q-gutter-sm">
            <button
              v-for="color in colorPalette"
              :key="color.value"
              type="button"
              class="color-circle-btn"
              :style="{ backgroundColor: color.value }"
              :class="{ 'color-circle-btn--active': form.color === color.value }"
              @click="form.color = color.value"
            >
              <q-icon
                v-if="form.color === color.value"
                name="sym_r_check"
                size="14px"
                color="white"
              />
              <q-tooltip>{{ color.label }}</q-tooltip>
            </button>
          </div>
        </div>

        <!-- Selectores de Metadatos de Enlace -->
        <div class="row q-col-gutter-sm">
          <!-- Categorías / Carreras -->
          <div class="col-12 col-sm-4">
            <q-select
              v-model="form.category_ids"
              multiple
              use-chips
              dense
              outlined
              emit-value
              map-options
              option-value="id"
              option-label="name"
              :options="categoriesOptions"
              label="Categorías / Carreras"
              class="xf-input-field"
            />
          </div>

          <!-- Estados Personalizados -->
          <div class="col-12 col-sm-4">
            <q-select
              v-model="form.custom_status_ids"
              multiple
              use-chips
              dense
              outlined
              emit-value
              map-options
              option-value="id"
              option-label="name"
              :options="statusesOptions"
              label="Estados de Lead"
              class="xf-input-field"
            />
          </div>

          <!-- Etiquetas -->
          <div class="col-12 col-sm-4">
            <q-select
              v-model="form.tag_ids"
              multiple
              use-chips
              dense
              outlined
              emit-value
              map-options
              option-value="id"
              option-label="name"
              :options="tagsOptions"
              label="Etiquetas"
              class="xf-input-field"
            />
          </div>
        </div>
      </q-card-section>

      <q-separator dark />

      <!-- Dialog Footer anclado -->
      <q-card-actions align="between" class="xf-note-dialog__footer q-px-md q-py-sm">
        <div class="row items-center q-gutter-x-sm">
          <q-checkbox
            v-model="form.is_archived"
            dense
            label="Archivar nota"
            class="text-caption text-grey-4"
          />
        </div>

        <div class="row items-center q-gutter-x-sm">
          <q-btn
            flat
            dense
            color="grey-4"
            label="Cancelar"
            v-close-popup
          />

          <q-btn
            unelevated
            dense
            color="primary"
            icon="sym_r_save"
            :label="isEditing ? 'Actualizar Nota' : 'Guardar en Keep'"
            class="q-px-md text-weight-bold"
            :loading="isSaving"
            :disable="!form.content.trim()"
            @click="handleSubmit"
          />
        </div>
      </q-card-actions>
    </q-card>

    <!-- Submodal de Confirmación de Cambios de IA (Antes vs Después) -->
    <q-dialog v-model="showAiDiffModal" persistent>
      <q-card style="width: 600px; max-width: 95vw;" class="ai-diff-card">
        <q-card-section class="row items-center justify-between q-py-sm">
          <div class="row items-center q-gutter-x-sm">
            <q-avatar size="28px" color="amber-8" text-color="dark">
              <q-icon name="sym_r_auto_awesome" size="16px" />
            </q-avatar>
            <div class="text-subtitle2 text-weight-bold text-white">
              Sugerencia de IA — {{ aiRefineTitle }}
            </div>
          </div>
          <q-btn flat round dense size="sm" icon="sym_r_close" color="grey-4" v-close-popup />
        </q-card-section>

        <q-separator dark />

        <q-card-section class="q-gutter-y-sm scroll" style="max-height: 60vh;">
          <div class="text-caption text-grey-4">
            Revisa el texto propuesto antes de aplicar los cambios a la nota:
          </div>

          <div class="diff-block">
            <div class="text-caption text-weight-bold text-grey-5 q-mb-xs">Texto Actual:</div>
            <div class="diff-text original-text">{{ form.content }}</div>
          </div>

          <div class="diff-block">
            <div class="text-caption text-weight-bold text-amber-4 q-mb-xs">Propuesta Mejorada con IA:</div>
            <div class="diff-text refined-text">{{ aiRefinedCandidate }}</div>
          </div>
        </q-card-section>

        <q-separator dark />

        <q-card-actions align="right" class="q-pa-sm">
          <q-btn flat dense color="grey-4" label="Descartar" v-close-popup />
          <q-btn
            unelevated
            dense
            color="amber-8"
            text-color="dark"
            icon="sym_r_check"
            label="Aplicar al Contenido"
            class="q-px-md text-weight-bold"
            @click="applyAiChanges"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-dialog>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useQuasar } from 'quasar'
import { http } from '@/shared/api/http'
import { useNotes } from '../composables/useNotes'
import type { AnalyzeResult, Note, NotePayload } from '../types/note.types'

const props = defineProps<{
  modelValue: boolean
  note?: Note | null
}>()

const emit = defineEmits<{
  'update:modelValue': [val: boolean]
  saved: [note: Note]
}>()

const $q = useQuasar()
const { saveNote, analyzeContent, refineWithAi, isSaving, isAnalyzing, isRefining } = useNotes()

const isOpen = computed({
  get: () => props.modelValue,
  set: (val) => emit('update:modelValue', val),
})

const isEditing = computed(() => !!props.note?.id)

const colorPalette = [
  { label: 'Pizarra Oscura (Default)', value: '#1e293b' },
  { label: 'Azul Marino', value: '#1e3a5f' },
  { label: 'Verde Esmeralda', value: '#064e3b' },
  { label: 'Púrpura Místico', value: '#4c1d95' },
  { label: 'Bronce Ámbar', value: '#78350f' },
  { label: 'Rosa Carmesí', value: '#831843' },
  { label: 'Verde Azulado', value: '#134e4a' },
  { label: 'Índigo Profundo', value: '#312e81' },
]

const visibilityOptions = [
  { label: 'Visibilidad Total (Todo el equipo)', value: 'all' },
  { label: 'Solo Carreras Asignadas al Asesor', value: 'categories' },
  { label: 'Privada (Solo Administradores)', value: 'private' },
]

const form = reactive<NotePayload>({
  title: '',
  content: '',
  color: '#1e293b',
  is_pinned: false,
  is_archived: false,
  shortcut: '',
  visibility: 'all',
  category_ids: [],
  custom_status_ids: [],
  tag_ids: [],
})

const categoriesOptions = ref<Array<{ id: string; name: string }>>([])
const statusesOptions = ref<Array<{ id: string; name: string }>>([])
const tagsOptions = ref<Array<{ id: string; name: string }>>([])

const detectedResult = ref<AnalyzeResult | null>(null)
const showAiDiffModal = ref(false)
const aiRefinedCandidate = ref('')
const aiRefineTitle = ref('')

watch(
  () => props.modelValue,
  async (open) => {
    if (open) {
      loadCatalogOptions()
      detectedResult.value = null

      if (props.note) {
        form.title = props.note.title || ''
        form.content = props.note.content || ''
        form.color = props.note.color || '#1e293b'
        form.is_pinned = props.note.is_pinned || false
        form.is_archived = props.note.is_archived || false
        form.shortcut = props.note.shortcut || ''
        form.visibility = props.note.visibility || 'all'
        form.category_ids = props.note.categories?.map((c) => c.id) || []
        form.custom_status_ids = props.note.custom_statuses?.map((s) => s.id) || []
        form.tag_ids = props.note.tags?.map((t) => t.id) || []
      } else {
        form.title = ''
        form.content = ''
        form.color = '#1e293b'
        form.is_pinned = false
        form.is_archived = false
        form.shortcut = ''
        form.visibility = 'all'
        form.category_ids = []
        form.custom_status_ids = []
        form.tag_ids = []
      }
    }
  },
)

async function loadCatalogOptions() {
  try {
    const [catRes, stRes, tgRes] = await Promise.all([
      http.get('/categories'),
      http.get('/custom-statuses'),
      http.get('/tags'),
    ])
    categoriesOptions.value = catRes.data?.data || []
    statusesOptions.value = stRes.data?.data || []
    tagsOptions.value = tgRes.data?.data || []
  } catch (err) {
    console.error('Error cargando catálogo para notas:', err)
  }
}

async function scanEntities() {
  if (!form.content.trim()) return
  try {
    const result = await analyzeContent(form.content)
    detectedResult.value = result
    if (result.total_detected === 0) {
      $q.notify({
        type: 'info',
        message: 'No se detectaron menciones de carreras, estados o etiquetas en el texto.',
        position: 'bottom-right',
      })
    } else {
      $q.notify({
        type: 'positive',
        message: `¡Se detectaron ${result.total_detected} entidades mencionadas en la nota!`,
        position: 'bottom-right',
      })
    }
  } catch (err: any) {
    $q.notify({
      type: 'negative',
      message: err?.response?.data?.message || 'Error al analizar el contenido',
      position: 'bottom-right',
    })
  }
}

function isCategorySelected(id: string): boolean {
  return form.category_ids?.includes(id) ?? false
}

function isStatusSelected(id: string): boolean {
  return form.custom_status_ids?.includes(id) ?? false
}

function isTagSelected(id: string): boolean {
  return form.tag_ids?.includes(id) ?? false
}

function toggleDetectedCategory(id: string) {
  if (!form.category_ids) form.category_ids = []
  const idx = form.category_ids.indexOf(id)
  if (idx === -1) {
    form.category_ids.push(id)
  } else {
    form.category_ids.splice(idx, 1)
  }
}

function toggleDetectedStatus(id: string) {
  if (!form.custom_status_ids) form.custom_status_ids = []
  const idx = form.custom_status_ids.indexOf(id)
  if (idx === -1) {
    form.custom_status_ids.push(id)
  } else {
    form.custom_status_ids.splice(idx, 1)
  }
}

function toggleDetectedTag(id: string) {
  if (!form.tag_ids) form.tag_ids = []
  const idx = form.tag_ids.indexOf(id)
  if (idx === -1) {
    form.tag_ids.push(id)
  } else {
    form.tag_ids.splice(idx, 1)
  }
}

function linkAllDetected() {
  if (!detectedResult.value) return
  if (!form.category_ids) form.category_ids = []
  if (!form.custom_status_ids) form.custom_status_ids = []
  if (!form.tag_ids) form.tag_ids = []

  detectedResult.value.detected_categories.forEach((cat) => {
    if (!form.category_ids!.includes(cat.id)) form.category_ids!.push(cat.id)
  })
  detectedResult.value.detected_custom_statuses.forEach((st) => {
    if (!form.custom_status_ids!.includes(st.id)) form.custom_status_ids!.push(st.id)
  })
  detectedResult.value.detected_tags.forEach((tg) => {
    if (!form.tag_ids!.includes(tg.id)) form.tag_ids!.push(tg.id)
  })

  $q.notify({
    type: 'positive',
    message: 'Todas las entidades detectadas han sido vinculadas a la nota.',
    position: 'bottom-right',
  })
}

async function handleAiRefine(action: 'spellcheck' | 'summarize' | 'tone_formal' | 'tone_persuasive') {
  if (!form.content.trim()) return

  const actionTitles: Record<string, string> = {
    spellcheck: 'Corrección Ortográfica y Estilo',
    summarize: 'Síntesis y Resumen',
    tone_formal: 'Adaptación a Tono Formal',
    tone_persuasive: 'Adaptación a Tono Persuasivo',
  }
  aiRefineTitle.value = actionTitles[action] || 'Ajuste con IA'

  try {
    const res = await refineWithAi({
      content: form.content,
      action,
    })
    aiRefinedCandidate.value = res.refined_content
    showAiDiffModal.value = true
  } catch (err: any) {
    $q.notify({
      type: 'negative',
      message: err?.response?.data?.message || 'Error al comunicarse con el motor de IA',
      position: 'bottom-right',
    })
  }
}

function applyAiChanges() {
  form.content = aiRefinedCandidate.value
  showAiDiffModal.value = false
  $q.notify({
    type: 'positive',
    message: 'Cambios de IA aplicados al contenido de la nota.',
    position: 'bottom-right',
  })
}

async function handleSubmit() {
  if (!form.content.trim()) return
  try {
    const saved = await saveNote(form, props.note?.id)
    if (saved) {
      $q.notify({
        type: 'positive',
        message: isEditing.value ? 'Nota actualizada correctamente' : 'Nota guardada con éxito',
        position: 'bottom-right',
      })
      emit('saved', saved)
      isOpen.value = false
    }
  } catch (err: any) {
    $q.notify({
      type: 'negative',
      message: err?.response?.data?.message || 'Error al guardar la nota',
      position: 'bottom-right',
    })
  }
}
</script>

<style scoped lang="scss">
.xf-note-dialog-card {
  background: #0f172a;
  border: 1px solid rgba(255, 255, 255, 0.12);
  border-radius: 16px;
  overflow: hidden;
}

.xf-note-dialog__header {
  background: rgba(15, 23, 42, 0.95);
}

.ai-toolbar {
  background: rgba(30, 41, 59, 0.7);
  border: 1px solid rgba(255, 255, 255, 0.08);
}

.detected-box {
  background: rgba(6, 78, 59, 0.25);
  border: 1px solid rgba(16, 185, 129, 0.3);
}

.color-circle-btn {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  border: 2px solid transparent;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s ease;

  &:hover {
    transform: scale(1.15);
  }

  &--active {
    border-color: #ffffff;
    box-shadow: 0 0 10px rgba(255, 255, 255, 0.5);
  }
}

.ai-diff-card {
  background: #0f172a;
  border: 1px solid rgba(251, 191, 36, 0.3);
  border-radius: 14px;
}

.diff-block {
  background: rgba(30, 41, 59, 0.6);
  border-radius: 8px;
  padding: 10px 12px;
  border: 1px solid rgba(255, 255, 255, 0.08);
}

.diff-text {
  font-size: 0.88rem;
  line-height: 1.5;
  white-space: pre-wrap;
  word-break: break-word;

  &.original-text {
    color: #94a3b8;
  }

  &.refined-text {
    color: #fef08a;
  }
}
</style>
