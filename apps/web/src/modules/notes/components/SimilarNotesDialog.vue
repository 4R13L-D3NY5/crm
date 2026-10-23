<template>
  <q-dialog
    v-model="isOpen"
    transition-show="jump-down"
    transition-hide="jump-up"
  >
    <q-card class="xf-similar-dialog column no-wrap">
      <!-- Header -->
      <q-card-section class="xf-similar-header row items-center justify-between q-py-sm q-px-md">
        <div class="row items-center q-gutter-x-sm">
          <q-avatar size="32px" :color="currentMode === 'ai' ? 'purple-9' : 'teal-9'" text-color="white">
            <q-icon :name="currentMode === 'ai' ? 'sym_r_auto_awesome' : 'sym_r_find_in_page'" size="18px" />
          </q-avatar>
          <div>
            <div class="text-subtitle1 text-weight-bold text-white row items-center q-gutter-x-xs">
              <span>
                {{
                  isMatrixMode
                    ? (currentMode === 'ai' ? 'Tarjetas Similares entre sí (Copiloto IA)' : 'Tarjetas Similares entre sí (Algorítmico)')
                    : (currentMode === 'ai' ? 'Notas Similares con IA' : 'Notas Similares (Léxica & Términos)')
                }}
              </span>
              <q-badge :color="currentMode === 'ai' ? 'purple-8' : 'teal-8'" text-color="white" class="text-caption text-bold font-mono">
                {{ currentMode === 'ai' ? 'Copiloto IA' : 'Algorítmico' }}
              </q-badge>
            </div>
            <div class="text-caption text-grey-4" style="font-size: 0.72rem">
              {{
                isMatrixMode
                  ? (currentMode === 'ai' ? 'Comparación semántica profunda de las notas existentes en el tablero' : 'Comparación léxica de coincidencias y duplicados en el tablero')
                  : (currentMode === 'ai' ? 'Inferencia temática y semántica en la base de conocimiento' : 'Coincidencia de términos clave, títulos y categorías')
              }}
            </div>
          </div>
        </div>

        <q-btn flat round dense size="sm" color="grey-4" icon="sym_r_close" v-close-popup />
      </q-card-section>

      <q-separator dark />

      <!-- Selector de Modo de Similitud -->
      <div class="row items-center justify-between q-px-md q-py-xs bg-slate-900 border-subtle">
        <q-btn-toggle
          v-model="currentMode"
          dense
          no-caps
          rounded
          unelevated
          toggle-color="primary"
          color="dark"
          text-color="grey-4"
          :options="[
            { label: 'Buscar Similar (Léxico)', value: 'algorithmic', icon: 'sym_r_find_in_page' },
            { label: 'Buscar con IA (Semántico)', value: 'ai', icon: 'sym_r_auto_awesome' },
          ]"
          @update:model-value="onModeChange"
        />
        <div class="text-caption text-grey-4 hidden-xs">
          <span v-if="currentMode === 'ai'" class="text-purple-3 font-mono text-caption">🧠 Razonamiento temático e intenciones</span>
          <span v-else class="text-teal-3 font-mono text-caption">⚡ Coincidencia directa de palabras clave</span>
        </div>
      </div>

      <!-- Banner de Resumen Matrix (Tarjetas entre sí) -->
      <div v-if="isMatrixMode" class="q-px-md q-pt-md">
        <div class="reference-note-card q-pa-sm rounded-borders">
          <div class="row items-center justify-between">
            <div class="row items-center q-gutter-x-xs">
              <q-icon name="sym_r_compare" size="18px" color="amber-4" />
              <span class="text-caption text-weight-bold text-uppercase text-amber-4 font-mono letter-spacing-wide">
                Análisis de Similitud entre Tarjetas del Tablero
              </span>
            </div>
            <q-badge color="teal-9" text-color="teal-2" class="font-mono text-caption">
              {{ totalScanned }} notas analizadas
            </q-badge>
          </div>
          <div class="text-caption text-grey-3 q-mt-xs">
            Se compararon las tarjetas del tablero para detectar notas afines, duplicados o temas redundantes mostrando su porcentaje de parecido.
          </div>
        </div>
      </div>

      <!-- Banner de la Nota de Referencia (Modo Individual / Borrador) -->
      <div v-else-if="targetNote || draftContent" class="q-px-md q-pt-md">
        <div class="reference-note-card q-pa-sm rounded-borders">
          <div class="text-caption text-weight-bold text-uppercase text-amber-4 font-mono letter-spacing-wide q-mb-xs">
            Nota de Referencia Analizada:
          </div>
          <div class="text-body2 text-weight-bold text-white ellipsis">
            {{ targetTitle || targetNote?.title || 'Borrador sin título' }}
          </div>
          <div class="text-caption text-grey-3 ellipsis-2-lines q-mt-xs">
            {{ targetContent || targetNote?.content }}
          </div>
          <div v-if="targetAuthor" class="text-caption text-grey-5 q-mt-xs row items-center q-gutter-x-xs">
            <q-icon name="sym_r_person" size="12px" color="teal-4" />
            <span>Registrada por: <strong class="text-grey-3">{{ targetAuthor }}</strong></span>
          </div>
        </div>
      </div>

      <!-- Contenido Principal: Loading / Lista / Vacío -->
      <q-card-section class="col scroll q-pa-md">
        <!-- Spinner mientras analiza -->
        <div v-if="isLoading" class="column items-center justify-center q-py-xl text-center">
          <q-spinner-orbit size="42px" color="amber-5" />
          <div class="text-subtitle2 text-weight-bold text-white q-mt-md">
            {{ currentMode === 'ai' ? 'Analizando similitud temática con Inteligencia Artificial...' : 'Calculando matriz de similitud léxica...' }}
          </div>
          <div class="text-caption text-grey-4 q-mt-xs">
            Evaluando conceptos, palabras clave y relaciones en la base de conocimiento
          </div>
        </div>

        <!-- CASO 1: MODO MATRIZ (Tarjetas Similares entre sí) -->
        <div v-else-if="isMatrixMode">
          <div v-if="matrixPairs.length > 0" class="column q-gutter-y-md">
            <div class="row items-center justify-between text-caption text-grey-4">
              <span>Se encontraron <strong>{{ matrixPairs.length }}</strong> pares de tarjetas afines:</span>
              <span class="text-amber-4 text-weight-medium">Ordenadas por grado de coincidencia</span>
            </div>

            <div
              v-for="pair in matrixPairs"
              :key="pair.id"
              class="similar-card rounded-borders q-pa-md"
              :style="{ borderLeftColor: getScoreColorHex(pair.similarity_percent) }"
            >
              <!-- Header del Par: Badge de Porcentaje -->
              <div class="row items-center justify-between no-wrap q-mb-xs">
                <div class="row items-center q-gutter-x-xs text-caption">
                  <q-icon name="sym_r_join" size="16px" color="amber-4" />
                  <span class="text-weight-bold text-white">Par de Notas Relacionadas</span>
                </div>

                <q-badge
                  :color="getScoreBadgeColor(pair.similarity_percent)"
                  class="text-weight-bold text-caption q-px-sm q-py-xs"
                >
                  <q-icon name="sym_r_insights" size="13px" class="q-mr-xs" />
                  {{ pair.similarity_percent }}% de Parecido
                </q-badge>
              </div>

              <!-- Razón Semántica / Explicación -->
              <div class="ai-reason-box q-pa-xs q-px-sm rounded-borders q-my-xs">
                <div class="text-caption text-amber-2 row items-center q-gutter-x-xs">
                  <q-icon :name="pair.source === 'ia' ? 'sym_r_auto_awesome' : 'sym_r_manage_search'" size="14px" color="amber-4" />
                  <span>{{ pair.similarity_reason }}</span>
                </div>
              </div>

              <!-- Palabras Clave Coincidentes -->
              <div v-if="pair.matched_keywords && pair.matched_keywords.length > 0" class="row items-center q-gutter-xs q-my-xs">
                <span class="text-caption text-grey-5" style="font-size: 0.72rem">Coincidencias:</span>
                <q-badge
                  v-for="kw in pair.matched_keywords"
                  :key="kw"
                  color="dark"
                  text-color="teal-3"
                  class="font-mono text-caption"
                  style="border: 1px solid rgba(20, 184, 166, 0.3); font-size: 0.7rem;"
                >
                  "{{ kw }}"
                </q-badge>
              </div>

              <!-- Comparativa de Ambas Notas Lado a Lado -->
              <div class="row q-col-gutter-sm q-mt-xs">
                <!-- Nota A -->
                <div class="col-12 col-md-6">
                  <div class="sub-note-box q-pa-sm rounded-borders">
                    <div class="row items-start justify-between no-wrap q-mb-xs">
                      <div class="text-subtitle2 text-weight-bold text-white ellipsis">
                        {{ pair.note_a.title || 'Nota sin título' }}
                      </div>
                      <q-btn
                        flat
                        dense
                        size="xs"
                        color="teal-3"
                        icon="sym_r_edit"
                        label="Editar"
                        no-caps
                        @click="onEditNote(pair.note_a)"
                      />
                    </div>
                    <div class="text-body2 text-grey-3 ellipsis-3-lines q-my-xs" style="font-size: 0.82rem">
                      {{ pair.note_a.content }}
                    </div>
                    <div class="row items-center justify-between text-caption text-grey-5 q-mt-xs pt-xs border-top-subtle" style="font-size: 0.72rem">
                      <span class="ellipsis" style="max-width: 140px">
                        {{ pair.note_a.user?.name || pair.note_a.author_name || 'Usuario' }}
                      </span>
                      <q-btn
                        flat
                        dense
                        size="xs"
                        color="grey-4"
                        icon="sym_r_filter_alt"
                        label="Filtrar"
                        no-caps
                        @click="onFilterBoard(pair.note_a)"
                      />
                    </div>
                  </div>
                </div>

                <!-- Nota B -->
                <div class="col-12 col-md-6">
                  <div class="sub-note-box q-pa-sm rounded-borders">
                    <div class="row items-start justify-between no-wrap q-mb-xs">
                      <div class="text-subtitle2 text-weight-bold text-white ellipsis">
                        {{ pair.note_b.title || 'Nota sin título' }}
                      </div>
                      <q-btn
                        flat
                        dense
                        size="xs"
                        color="teal-3"
                        icon="sym_r_edit"
                        label="Editar"
                        no-caps
                        @click="onEditNote(pair.note_b)"
                      />
                    </div>
                    <div class="text-body2 text-grey-3 ellipsis-3-lines q-my-xs" style="font-size: 0.82rem">
                      {{ pair.note_b.content }}
                    </div>
                    <div class="row items-center justify-between text-caption text-grey-5 q-mt-xs pt-xs border-top-subtle" style="font-size: 0.72rem">
                      <span class="ellipsis" style="max-width: 140px">
                        {{ pair.note_b.user?.name || pair.note_b.author_name || 'Usuario' }}
                      </span>
                      <q-btn
                        flat
                        dense
                        size="xs"
                        color="grey-4"
                        icon="sym_r_filter_alt"
                        label="Filtrar"
                        no-caps
                        @click="onFilterBoard(pair.note_b)"
                      />
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Estado Vacío Matrix -->
          <div v-else class="column items-center justify-center q-py-xl text-center">
            <q-avatar size="56px" color="teal-10" text-color="teal-2" class="q-mb-md">
              <q-icon name="sym_r_verified" size="32px" />
            </q-avatar>
            <div class="text-subtitle1 text-weight-bold text-white q-mb-xs">
              ¡No se detectaron notas duplicadas o parecidas!
            </div>
            <p class="text-caption text-grey-4" style="max-width: 440px">
              Todas las tarjetas analizadas cuentan con contenido y términos suficientemente diferenciados.
            </p>
          </div>
        </div>

        <!-- CASO 2: MODO INDIVIDUAL / BORRADOR -->
        <div v-else-if="similarList.length > 0" class="column q-gutter-y-md">
          <div class="row items-center justify-between text-caption text-grey-4">
            <span>Se encontraron <strong>{{ similarList.length }}</strong> notas afines:</span>
            <span class="text-amber-4 text-weight-medium">Ordenadas por grado de coincidencia</span>
          </div>

          <div
            v-for="item in similarList"
            :key="item.note.id"
            class="similar-card rounded-borders q-pa-md"
            :style="{ borderLeftColor: getScoreColorHex(item.similarity_percent) }"
          >
            <!-- Header de la tarjeta similar -->
            <div class="row items-start justify-between no-wrap q-mb-xs">
              <div class="col ellipsis">
                <div class="text-subtitle2 text-weight-bold text-white ellipsis">
                  {{ item.note.title || 'Nota sin título' }}
                </div>
              </div>

              <!-- Badge de Porcentaje -->
              <q-badge
                :color="getScoreBadgeColor(item.similarity_percent)"
                class="text-weight-bold text-caption q-px-sm q-py-xs"
              >
                <q-icon name="sym_r_insights" size="12px" class="q-mr-xs" />
                {{ item.similarity_percent }}% Similitud
              </q-badge>
            </div>

            <!-- Razón de la IA / Explicación Semántica -->
            <div class="ai-reason-box q-pa-xs q-px-sm rounded-borders q-my-xs">
              <div class="text-caption text-amber-2 row items-center q-gutter-x-xs">
                <q-icon name="sym_r_auto_awesome" size="14px" color="amber-4" />
                <span>{{ item.similarity_reason }}</span>
              </div>
            </div>

            <!-- Contenido de la nota similar -->
            <div class="text-body2 text-grey-3 ellipsis-3-lines q-my-xs" style="font-size: 0.85rem">
              {{ item.note.content }}
            </div>

            <!-- Chips de Categorías y Tags -->
            <div v-if="hasEntities(item.note)" class="row items-center q-gutter-xs q-my-xs">
              <q-chip
                v-for="cat in item.note.categories"
                :key="cat.id"
                dense
                size="sm"
                class="q-mr-none"
                :style="{ backgroundColor: 'rgba(16, 185, 129, 0.15)', color: '#34d399', borderColor: '#10b981' }"
              >
                <q-avatar size="14px" color="teal-9" text-color="white">
                  <q-icon name="sym_r_school" size="10px" />
                </q-avatar>
                {{ cat.name }}
              </q-chip>

              <q-chip
                v-for="tag in item.note.tags"
                :key="tag.id"
                dense
                size="sm"
                class="q-mr-none"
                :style="{ backgroundColor: 'rgba(0, 168, 132, 0.15)', color: '#2dd4bf' }"
              >
                #{{ tag.name }}
              </q-chip>
            </div>

            <!-- Footer de la nota similar: Autor y Acciones -->
            <div class="row items-center justify-between q-mt-sm q-pt-xs border-top-subtle">
              <div class="row items-center q-gutter-x-xs text-caption text-grey-4">
                <q-avatar size="20px" color="teal-9" text-color="teal-2" class="text-weight-bold" style="font-size: 10px;">
                  {{ getInitials(item.note.user?.name || item.note.author_name) }}
                </q-avatar>
                <span class="ellipsis" style="max-width: 180px">
                  {{ item.note.user?.name || item.note.author_name || 'Usuario' }}
                </span>
                <span class="text-grey-6">•</span>
                <span class="text-grey-5 font-mono text-caption" style="font-size: 0.72rem">
                  {{ formatDate(item.note.created_at) }}
                </span>
              </div>

              <div class="row items-center q-gutter-x-xs">
                <q-btn
                  flat
                  round
                  dense
                  size="sm"
                  color="grey-4"
                  icon="sym_r_content_copy"
                  @click="copyContent(item.note.content)"
                >
                  <q-tooltip>Copiar texto de esta nota</q-tooltip>
                </q-btn>

                <q-btn
                  flat
                  dense
                  size="sm"
                  color="teal-3"
                  icon="sym_r_filter_alt"
                  label="Filtrar"
                  class="q-px-xs"
                  no-caps
                  @click="onFilterBoard(item.note)"
                >
                  <q-tooltip>Filtrar esta nota en el tablero principal</q-tooltip>
                </q-btn>

                <q-btn
                  outline
                  dense
                  size="sm"
                  color="teal-3"
                  label="Abrir Nota"
                  icon-right="sym_r_open_in_new"
                  class="q-px-sm"
                  no-caps
                  @click="onSelectNote(item.note)"
                />
              </div>
            </div>
          </div>
        </div>

        <!-- Estado Vacío Individual -->
        <div v-else class="column items-center justify-center q-py-xl text-center">
          <q-avatar size="56px" color="grey-9" text-color="amber-4" class="q-mb-md">
            <q-icon name="sym_r_check_circle" size="32px" />
          </q-avatar>
          <div class="text-subtitle1 text-weight-bold text-white q-mb-xs">
            ¡Esta nota es única en el sistema!
          </div>
          <p class="text-caption text-grey-4" style="max-width: 440px">
            No se encontraron notas existentes con similitud semántica en la base de conocimiento. No hay riesgo de duplicidad de información.
          </p>
        </div>
      </q-card-section>

      <q-separator dark />

      <!-- Footer Actions -->
      <q-card-actions align="right" class="q-pa-sm q-px-md">
        <q-btn
          flat
          label="Cerrar"
          color="grey-4"
          v-close-popup
        />
      </q-card-actions>
    </q-card>
  </q-dialog>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { copyToClipboard, useQuasar } from 'quasar'
