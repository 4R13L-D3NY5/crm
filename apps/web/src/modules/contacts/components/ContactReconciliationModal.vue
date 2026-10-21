<template>
  <q-dialog
    :model-value="modelValue"
    persistent
    maximized
    transition-show="slide-up"
    transition-hide="slide-down"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <q-card class="bg-dark-page text-white column no-wrap" style="height: 100vh;">
      <!-- Header de la Barra Superior -->
      <q-bar class="bg-dark text-white q-py-lg q-px-md border-bottom-subtle">
        <div class="row items-center q-gutter-x-sm">
          <q-avatar size="34px" color="teal-9" text-color="teal-2" icon="sym_r_sync_saved_locally" />
          <div>
            <div class="text-subtitle1 text-bold leading-tight">
              Motor de Cotejo de Datos & Actualización Masiva de Estados
            </div>
            <div class="text-caption text-grey-4">
              Cruza planillas externas de Sistemas/Admisiones (.xlsx, .csv) y actualiza estados de matriculación en el CRM
            </div>
          </div>
        </div>
        <q-space />
        <q-btn flat round dense icon="sym_r_close" color="grey-4" v-close-popup />
      </q-bar>

      <!-- Cuerpo Principal con Stepper -->
      <div class="col scroll q-pa-md">
        <div class="row justify-center">
          <div class="col-12 col-xl-10">
            <q-stepper
              v-model="currentStep"
              dark
              animated
              header-nav
              color="primary"
              active-color="teal-4"
              done-color="positive"
              class="bg-dark-soft border-subtle rounded-borders"
            >
              <!-- PASO 1: SUBIR Y MAPEAR -->
              <q-step
                :name="1"
                title="Cargar Archivo y Mapear Columnas"
                icon="sym_r_upload_file"
                :done="currentStep > 1"
              >
                <!-- Selector de Reglas / Plantillas Guardadas -->
                <div class="row items-center justify-between q-mb-md bg-dark q-pa-md rounded-borders border-subtle">
                  <div class="row items-center q-gutter-x-sm">
                    <q-icon name="sym_r_bookmark" size="22px" color="amber-4" />
                    <div>
                      <div class="text-subtitle2 text-bold">Plantillas de Cotejo Guardadas</div>
                      <div class="text-caption text-grey-4">Selecciona una regla preconfigurada para auto-completar el mapeo</div>
                    </div>
                  </div>
                  <div class="row items-center q-gutter-x-sm" style="min-width: 300px;">
                    <q-select
                      v-model="selectedSavedRule"
                      outlined
                      dark
                      dense
                      emit-value
                      map-options
                      clearable
                      :options="savedRulesOptions"
                      label="Seleccionar regla guardada..."
                      class="col"
                      @update:model-value="applySavedRule"
                    >
                      <template #no-option>
                        <q-item dark>
                          <q-item-section class="text-grey-5">No hay reglas guardadas aún</q-item-section>
                        </q-item>
                      </template>
                    </q-select>
                    <q-btn
                      v-if="selectedSavedRule"
                      flat
                      round
                      dense
                      icon="sym_r_delete"
                      color="negative"
                      @click="removeSelectedRule"
                    >
                      <q-tooltip>Eliminar regla guardada</q-tooltip>
                    </q-btn>
                  </div>
                </div>

                <!-- Subir archivo Excel -->
                <div class="q-mb-md">
                  <q-file
                    v-model="excelFile"
                    outlined
                    dark
                    dense
                    clearable
                    accept=".xlsx, .xls, .csv"
                    label="Seleccionar reporte o planilla Excel (.xlsx, .csv)"
                    class="q-mb-sm"
                    @update:model-value="handleFileChange"
                  >
                    <template #prepend>
                      <q-icon name="sym_r_table_view" color="teal-4" />
                    </template>
                  </q-file>

                  <div
                    v-if="!excelFile && parsedRows.length === 0"
                    class="reconcile-dropzone q-pa-lg text-center text-grey-5 cursor-pointer rounded-borders"
                    @click="triggerFileInput"
                    @dragover.prevent
                    @drop.prevent="handleFileDrop"
                  >
                    <q-icon name="sym_r_cloud_upload" size="44px" color="teal-4" class="q-mb-sm" />
                    <div class="text-subtitle2 text-bold text-grey-3">
                      Haz clic o arrastra aquí tu archivo Excel (.xlsx) o CSV
                    </div>
                    <div class="text-caption text-grey-5">
                      Compatible con reportes de inscritos nuevos, postulantes, estudiantes y cobranzas
                    </div>
                  </div>
                </div>

                <!-- Información del archivo cargado -->
                <div v-if="parsedRows.length > 0" class="q-gutter-y-md">
                  <q-banner rounded dense class="bg-teal-10 text-teal-1">
                    <template #avatar>
                      <q-icon name="sym_r_check_circle" color="teal-3" size="24px" />
                    </template>
                    <div class="text-body2 text-bold">
                      Archivo cargado: {{ parsedRows.length }} registros detectados
                    </div>
                    <div class="text-caption">
                      Hoja seleccionada: <strong>{{ activeSheetName }}</strong> | Columnas detectadas: {{ detectedColumns.length }}
                    </div>
                  </q-banner>

                  <!-- Formulario de Mapeo de Columnas -->
                  <div class="bg-dark q-pa-md rounded-borders border-subtle">
                    <div class="text-subtitle2 text-bold text-teal-3 q-mb-md row items-center q-gutter-x-xs">
                      <q-icon name="sym_r_alt_route" size="18px" />
                      <span>1. Mapeo de Columnas del Archivo</span>
                    </div>

                    <div class="row q-col-gutter-md">
                      <div class="col-12 col-md-6">
                        <q-select
                          v-model="ruleForm.phone_column"
                          outlined
                          dark
                          dense
                          :options="detectedColumns"
                          label="Columna Teléfono / Celular *"
                          hint="Clave principal de coincidencia con WhatsApp"
                        />
                      </div>
                      <div class="col-12 col-md-6">
                        <q-select
                          v-model="ruleForm.name_column"
                          outlined
                          dark
                          dense
                          clearable
                          :options="detectedColumns"
                          label="Columna Nombre Completo"
                          hint="Para crear o actualizar el nombre del estudiante"
                        />
                      </div>
                      <div class="col-12 col-md-6">
                        <q-select
                          v-model="ruleForm.ci_column"
                          outlined
                          dark
                          dense
                          clearable
                          :options="detectedColumns"
                          label="Columna Documento de Identidad / CI"
                          hint="Búsqueda secundaria y registro en custom_fields"
                        />
                      </div>
                      <div class="col-12 col-md-6">
                        <q-select
                          v-model="ruleForm.email_column"
                          outlined
                          dark
                          dense
                          clearable
                          :options="detectedColumns"
                          label="Columna Correo Electrónico"
                          hint="Email personal o institucional"
                        />
                      </div>
                    </div>
                  </div>

                  <!-- Reglas de Actualización de Estados y Categorías -->
                  <div class="bg-dark q-pa-md rounded-borders border-subtle">
                    <div class="text-subtitle2 text-bold text-amber-3 q-mb-md row items-center q-gutter-x-xs">
                      <q-icon name="sym_r_rule" size="18px" />
                      <span>2. Acciones y Estados de Destino</span>
                    </div>

                    <div class="row q-col-gutter-md">
                      <!-- Estado de Destino -->
                      <div class="col-12 col-md-6">
                        <q-select
                          v-model="ruleForm.target_status_id"
                          outlined
                          dark
                          dense
                          emit-value
                          map-options
                          :options="statusOptions"
                          label="Estado al que se actualizará *"
                          hint="Ej: Matriculado (Ganado)"
                        >
                          <template #option="scope">
                            <q-item v-bind="scope.itemProps" dark>
                              <q-item-section avatar>
                                <q-badge :style="{ backgroundColor: scope.opt.color || '#10b981' }">
                                  {{ scope.opt.label }}
                                </q-badge>
                              </q-item-section>
                              <q-item-section>
                                <q-item-label>{{ scope.opt.label }}</q-item-label>
                              </q-item-section>
                            </q-item>
                          </template>
                        </q-select>
                      </div>

                      <!-- Modo de Categoría (Carrera / Facultad) -->
                      <div class="col-12 col-md-6">
                        <q-select
                          v-model="ruleForm.category_mode"
                          outlined
                          dark
                          dense
                          emit-value
                          map-options
                          :options="categoryModeOptions"
                          label="Vinculación a Categoría (Carrera)"
                          hint="Asigna la carrera del estudiante en el CRM"
                        />
                      </div>

                      <!-- Columna de Carrera (si es Dinámica) -->
                      <div v-if="ruleForm.category_mode === 'dynamic'" class="col-12 col-md-6">
                        <q-select
                          v-model="ruleForm.category_column"
                          outlined
                          dark
                          dense
                          :options="detectedColumns"
                          label="Columna con el Nombre de la Carrera *"
                          hint="Ej: nombre_carrera (Se cruzará automáticamente con el árbol)"
                        />
                      </div>

                      <!-- Categoría Fija (si es Fija) -->
                      <div v-if="ruleForm.category_mode === 'fixed'" class="col-12 col-md-6">
                        <q-select
                          v-model="ruleForm.fixed_category_id"
                          outlined
                          dark
                          dense
                          emit-value
                          map-options
                          :options="categoryOptions"
                          label="Seleccionar Categoría Fija *"
                        />
                      </div>

                      <!-- Etiqueta Automática -->
                      <div class="col-12 col-md-6">
                        <q-input
                          v-model="ruleForm.auto_tag"
                          outlined
                          dark
                          dense
                          label="Etiqueta automática (opcional)"
                          placeholder="ej: Inscrito 1-2027"
                          hint="Se adjuntará a todos los contactos procesados"
                        />
                      </div>

                      <!-- Switch para crear contactos nuevos si no existen -->
                      <div class="col-12 col-md-6 flex items-center">
                        <q-toggle
                          v-model="ruleForm.auto_create_unmatched"
                          dark
                          color="teal-4"
                          label="Crear contacto en el CRM si aún no existe"
                          left-label
                        />
                        <div class="text-caption text-grey-5 q-ml-sm">
                          (Recomendado: asegura que todos los inscritos queden registrados en el CRM)
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- Guardar como nueva regla -->
                  <div class="row items-center q-gutter-x-md q-pt-xs">
                    <q-checkbox
                      v-model="saveRuleChecked"
                      dark
                      color="teal-4"
                      label="Guardar esta configuración como regla reutilizable"
                    />
                    <q-input
                      v-if="saveRuleChecked"
                      v-model="saveRuleName"
                      outlined
                      dark
                      dense
                      placeholder="Nombre de la regla (ej: Inscritos Nuevos UNITEPC)"
                      style="min-width: 320px;"
                    />
                  </div>

                  <!-- Acciones del Paso 1 -->
                  <div class="row justify-end q-gutter-x-sm q-mt-lg">
                    <q-btn
                      unelevated
                      no-caps
                      color="teal-6"
                      icon-right="sym_r_arrow_forward"
                      label="Simular y Previsualizar Cotejo"
                      :loading="isPreviewLoading"
                      :disable="!ruleForm.phone_column || !ruleForm.target_status_id"
                      @click="runPreview"
                    />
                  </div>
                </div>
              </q-step>

              <!-- PASO 2: PREVISUALIZACIÓN Y COTEJO (DRY RUN) -->
              <q-step
                :name="2"
                title="Resultados del Cotejo y Simulación"
                icon="sym_r_flaky"
                :done="currentStep > 2"
              >
                <div v-if="previewResult" class="q-gutter-y-md">
                  <!-- Tarjetas KPI Resumen -->
                  <div class="row q-col-gutter-md">
                    <div class="col-12 col-sm-6 col-md-3">
                      <q-card dark class="bg-dark border-subtle q-pa-sm text-center">
                        <div class="text-caption text-grey-4 text-uppercase">Total Filas</div>
                        <div class="text-h4 text-bold text-white">{{ previewResult.summary.total_rows }}</div>
                        <div class="text-caption text-grey-5">En el archivo cargado</div>
                      </q-card>
                    </div>

                    <div class="col-12 col-sm-6 col-md-3">
                      <q-card dark class="bg-dark border-subtle q-pa-sm text-center">
                        <div class="text-caption text-positive text-uppercase text-bold">Coincidentes CRM</div>
                        <div class="text-h4 text-bold text-positive">{{ previewResult.summary.matched }}</div>
                        <div class="text-caption text-grey-4">Se actualizarán a {{ previewResult.target_status.name }}</div>
                      </q-card>
                    </div>

                    <div class="col-12 col-sm-6 col-md-3">
                      <q-card dark class="bg-dark border-subtle q-pa-sm text-center">
                        <div class="text-caption text-amber-4 text-uppercase text-bold">Nuevos a Crear</div>
                        <div class="text-h4 text-bold text-amber-4">{{ previewResult.summary.to_create }}</div>
                        <div class="text-caption text-grey-4">Contactos nuevos matriculados</div>
                      </q-card>
                    </div>

                    <div class="col-12 col-sm-6 col-md-3">
                      <q-card dark class="bg-dark border-subtle q-pa-sm text-center">
                        <div class="text-caption text-grey-4 text-uppercase">Omitidos / Inválidos</div>
                        <div class="text-h4 text-bold text-grey-4">
                          {{ previewResult.summary.unmatched + previewResult.summary.invalid }}
                        </div>
                        <div class="text-caption text-grey-5">Sin teléfono o no encontrados</div>
                      </q-card>
                    </div>
                  </div>

                  <!-- Tabla Detallada de Simulación -->
                  <div class="bg-dark rounded-borders border-subtle q-pa-sm">
                    <div class="row items-center justify-between q-pa-sm">
                      <div class="text-subtitle2 text-bold text-white">Detalle Fila por Fila</div>
                      <q-input
                        v-model="previewFilter"
                        outlined
                        dark
                        dense
                        placeholder="Buscar en simulación..."
                        class="q-ml-md"
                        style="min-width: 260px;"
                      >
                        <template #prepend><q-icon name="sym_r_search" size="18px" /></template>
                      </q-input>
                    </div>

                    <q-table
                      dark
                      dense
                      flat
                      :rows="previewResult.preview"
                      :columns="previewColumns"
                      :filter="previewFilter"
                      row-key="row_index"
                      :pagination="{ rowsPerPage: 10 }"
                      class="bg-transparent"
                    >
                      <template #body-cell-status="props">
                        <q-td :props="props">
                          <q-badge
                            v-if="props.row.status === 'matched'"
                            color="positive"
                            text-color="dark"
                            class="text-bold"
                          >
                            <q-icon name="sym_r_check_circle" size="12px" class="q-mr-xs" />
                            Coincidente CRM
                          </q-badge>
                          <q-badge
                            v-else-if="props.row.status === 'to_create'"
                            color="amber-4"
                            text-color="dark"
                            class="text-bold"
                          >
                            <q-icon name="sym_r_person_add" size="12px" class="q-mr-xs" />
                            Nuevo a Crear
                          </q-badge>
                          <q-badge
                            v-else
                            color="grey-7"
                            text-color="white"
                          >
                            Omitido
                          </q-badge>
                        </q-td>
                      </template>

                      <template #body-cell-action="props">
                        <q-td :props="props" class="text-caption font-mono">
                          {{ props.row.action }}
                        </q-td>
                      </template>
                    </q-table>
                  </div>

                  <!-- Acciones del Paso 2 -->
                  <div class="row justify-between items-center q-mt-lg">
                    <q-btn
                      flat
                      no-caps
                      color="grey-4"
                      icon="sym_r_arrow_back"
                      label="Modificar Mapeo"
                      @click="currentStep = 1"
                    />
                    <q-btn
                      unelevated
                      no-caps
                      color="positive"
                      text-color="dark"
                      icon-right="sym_r_published_with_changes"
                      label="Confirmar y Ejecutar Actualización Masiva"
                      :loading="isExecuting"
                      class="text-bold"
                      @click="runExecution"
                    />
                  </div>
                </div>
              </q-step>

              <!-- PASO 3: REPORTE DE RESULTADOS Y DESCARGA -->
              <q-step
                :name="3"
                title="Actualización Finalizada"
                icon="sym_r_task_alt"
              >
                <div v-if="executionResult" class="text-center q-pa-lg q-gutter-y-md">
                  <q-avatar size="72px" color="teal-9" text-color="positive" icon="sym_r_check_circle" />
                  <div class="text-h5 text-bold text-white">¡Cotejo y Actualización Finalizados!</div>
                  <div class="text-body1 text-grey-3">
                    {{ executionResult.message }}
                  </div>

                  <div class="row justify-center q-gutter-md q-my-md">
                    <q-chip color="teal-10" text-color="teal-2" size="lg" icon="sym_r_person_check">
                      {{ executionResult.updated_contacts }} contactos actualizados
                    </q-chip>
                    <q-chip color="amber-10" text-color="amber-2" size="lg" icon="sym_r_person_add">
                      {{ executionResult.created_contacts }} nuevos contactos creados
                    </q-chip>
                    <q-chip color="indigo-10" text-color="indigo-2" size="lg" icon="sym_r_chat">
                      {{ executionResult.updated_conversations }} chats actualizados
                    </q-chip>
                  </div>

                  <div class="row justify-center q-gutter-x-md q-pt-md">
                    <q-btn
                      outline
                      no-caps
                      color="positive"
                      icon="sym_r_download"
                      label="Descargar Planilla con Resultados (.xlsx)"
                      @click="downloadProcessedExcel"
                    />
                    <q-btn
                      unelevated
                      no-caps
                      color="primary"
                      label="Cerrar y Volver a Contactos"
                      v-close-popup
                    />
                  </div>
                </div>
              </q-step>
            </q-stepper>
          </div>
        </div>
      </div>
    </q-card>
  </q-dialog>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useQuasar } from 'quasar'
