<template>
  <q-dialog
    v-model="isOpen"
    persistent
    maximized-mobile
    transition-show="fade"
    transition-hide="fade"
  >
    <q-card class="xf-import-dialog-card column no-wrap" style="width: 1080px; max-width: 96vw; height: 90vh;">
      <!-- Header -->
      <q-card-section class="xf-dialog-header row items-center justify-between q-py-sm q-px-md">
        <div class="row items-center q-gutter-x-sm">
          <q-avatar size="34px" color="teal-9" text-color="teal-2">
            <q-icon name="sym_r_cloud_upload" size="20px" />
          </q-avatar>
          <div>
            <div class="text-subtitle1 text-weight-bold text-white">
              Importar Contenido & Base de Conocimiento
            </div>
            <div class="text-caption text-grey-4" style="font-size: 0.73rem">
              Sube respaldos de Google Keep o documentos institucionales para el equipo y el copiloto IA
            </div>
          </div>
        </div>

        <q-btn
          flat
          round
          dense
          size="sm"
          color="grey-4"
          icon="sym_r_close"
          v-close-popup
        />
      </q-card-section>

      <!-- Pestañas de modo: Keep ZIP vs Documentos -->
      <div class="xf-dialog-tabs q-px-md q-pt-xs bg-slate-900">
        <q-tabs
          v-model="activeTab"
          dense
          no-caps
          align="left"
          active-color="teal-4"
          indicator-color="teal-4"
          class="text-grey-4"
        >
          <q-tab name="keep" icon="sym_r_folder_zip" label="Google Keep (ZIP)" />
          <q-tab name="documents" icon="sym_r_upload_file" label="Cargar Documento (.pdf, .docx, .xlsx, .txt)" />
        </q-tabs>
      </div>

      <q-separator dark />

      <!-- Body / Contenido -->
      <q-card-section class="col scroll q-pa-md">
        <!-- ============================================== -->
        <!-- TAB 1: GOOGLE KEEP ZIP PREVIEW & SHORTCUTS     -->
        <!-- ============================================== -->
        <div v-if="activeTab === 'keep'" class="column q-gutter-y-md">
          <!-- Zona de Subida si aún no hay notas cargadas -->
          <div
            v-if="parsedNotes.length === 0"
            class="xf-dropzone column items-center justify-center q-pa-xl text-center cursor-pointer"
            :class="{ 'xf-dropzone--dragging': isDragging }"
            @dragover.prevent="isDragging = true"
            @dragleave.prevent="isDragging = false"
            @drop.prevent="handleZipDrop"
            @click="triggerZipFileInput"
          >
            <input
              ref="zipFileInput"
              type="file"
              accept=".zip,application/zip"
              class="hidden"
              @change="handleZipFileSelected"
            />

            <q-spinner-dots v-if="isLoadingPreview" size="50px" color="teal-4" />
            <template v-else>
              <q-avatar size="64px" color="teal-10" text-color="teal-3" class="q-mb-md">
                <q-icon name="sym_r_drive_folder_upload" size="36px" />
              </q-avatar>
              <div class="text-h6 text-weight-bold text-white q-mb-xs">
                Arrastra aquí el archivo ZIP de Google Takeout
              </div>
              <p class="text-caption text-grey-4 q-mb-md" style="max-width: 500px">
                Procesaremos automáticamente todos los JSON de Keep, extrayendo títulos, textos, listas y etiquetas, y pre-asignaremos atajos rápidos inteligentes.
              </p>
              <q-btn
                unelevated
                color="teal-7"
                icon="sym_r_file_open"
                label="Seleccionar archivo ZIP"
                class="q-px-lg text-weight-bold"
              />
              <div class="text-caption text-grey-5 q-mt-sm">
                Ejemplo: takeout-20261022T115530Z-1-001.zip (hasta 50 MB)
              </div>
            </template>
          </div>

          <!-- Vista de Previsualización y Asignación de Atajos -->
          <div v-else class="column q-gutter-y-sm">
            <!-- Barra de Resumen y Herramientas -->
            <div class="row items-center justify-between q-col-gutter-sm bg-slate-800 q-pa-sm rounded-borders">
              <!-- Stats -->
              <div class="col-12 col-md-auto row items-center q-gutter-x-sm">
                <q-chip dense color="teal-9" text-color="teal-2" icon="sym_r_check_circle">
                  {{ selectedCount }} de {{ parsedNotes.length }} seleccionadas
                </q-chip>
                <q-chip dense color="blue-9" text-color="blue-2" icon="sym_r_tag">
                  {{ notesWithShortcutCount }} con atajo rápido (#)
                </q-chip>
                <q-btn
                  flat
                  dense
                  size="sm"
                  color="grey-4"
                  icon="sym_r_restart_alt"
                  label="Cargar otro ZIP"
                  @click="resetKeepImport"
                />
              </div>

              <!-- Filtro de Búsqueda -->
              <div class="col-12 col-sm-6 col-md-4">
                <q-input
                  v-model="keepSearch"
                  dense
                  outlined
                  dark
                  placeholder="Filtrar notas por título, atajo o texto..."
                  class="xf-dense-search"
                >
                  <template #prepend>
                    <q-icon name="sym_r_search" size="16px" color="teal-3" />
                  </template>
                  <template #append v-if="keepSearch">
                    <q-icon name="sym_r_close" size="14px" class="cursor-pointer" @click="keepSearch = ''" />
                  </template>
                </q-input>
              </div>

              <!-- Opciones Rápidas -->
              <div class="col-12 col-sm-6 col-md-auto row items-center q-gutter-x-xs">
                <q-btn
                  flat
                  dense
                  size="sm"
                  color="teal-3"
                  icon="sym_r_select_all"
                  label="Todas"
                  @click="selectAllNotes(true)"
                />
                <q-btn
                  flat
                  dense
                  size="sm"
                  color="grey-4"
                  icon="sym_r_deselect"
                  label="Ninguna"
                  @click="selectAllNotes(false)"
                />
                <q-btn
                  outline
                  dense
                  size="sm"
                  color="teal-4"
                  icon="sym_r_auto_fix_high"
                  label="Re-generar Atajos"
                  class="q-px-xs"
                  @click="regenerateShortcuts"
                >
                  <q-tooltip class="bg-dark text-white">Reasigna atajos inteligentes basados en títulos limpios</q-tooltip>
                </q-btn>
              </div>
            </div>

            <!-- Switch de Indexación IA -->
            <div class="row items-center justify-between bg-dark q-px-sm q-py-xs rounded-borders border-subtle">
              <div class="row items-center q-gutter-x-xs">
                <q-icon name="sym_r_neurology" color="purple-3" size="18px" />
                <span class="text-caption text-weight-medium text-white">
                  Indexación en Base de Conocimiento de IA (RAG)
                </span>
                <span class="text-caption text-grey-4">
                  — Habilita respuestas automáticas y sugerencias del copiloto
                </span>
              </div>
              <q-toggle v-model="indexAi" color="purple-4" dense />
            </div>

            <!-- Tabla de Previsualización y Edición de Atajos -->
            <div class="xf-table-container">
              <q-virtual-scroll
                style="max-height: 52vh"
                :items="filteredNotes"
                separator
                v-slot="{ item, index }"
              >
                <div
                  :key="item.temp_id || index"
                  class="xf-note-row row items-center q-py-xs q-px-sm"
                  :class="{ 'xf-note-row--selected': item.selected }"
                >
                  <!-- Checkbox Selección -->
                  <div class="col-auto q-mr-sm">
                    <q-checkbox v-model="item.selected" dense color="teal-4" />
                  </div>

                  <!-- Indicador de Color Keep -->
                  <div class="col-auto q-mr-sm">
                    <div
                      class="color-pill"
                      :style="{ backgroundColor: item.color || '#1e293b' }"
                      :title="`Color Keep: ${item.keep_color}`"
                    />
                  </div>

                  <!-- Título Editable -->
                  <div class="col-12 col-sm-4 q-pr-sm">
                    <q-input
                      v-model="item.title"
                      dense
                      outlined
                      dark
                      placeholder="Título de la nota"
                      class="text-caption"
                      :input-style="{ fontWeight: '600' }"
                    />
                  </div>

                  <!-- Atajo Rápido Editable (#) -->
                  <div class="col-12 col-sm-3 q-pr-sm">
                    <q-input
                      v-model="item.shortcut"
                      dense
                      outlined
                      dark
                      prefix="#"
                      placeholder="ej: saludo, becas"
                      class="text-caption text-teal-3 font-mono"
                      :input-style="{ color: '#2dd4bf', fontWeight: 'bold' }"
                      @update:model-value="(val) => item.shortcut = cleanShortcutInput(val)"
                    >
                      <template #prepend>
                        <q-icon name="sym_r_bolt" size="14px" color="teal-4" />
                      </template>
                      <template #append v-if="item.shortcut">
                        <q-icon name="sym_r_close" size="12px" class="cursor-pointer" @click="item.shortcut = ''" />
                      </template>
                    </q-input>
                  </div>

                  <!-- Snippet de Contenido & Preview Button -->
                  <div class="col-12 col-sm row items-center justify-between">
                    <span class="text-caption text-grey-4 ellipsis q-pr-sm" style="max-width: 200px">
                      {{ item.content ? item.content.slice(0, 70) : '(Sin texto)' }}
                    </span>

                    <div class="row items-center q-gutter-x-xs no-wrap">
                      <q-btn
                        flat
                        round
                        dense
                        size="sm"
                        color="grey-4"
                        icon="sym_r_visibility"
                        @click="openFullPreview(item)"
                      >
                        <q-tooltip class="bg-dark text-white">Ver contenido completo</q-tooltip>
                      </q-btn>

                      <q-btn
                        flat
                        round
                        dense
                        size="sm"
                        :color="item.is_pinned ? 'amber-4' : 'grey-6'"
                        icon="sym_r_push_pin"
                        @click="item.is_pinned = !item.is_pinned"
                      >
                        <q-tooltip>{{ item.is_pinned ? 'Nota fijada' : 'Fijar nota' }}</q-tooltip>
                      </q-btn>
                    </div>
                  </div>
                </div>
              </q-virtual-scroll>
            </div>
          </div>
        </div>

        <!-- ============================================== -->
        <!-- TAB 2: SUBIR DOCUMENTOS (.PDF, .DOCX, ETC.)   -->
        <!-- ============================================== -->
        <div v-else class="column q-gutter-y-md">
          <div
            v-if="!selectedDocFile"
            class="xf-dropzone column items-center justify-center q-pa-xl text-center cursor-pointer"
            :class="{ 'xf-dropzone--dragging': isDraggingDoc }"
            @dragover.prevent="isDraggingDoc = true"
            @dragleave.prevent="isDraggingDoc = false"
            @drop.prevent="handleDocDrop"
            @click="triggerDocFileInput"
          >
            <input
              ref="docFileInput"
              type="file"
              accept=".pdf,.docx,.xlsx,.txt,.csv"
              class="hidden"
              @change="handleDocFileSelected"
            />
            <q-avatar size="64px" color="blue-10" text-color="blue-3" class="q-mb-md">
              <q-icon name="sym_r_upload_file" size="36px" />
            </q-avatar>
            <div class="text-h6 text-weight-bold text-white q-mb-xs">
              Arrastra o selecciona un documento institucional
            </div>
            <p class="text-caption text-grey-4 q-mb-md" style="max-width: 480px">
              Admitimos <strong>.pdf</strong>, <strong>.docx</strong> (Word), <strong>.xlsx</strong> (Excel), y <strong>.txt</strong>. El texto se extraerá y se incorporará inmediatamente a las notas y al Copiloto RAG de IA.
            </p>
            <q-btn
              unelevated
              color="primary"
              icon="sym_r_file_open"
              label="Seleccionar Archivo"
              class="q-px-lg text-weight-bold"
            />
          </div>

          <!-- Formulario de Configuración del Documento Seleccionado -->
          <div v-else class="xf-doc-config-card q-pa-md rounded-borders bg-slate-900 border-subtle column q-gutter-y-md">
            <!-- Archivo Seleccionado Bar -->
            <div class="row items-center justify-between bg-slate-800 q-pa-sm rounded-borders">
              <div class="row items-center q-gutter-x-sm">
                <q-avatar size="32px" :color="getFileBadgeColor(selectedDocFile.name)" text-color="white">
                  <q-icon :name="getFileIcon(selectedDocFile.name)" size="18px" />
                </q-avatar>
                <div>
                  <div class="text-subtitle2 text-weight-bold text-white">
                    {{ selectedDocFile.name }}
                  </div>
                  <div class="text-caption text-grey-4">
                    {{ formatFileSize(selectedDocFile.size) }} • {{ getFileExtension(selectedDocFile.name).toUpperCase() }}
                  </div>
                </div>
              </div>

              <q-btn
                flat
                dense
                size="sm"
                color="grey-4"
                icon="sym_r_close"
                label="Cambiar archivo"
                @click="selectedDocFile = null"
              />
            </div>

            <!-- Título y Atajo Rápido -->
            <div class="row q-col-gutter-md">
              <div class="col-12 col-md-7">
                <label class="text-caption text-grey-4 text-weight-medium q-mb-xs block">
                  Título de la Nota / Documento
                </label>
                <q-input
                  v-model="docForm.title"
                  dense
                  outlined
                  dark
                  placeholder="Ej: Reglamento de Becas UNITEPC 2026"
                />
              </div>

              <div class="col-12 col-md-5">
                <label class="text-caption text-grey-4 text-weight-medium q-mb-xs block">
                  Atajo Rápido (#) para el Chat
                </label>
                <q-input
                  v-model="docForm.shortcut"
                  dense
                  outlined
                  dark
                  prefix="#"
                  placeholder="ej: becas, reglamento"
                  class="text-teal-3 font-mono"
                  :input-style="{ color: '#2dd4bf', fontWeight: 'bold' }"
                  @update:model-value="(val) => docForm.shortcut = cleanShortcutInput(val)"
                >
                  <template #prepend>
                    <q-icon name="sym_r_bolt" size="16px" color="teal-4" />
                  </template>
                </q-input>
              </div>
            </div>

            <!-- Opciones Adicionales -->
            <div class="row items-center justify-between q-col-gutter-sm bg-slate-800 q-pa-sm rounded-borders">
              <div class="col-12 col-sm-auto row items-center q-gutter-x-sm">
                <span class="text-caption text-grey-4">Color de tarjeta:</span>
                <div class="row items-center q-gutter-x-xs">
                  <div
                    v-for="c in colorPalette"
                    :key="c"
                    class="color-circle"
                    :style="{ backgroundColor: c }"
                    :class="{ 'color-circle--active': docForm.color === c }"
                    @click="docForm.color = c"
                  />
                </div>
              </div>

              <div class="col-12 col-sm-auto row items-center q-gutter-x-md">
                <q-checkbox v-model="docForm.is_pinned" dense dark color="amber-4" label="Fijar al inicio" />
                <q-toggle v-model="docForm.index_ai" dense dark color="purple-4" label="Indexar en IA (RAG)" />
              </div>
            </div>
          </div>
        </div>
      </q-card-section>

      <q-separator dark />

      <!-- Footer de Acciones -->
      <q-card-section class="xf-dialog-footer row items-center justify-between q-py-sm q-px-md bg-slate-900">
        <div class="text-caption text-grey-4">
          <span v-if="activeTab === 'keep' && parsedNotes.length > 0">
            <strong>{{ selectedCount }}</strong> notas listas para importar
          </span>
          <span v-else-if="activeTab === 'documents' && selectedDocFile">
            Listo para procesar e indexar en la Base de Conocimiento
          </span>
          <span v-else>
            Selecciona un archivo para continuar
          </span>
        </div>

        <div class="row items-center q-gutter-x-sm">
          <q-btn
            flat
            no-caps
            label="Cancelar"
            color="grey-4"
            v-close-popup
          />

          <!-- Botón de Importar Keep -->
          <q-btn
            v-if="activeTab === 'keep' && parsedNotes.length > 0"
            unelevated
            color="teal-7"
            icon="sym_r_cloud_done"
            :loading="isImporting"
            :disable="selectedCount === 0"
            :label="`Importar ${selectedCount} Notas`"
            class="text-weight-bold"
            @click="commitKeepImport"
          />

          <!-- Botón de Subir Documento -->
          <q-btn
            v-if="activeTab === 'documents' && selectedDocFile"
            unelevated
            color="primary"
            icon="sym_r_file_upload"
            :loading="isUploadingDoc"
            label="Subir e Ingerir Documento"
            class="text-weight-bold"
            @click="commitDocUpload"
          />
        </div>
      </q-card-section>
    </q-card>

    <!-- Modal Auxiliar de Previsualización Completa de Nota -->
    <q-dialog v-model="isPreviewModalOpen">
      <q-card class="bg-slate-900 text-white border-subtle" style="width: 580px; max-width: 90vw;">
        <q-card-section class="row items-center justify-between q-py-sm">
          <div class="text-subtitle1 text-weight-bold">{{ previewNoteItem?.title }}</div>
          <q-btn flat round dense icon="sym_r_close" v-close-popup />
        </q-card-section>
        <q-separator dark />
        <q-card-section class="q-pa-md scroll" style="max-height: 60vh">
          <div class="row items-center q-gutter-x-xs q-mb-sm">
            <q-badge v-if="previewNoteItem?.shortcut" color="teal-9" text-color="teal-2">
              #{{ previewNoteItem?.shortcut }}
            </q-badge>
            <q-badge color="grey-8 text-grey-3">
              Archivo: {{ previewNoteItem?.source_file }}
            </q-badge>
          </div>
          <div class="text-body2 text-grey-2" style="white-space: pre-wrap; line-height: 1.6">
            {{ previewNoteItem?.content }}
          </div>
        </q-card-section>
      </q-card>
    </q-dialog>
  </q-dialog>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useQuasar } from 'quasar'
import { importKeepBatch, previewKeepZip, uploadKnowledgeDocument } from '../api/notes.api'
import type { KeepParsedNote } from '../types/note.types'

const props = defineProps<{
  modelValue: boolean
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', value: boolean): void
  (e: 'imported'): void
}>()

const $q = useQuasar()

const isOpen = computed({
  get: () => props.modelValue,
  set: (val) => emit('update:modelValue', val),
})

const activeTab = ref<'keep' | 'documents'>('keep')

// Paleta de colores institucionales
const colorPalette = [
  '#1e293b', // Slate default
  '#ef4444', // Red
  '#f97316', // Orange
  '#eab308', // Yellow
  '#22c55e', // Green
  '#14b8a6', // Teal
  '#06b6d4', // Cyan
  '#3b82f6', // Blue
  '#a855f7', // Purple
  '#ec4899', // Pink
]

// ==========================================
// ESTADO GOOGLE KEEP
// ==========================================
const isDragging = ref(false)
const isLoadingPreview = ref(false)
const isImporting = ref(false)
const parsedNotes = ref<KeepParsedNote[]>([])
const keepSearch = ref('')
const indexAi = ref(true)
const zipFileInput = ref<HTMLInputElement | null>(null)

// Modal de lectura completa
const isPreviewModalOpen = ref(false)
const previewNoteItem = ref<KeepParsedNote | null>(null)

function openFullPreview(item: KeepParsedNote) {
  previewNoteItem.value = item
  isPreviewModalOpen.value = true
}

const filteredNotes = computed(() => {
  if (!keepSearch.value.trim()) {
    return parsedNotes.value
  }
  const q = keepSearch.value.toLowerCase()
  return parsedNotes.value.filter(
    (n) =>
      n.title.toLowerCase().includes(q) ||
      (n.shortcut && n.shortcut.toLowerCase().includes(q)) ||
      n.content.toLowerCase().includes(q)
  )
})

const selectedCount = computed(() => {
  return parsedNotes.value.filter((n) => n.selected).length
})

const notesWithShortcutCount = computed(() => {
  return parsedNotes.value.filter((n) => !!n.shortcut).length
})

function cleanShortcutInput(val: string | number | null): string {
  if (!val) return ''
  let str = String(val).toLowerCase()
  str = str.replace(/^#+/, '') // Quitar prefijo #
  str = str.normalize('NFD').replace(/[\u0300-\u036f]/g, '') // Quitar tildes
  str = str.replace(/[^a-z0-9_-]/g, '_') // Caracteres válidos
  return str.slice(0, 30)
}

function selectAllNotes(status: boolean) {
  parsedNotes.value.forEach((n) => (n.selected = status))
}

function regenerateShortcuts() {
  parsedNotes.value.forEach((n) => {
    if (!n.shortcut || n.shortcut.length < 3) {
      n.shortcut = cleanShortcutInput(n.title.slice(0, 20).replace(/\s+/g, '_'))
    }
  })
  $q.notify({
    type: 'positive',
    message: 'Atajos inteligentes actualizados.',
    timeout: 1200,
    position: 'bottom-right',
  })
}

function triggerZipFileInput() {
  zipFileInput.value?.click()
}

function handleZipFileSelected(e: Event) {
  const target = e.target as HTMLInputElement
  if (target.files && target.files.length > 0) {
    processZipFile(target.files[0])
  }
}

function handleZipDrop(e: DragEvent) {
  isDragging.value = false
  if (e.dataTransfer?.files && e.dataTransfer.files.length > 0) {
    const file = e.dataTransfer.files[0]
    if (file.name.toLowerCase().endsWith('.zip')) {
      processZipFile(file)
    } else {
      $q.notify({
        type: 'warning',
        message: 'Por favor sube un archivo con extensión .ZIP',
        position: 'bottom-right',
      })
    }
  }
}

async function processZipFile(file: File) {
  isLoadingPreview.value = true
  try {
    const res = await previewKeepZip(file)
    parsedNotes.value = res.data.notes || []
    $q.notify({
      type: 'positive',
      message: `¡Se detectaron ${res.data.total_detected} notas en el archivo ZIP!`,
      position: 'bottom-right',
      timeout: 2500,
    })
  } catch (err: any) {
    $q.notify({
      type: 'negative',
      message: err?.response?.data?.message || 'Error al procesar el archivo ZIP.',
      position: 'bottom-right',
    })
  } finally {
    isLoadingPreview.value = false
  }
}

function resetKeepImport() {
  parsedNotes.value = []
  keepSearch.value = ''
}

async function commitKeepImport() {
  const selected = parsedNotes.value.filter((n) => n.selected)
  if (selected.length === 0) return

  isImporting.value = true
  try {
    const res = await importKeepBatch(selected, indexAi.value)
    $q.notify({
      type: 'positive',
      message: `Importadas con éxito ${res.imported_count} notas (${res.ai_chunks_created} fragmentos para IA).`,
      position: 'bottom-right',
      timeout: 3500,
    })
    emit('imported')
    isOpen.value = false
    resetKeepImport()
  } catch (err: any) {
    $q.notify({
      type: 'negative',
      message: err?.response?.data?.message || 'Error al guardar notas en el servidor.',
      position: 'bottom-right',
    })
  } finally {
    isImporting.value = false
  }
}

// ==========================================
// ESTADO SUBIDA DE DOCUMENTOS
// ==========================================
const isDraggingDoc = ref(false)
const isUploadingDoc = ref(false)
const docFileInput = ref<HTMLInputElement | null>(null)
const selectedDocFile = ref<File | null>(null)

const docForm = ref({
  title: '',
  shortcut: '',
  color: '#1e293b',
  is_pinned: false,
  index_ai: true,
})

function triggerDocFileInput() {
  docFileInput.value?.click()
}

function handleDocFileSelected(e: Event) {
  const target = e.target as HTMLInputElement
  if (target.files && target.files.length > 0) {
    setupDocFile(target.files[0])
  }
}

function handleDocDrop(e: DragEvent) {
  isDraggingDoc.value = false
  if (e.dataTransfer?.files && e.dataTransfer.files.length > 0) {
    setupDocFile(e.dataTransfer.files[0])
  }
}

function setupDocFile(file: File) {
  selectedDocFile.value = file
  const baseName = file.name.substring(0, file.name.lastIndexOf('.')) || file.name
  docForm.value.title = baseName.replace(/[_-]/g, ' ')
  docForm.value.shortcut = cleanShortcutInput(baseName.slice(0, 15))
  docForm.value.color = getExtensionDefaultColor(getFileExtension(file.name))
}

function getFileExtension(filename: string): string {
  return filename.split('.').pop()?.toLowerCase() || ''
}

function getFileIcon(filename: string): string {
  const ext = getFileExtension(filename)
  if (ext === 'pdf') return 'sym_r_picture_as_pdf'
  if (ext === 'docx') return 'sym_r_description'
  if (ext === 'xlsx') return 'sym_r_table_view'
  return 'sym_r_text_snippet'
}

function getFileBadgeColor(filename: string): string {
  const ext = getFileExtension(filename)
  if (ext === 'pdf') return 'red-8'
  if (ext === 'docx') return 'blue-8'
  if (ext === 'xlsx') return 'green-8'
  return 'grey-8'
}

function getExtensionDefaultColor(ext: string): string {
  if (ext === 'pdf') return '#ef4444'
  if (ext === 'docx') return '#3b82f6'
  if (ext === 'xlsx') return '#22c55e'
  return '#1e293b'
}

function formatFileSize(bytes: number): string {
  if (bytes < 1024) return bytes + ' B'
  if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB'
  return (bytes / 1048576).toFixed(1) + ' MB'
}

async function commitDocUpload() {
  if (!selectedDocFile.value) return

  isUploadingDoc.value = true
  try {
    const formData = new FormData()
    formData.append('file', selectedDocFile.value)
    if (docForm.value.title) formData.append('title', docForm.value.title)
    if (docForm.value.shortcut) formData.append('shortcut', docForm.value.shortcut)
    if (docForm.value.color) formData.append('color', docForm.value.color)
    formData.append('is_pinned', docForm.value.is_pinned ? '1' : '0')
    formData.append('index_ai', docForm.value.index_ai ? '1' : '0')

    await uploadKnowledgeDocument(formData)

    $q.notify({
      type: 'positive',
      message: 'Documento procesado e incorporado a las notas y al Copiloto IA con éxito.',
      position: 'bottom-right',
      timeout: 3000,
    })

    emit('imported')
    isOpen.value = false
    selectedDocFile.value = null
  } catch (err: any) {
    $q.notify({
      type: 'negative',
      message: err?.response?.data?.message || 'Error al procesar el documento.',
      position: 'bottom-right',
    })
  } finally {
    isUploadingDoc.value = false
  }
}
</script>

<style scoped lang="scss">
.xf-import-dialog-card {
  background: #090d16;
  border: 1px solid rgba(255, 255, 255, 0.12);
  border-radius: 16px;
  overflow: hidden;
}

.xf-dialog-header {
  background: rgba(15, 23, 42, 0.95);
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

.xf-dialog-tabs {
  background: #0f172a;
}

.xf-dropzone {
  border: 2px dashed rgba(20, 184, 166, 0.4);
  background: rgba(15, 23, 42, 0.6);
  border-radius: 16px;
  transition: all 0.25s ease;

  &:hover,
  &--dragging {
    border-color: #2dd4bf;
    background: rgba(20, 184, 166, 0.1);
    transform: translateY(-2px);
  }
}

.xf-table-container {
  background: rgba(15, 23, 42, 0.7);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 12px;
  overflow: hidden;
}

.xf-note-row {
  border-bottom: 1px solid rgba(255, 255, 255, 0.05);
  transition: background 0.15s ease;

  &:hover {
    background: rgba(255, 255, 255, 0.03);
  }

  &--selected {
    background: rgba(20, 184, 166, 0.06);
  }
}

.color-pill {
  width: 14px;
  height: 14px;
  border-radius: 50%;
  border: 1px solid rgba(255, 255, 255, 0.3);
}

.color-circle {
  width: 22px;
  height: 22px;
  border-radius: 50%;
  cursor: pointer;
  border: 2px solid transparent;
  transition: transform 0.15s ease;

  &:hover {
    transform: scale(1.15);
  }

  &--active {
    border-color: #ffffff;
    box-shadow: 0 0 8px rgba(255, 255, 255, 0.5);
  }
}

.border-subtle {
  border: 1px solid rgba(255, 255, 255, 0.08);
}
</style>