import { getSimilarMatrix, getSimilarNotes, searchSimilarDraft } from '../api/notes.api'
import type { Note, SimilarNoteItem, SimilarPairItem } from '../types/note.types'

const props = defineProps<{
  modelValue: boolean
  note?: Note | null
  draftContent?: string
  draftTitle?: string
  similarityMode?: 'auto' | 'ai' | 'algorithmic'
  isMatrixMode?: boolean
  searchQuery?: string
  categoryId?: string
}>()

const emit = defineEmits<{
  'update:modelValue': [val: boolean]
  'select-note': [note: Note]
  'filter-board': [keyword: string]
  'edit-note': [note: Note]
}>()

const $q = useQuasar()

const isOpen = computed({
  get: () => props.modelValue,
  set: (val: boolean) => emit('update:modelValue', val),
})

const currentMode = ref<'algorithmic' | 'ai'>('ai')

watch(
  () => props.similarityMode,
  (mode) => {
    if (mode === 'algorithmic' || mode === 'ai') {
      currentMode.value = mode
    }
  },
  { immediate: true }
)

function onModeChange() {
  loadSimilar()
}

const isLoading = ref(false)
const similarList = ref<SimilarNoteItem[]>([])
const matrixPairs = ref<SimilarPairItem[]>([])
const totalScanned = ref(0)
const targetNote = ref<Note | null>(null)