import { useQueryClient } from '@tanstack/vue-query'
import * as XLSX from 'xlsx'
import { http } from '@/shared/api/http'
import {
  previewReconciliation,
  executeReconciliation,
  getReconciliationRules,
  deleteReconciliationRule,
  type ReconciliationRule,
  type ReconciliationPreviewResult,
  type ReconciliationExecuteResult,
} from '../api/reconciliation.api'

const props = defineProps<{
  modelValue: boolean
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', val: boolean): void
}>()

const $q = useQuasar()
const queryClient = useQueryClient()

// Estados del Wizard
const currentStep = ref(1)
const excelFile = ref<File | null>(null)
const parsedRows = ref<any[]>([])
const activeSheetName = ref('')
const detectedColumns = ref<string[]>([])

// Reglas Guardadas
const savedRules = ref<ReconciliationRule[]>([])
const selectedSavedRule = ref<string | null>(null)
const saveRuleChecked = ref(false)
const saveRuleName = ref('')

// Estados y Categorías desde la API
const customStatuses = ref<any[]>([])
const categories = ref<any[]>([])

// Formulario de Mapeo
const ruleForm = ref<ReconciliationRule>({
  phone_column: '',
  name_column: '',
  ci_column: '',
  email_column: '',
  target_status_id: '',
  category_mode: 'dynamic',
  category_column: '',
  fixed_category_id: '',
  auto_create_unmatched: true,
  auto_tag: 'Inscrito',
})

// Resultados
const isPreviewLoading = ref(false)
const previewResult = ref<ReconciliationPreviewResult | null>(null)
const previewFilter = ref('')
const isExecuting = ref(false)
const executionResult = ref<ReconciliationExecuteResult | null>(null)

// Opciones de Selectores
const savedRulesOptions = computed(() => {
  return savedRules.value.map(r => ({
    label: r.name || 'Regla sin nombre',
    value: r.id,
  }))
})

const statusOptions = computed(() => {
  return customStatuses.value.map(s => ({
    label: s.name,
    value: s.id,
    color: s.color,
  }))
})

const categoryModeOptions = [
  { label: 'Dinámica por columna (Cruza por nombre de carrera)', value: 'dynamic' },
  { label: 'Categoría Fija (Asigna la misma a todos)', value: 'fixed' },
  { label: 'No vincular categoría', value: 'none' },
]

const categoryOptions = computed(() => {
  return categories.value.map(c => ({
    label: c.name,
    value: c.id,
  }))
})

const previewColumns = [
  { name: 'row_index', label: '#', field: 'row_index', sortable: true, align: 'left' as const },
  { name: 'input_name', label: 'Estudiante / Nombre', field: 'input_name', sortable: true, align: 'left' as const },
  { name: 'input_phone', label: 'Teléfono', field: 'input_phone', align: 'left' as const },
  { name: 'target_category', label: 'Carrera Detectada', field: 'target_category', align: 'left' as const },
  { name: 'status', label: 'Estado Cotejo', field: 'status', sortable: true, align: 'center' as const },
  { name: 'action', label: 'Acción Prevista en CRM', field: 'action', align: 'left' as const },
]