const targetTitle = computed(() => {
  return props.draftTitle || targetNote.value?.title || ''
})

const targetContent = computed(() => {
  return props.draftContent || targetNote.value?.content || ''
})

const targetAuthor = computed(() => {
  return targetNote.value?.user?.name || targetNote.value?.author_name || null
})

watch(
  () => props.modelValue,
  (val) => {
    if (val) {
      if (props.similarityMode === 'algorithmic' || props.similarityMode === 'ai') {
        currentMode.value = props.similarityMode
      }
      loadSimilar()
    }
  }
)

async function loadSimilar() {
  isLoading.value = true
  similarList.value = []
  matrixPairs.value = []
  targetNote.value = props.note || null

  try {
    if (props.isMatrixMode || (!props.note?.id && !props.draftContent)) {
      // MODO MATRIZ: Comparar tarjetas existentes entre sí
      const res = await getSimilarMatrix({
        mode: currentMode.value,
        search: props.searchQuery || undefined,
        category_id: props.categoryId || undefined,
        limit: 30,
      })
      matrixPairs.value = res.data || []
      totalScanned.value = res.total_scanned || 0
    } else if (props.note?.id) {
      // MODO NOTA INDIVIDUAL
      const res = await getSimilarNotes(props.note.id, 6, currentMode.value)
      similarList.value = res.data || []
      if (res.target_note) {
        targetNote.value = res.target_note
      }
    } else if (props.draftContent && props.draftContent.trim().length >= 2) {
      // MODO BORRADOR
      const res = await searchSimilarDraft({
        content: props.draftContent,
        title: props.draftTitle || undefined,
        limit: 8,
        mode: currentMode.value,
      })
      similarList.value = res.data || []
    }
  } catch (err: any) {
    console.error('Error al cargar notas similares:', err)
    $q.notify({
      type: 'warning',
      message: err?.response?.data?.message || 'No se pudieron consultar notas similares.',
      position: 'bottom-right',
    })
  } finally {
    isLoading.value = false
  }
}