onMounted(async () => {
  await Promise.all([
    loadSavedRules(),
    loadCustomStatuses(),
    loadCategories(),
  ])
})

async function loadSavedRules() {
  try {
    savedRules.value = await getReconciliationRules()
  } catch {
    // Silencioso si falla
  }
}

async function loadCustomStatuses() {
  try {
    const res = await http.get('/custom-statuses')
    customStatuses.value = res.data?.data || res.data || []
    // Autoseleccionar "Matriculado" si existe
    const matriculado = customStatuses.value.find(s => s.name?.toLowerCase().includes('matriculado'))
    if (matriculado && !ruleForm.value.target_status_id) {
      ruleForm.value.target_status_id = matriculado.id
    }
  } catch {
    // Fallback
  }
}

async function loadCategories() {
  try {
    const res = await http.get('/categories')
    categories.value = res.data?.data || res.data || []
  } catch {
    // Fallback
  }
}

function triggerFileInput() {
  const el = document.querySelector('.q-file input[type="file"]') as HTMLInputElement
  if (el) el.click()
}

function handleFileDrop(e: DragEvent) {
  const files = e.dataTransfer?.files
  if (files && files.length > 0) {
    excelFile.value = files[0]
    handleFileChange(files[0])
  }
}

async function handleFileChange(file: File | null) {
  if (!file) {
    parsedRows.value = []
    detectedColumns.value = []
    return
  }

  try {
    const data = await file.arrayBuffer()
    const workbook = XLSX.read(data, { type: 'array' })
    const sheetName = workbook.SheetNames[0]
    activeSheetName.value = sheetName

    const worksheet = workbook.Sheets[sheetName]
    const jsonRows: any[] = XLSX.utils.sheet_to_json(worksheet, { defval: '' })

    if (jsonRows.length === 0) {
      $q.notify({ type: 'warning', message: 'El archivo Excel no contiene filas con datos.' })
      return
    }

    parsedRows.value = jsonRows
    const cols = Object.keys(jsonRows[0] || {})
    detectedColumns.value = cols

    // Auto-detectar columnas inteligentes
    autoDetectColumns(cols)
    $q.notify({
      type: 'positive',
      message: `Archivo procesado: ${jsonRows.length} registros y ${cols.length} columnas detectadas.`,
    })
  } catch (err: any) {
    $q.notify({ type: 'negative', message: 'Error al leer el archivo Excel: ' + err.message })
  }
}

function autoDetectColumns(cols: string[]) {
  for (const c of cols) {
    const lower = c.toLowerCase()
    if (!ruleForm.value.phone_column && /phone|celular|tel|movil|whatsapp/i.test(lower)) {
      ruleForm.value.phone_column = c
    }
    if (!ruleForm.value.name_column && /nombre|name|estudiante|alumno/i.test(lower)) {
      ruleForm.value.name_column = c
    }
    if (!ruleForm.value.ci_column && /ci|identidad|dni|documento|cedula/i.test(lower)) {
      ruleForm.value.ci_column = c
    }
    if (!ruleForm.value.email_column && /email|correo/i.test(lower)) {
      ruleForm.value.email_column = c
    }
    if (!ruleForm.value.category_column && /carrera|facultad|programa|categoria/i.test(lower)) {
      ruleForm.value.category_column = c
    }
  }
}

function applySavedRule(ruleId: string | null) {
  if (!ruleId) return
  const rule = savedRules.value.find(r => r.id === ruleId)
  if (!rule) return

  ruleForm.value.phone_column = rule.phone_column || ruleForm.value.phone_column
  ruleForm.value.name_column = rule.name_column || ruleForm.value.name_column
  ruleForm.value.ci_column = rule.ci_column || ruleForm.value.ci_column
  ruleForm.value.email_column = rule.email_column || ruleForm.value.email_column
  ruleForm.value.target_status_id = rule.target_status_id || ruleForm.value.target_status_id
  ruleForm.value.category_mode = rule.category_mode || ruleForm.value.category_mode
  ruleForm.value.category_column = rule.category_column || ruleForm.value.category_column
  ruleForm.value.fixed_category_id = rule.fixed_category_id || ruleForm.value.fixed_category_id
  ruleForm.value.auto_create_unmatched = rule.auto_create_unmatched !== undefined ? rule.auto_create_unmatched : true
  ruleForm.value.auto_tag = rule.auto_tag || ruleForm.value.auto_tag

  $q.notify({ type: 'info', message: `Plantilla "${rule.name}" aplicada.` })
}