function onFilterBoard(note: Note) {
  emit('filter-board', note.shortcut ? `#${note.shortcut}` : note.title || '')
  isOpen.value = false
}

function onEditNote(note: Note) {
  isOpen.value = false
  emit('edit-note', note)
}

function getScoreBadgeColor(score: number): string {
  if (score >= 75) return 'positive'
  if (score >= 50) return 'amber-9'
  return 'teal-8'
}

function getScoreColorHex(score: number): string {
  if (score >= 75) return '#10b981'
  if (score >= 50) return '#f59e0b'
  return '#14b8a6'
}

function hasEntities(n: Note): boolean {
  return Boolean(
    (n.categories && n.categories.length > 0) ||
    (n.tags && n.tags.length > 0)
  )
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
    return d.toLocaleDateString('es-ES', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' })
  } catch {
    return ''
  }
}

async function copyContent(text: string) {
  try {
    await copyToClipboard(text)
    $q.notify({
      type: 'positive',
      message: 'Texto de la nota copiado al portapapeles',
      timeout: 1500,
      position: 'bottom-right',
    })
  } catch {
    $q.notify({
      type: 'negative',
      message: 'No se pudo copiar el texto',
      position: 'bottom-right',
    })
  }
}

function onSelectNote(selected: Note) {
  isOpen.value = false
  emit('select-note', selected)
}
</script>

<style scoped lang="scss">
.xf-similar-dialog {
  background: #0d131f;
  border: 1px solid rgba(255, 255, 255, 0.12);
  width: 780px;
  max-width: 95vw;
  max-height: 85vh;
  border-radius: 12px;
}

.xf-similar-header {
  background: rgba(15, 23, 42, 0.95);
}

.reference-note-card {
  background: rgba(30, 41, 59, 0.5);
  border: 1px dashed rgba(251, 191, 36, 0.3);
}

.similar-card {
  background: rgba(30, 41, 59, 0.7);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-left-width: 4px;
  transition: transform 0.15s ease, background 0.15s ease;

  &:hover {
    background: rgba(30, 41, 59, 0.95);
    transform: translateY(-1px);
  }
}

.sub-note-box {
  background: rgba(15, 23, 42, 0.6);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 8px;
  height: 100%;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
}

.ai-reason-box {
  background: rgba(245, 158, 11, 0.12);
  border: 1px solid rgba(245, 158, 11, 0.25);
}

.border-top-subtle {
  border-top: 1px solid rgba(255, 255, 255, 0.06);
}
</style>