async function removeSelectedRule() {
  if (!selectedSavedRule.value) return
  try {
    await deleteReconciliationRule(selectedSavedRule.value)
    $q.notify({ type: 'positive', message: 'Regla eliminada exitosamente.' })
    selectedSavedRule.value = null
    await loadSavedRules()
  } catch {
    $q.notify({ type: 'negative', message: 'Error al eliminar la regla.' })
  }
}

async function runPreview() {
  if (!ruleForm.value.phone_column || !ruleForm.value.target_status_id) {
    $q.notify({ type: 'warning', message: 'Debes seleccionar al menos la columna de teléfono y el estado destino.' })
    return
  }

  isPreviewLoading.value = true
  try {
    previewResult.value = await previewReconciliation({
      rows: parsedRows.value,
      rule: ruleForm.value,
    })
    currentStep.value = 2
  } catch (err: any) {
    $q.notify({ type: 'negative', message: err?.response?.data?.message || 'Error durante la simulación de cotejo.' })
  } finally {
    isPreviewLoading.value = false
  }
}

async function runExecution() {
  isExecuting.value = true
  try {
    executionResult.value = await executeReconciliation({
      rows: parsedRows.value,
      rule: ruleForm.value,
      save_rule: saveRuleChecked.value,
      rule_name: saveRuleName.value || 'Regla de Cotejo',
    })

    await queryClient.invalidateQueries({ queryKey: ['contacts'] })
    await queryClient.invalidateQueries({ queryKey: ['conversations'] })
    await loadSavedRules()

    currentStep.value = 3
    $q.notify({
      type: 'positive',
      message: executionResult.value.message,
      timeout: 3500,
    })
  } catch (err: any) {
    $q.notify({ type: 'negative', message: err?.response?.data?.message || 'Error al ejecutar la actualización masiva.' })
  } finally {
    isExecuting.value = false
  }
}

function downloadProcessedExcel() {
  if (!executionResult.value?.processed_rows) return

  const ws = XLSX.utils.json_to_sheet(executionResult.value.processed_rows)
  const wb = XLSX.utils.book_new()
  XLSX.utils.book_append_sheet(wb, ws, 'Resultado Cotejo')
  XLSX.writeFile(wb, `resultado_cotejo_matriculados_${new Date().toISOString().slice(0, 10)}.xlsx`)
}
</script>

<style scoped lang="scss">
.reconcile-dropzone {
  border: 2px dashed rgba(255, 255, 255, 0.2);
  background: rgba(255, 255, 255, 0.03);
  transition: all 0.2s ease;

  &:hover {
    border-color: #10b981;
    background: rgba(16, 185, 129, 0.05);
  }
}

.border-subtle {
  border: 1px solid rgba(255, 255, 255, 0.08);
}

.border-bottom-subtle {
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}
</style>
