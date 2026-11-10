<template>
  <q-dialog
    :model-value="modelValue"
    persistent
    transition-show="scale"
    transition-hide="scale"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <q-card class="bulk-classifier-card text-white">
      <!-- Barra superior estilizada con gradiente -->
      <div class="bulk-header-bar q-pa-md">
        <div class="row items-center justify-between">
          <div class="row items-center q-gutter-x-sm">
            <q-avatar size="36px" color="teal-9" text-color="teal-2">
              <q-icon name="sym_r_auto_fix_high" size="20px" />
            </q-avatar>
            <div>
              <div class="text-subtitle1 text-weight-bold text-white">
                Acción Masiva por Contenido de Texto
              </div>
              <div class="text-caption text-grey-4">
                Segmenta y clasifica en lote contactos y chats según lo que se habló.
              </div>
            </div>
          </div>

          <q-btn
            flat
            round
            dense
            icon="sym_r_close"
            color="grey-4"
            size="sm"
            :disable="isExecuting"
            @click="onClose"
          />
        </div>
      </div>

      <q-separator dark color="grey-8" />

      <q-card-section class="q-pa-md q-gutter-y-md bulk-scrollable-body">
        <!-- 1. Criterios de Búsqueda -->
        <div class="bulk-section-box q-pa-sm">
          <div class="text-caption text-weight-bold text-teal-3 q-mb-xs row items-center q-gutter-x-xs">
            <q-icon name="sym_r_search" size="16px" />
            <span>1. CRITERIO DE BÚSQUEDA EN MENSAJES</span>
          </div>

          <!-- Texto a buscar -->
          <q-input
            v-model="searchText"
            dense
            outlined
            dark
            label="Texto o palabra clave a buscar (ej: beca anticipada)"
            class="q-mb-sm"
            placeholder="Escribe la frase exacta o término..."
            :loading="isPreviewLoading"
            clearable
            @update:model-value="triggerPreviewDebounced"
          >
            <template #prepend>
              <q-icon name="sym_r_manage_search" size="18px" color="teal-4" />
            </template>
          </q-input>

          <!-- Dirección del mensaje (Radio Toggle) -->
          <div class="text-caption text-grey-4 q-mb-xs">Dirección del mensaje:</div>
          <q-btn-toggle
            v-model="direction"
            dense
            spread
            rounded
            unelevated
            toggle-color="teal-8"
            toggle-text-color="teal-1"
            color="dark"
            text-color="grey-4"
            size="sm"
            :options="[
              { label: 'Ambos (Toda la charla)', value: 'both', icon: 'sym_r_sync_alt' },
              { label: 'Solo Ingreso (Cliente)', value: 'inbound', icon: 'sym_r_call_received' },
              { label: 'Solo Egreso (Bot / Asesor)', value: 'outbound', icon: 'sym_r_call_made' },
            ]"
            @update:model-value="fetchPreview"
          />
        </div>

        <!-- 2. Vista Previa de Coincidencias en Tiempo Real -->
        <div class="preview-results-box q-pa-sm">
          <div class="row items-center justify-between q-mb-xs">
            <span class="text-caption text-weight-bold text-grey-3">
              RESULTADO DEL CRUCE EN BASE DE DATOS
            </span>
            <div class="row items-center q-gutter-x-xs">
              <q-btn
                flat
                dense
                no-caps
                size="xs"
                color="amber-4"
                icon="sym_r_download"
                label="Descargar Reporte"
                :loading="isExporting"
                :disable="!previewResult || previewResult.conversations_count === 0"
                @click="onExportReport"
              >
                <q-tooltip>Descargar reporte CSV/Excel con enlaces directos al CRM</q-tooltip>
              </q-btn>

              <q-btn
                flat
                dense
                no-caps
                size="xs"
                color="teal-3"
                icon="sym_r_refresh"
                label="Actualizar"
                :loading="isPreviewLoading"
                @click="fetchPreview"
              />
            </div>
          </div>

          <div v-if="isPreviewLoading" class="row items-center justify-center q-py-md text-teal-3">
            <q-spinner-dots size="24px" class="q-mr-sm" />
            <span class="text-caption">Analizando mensajes en el CRM...</span>
          </div>

          <div v-else-if="!searchText || searchText.trim().length < 2" class="text-caption text-grey-5 q-py-xs text-center">
            Introduce al menos 2 letras para calcular los contactos y chats afectados.
          </div>

          <div v-else-if="previewResult" class="q-gutter-y-xs">
            <div class="row items-center q-gutter-x-sm">
              <div class="col stat-pill text-center q-pa-xs">
                <div class="text-h6 text-weight-bolder text-teal-3">
                  {{ previewResult.conversations_count }}
                </div>
                <div class="text-caption text-grey-4" style="font-size: 0.72rem">
                  Conversaciones coincidentes
                </div>
              </div>

              <div class="col stat-pill text-center q-pa-xs">
                <div class="text-h6 text-weight-bolder text-amber-3">
                  {{ previewResult.contacts_count }}
                </div>
                <div class="text-caption text-grey-4" style="font-size: 0.72rem">
                  Contactos únicos a impactar
                </div>
              </div>
            </div>

            <!-- Muestra de prospectos detectados -->
            <div v-if="previewResult.sample?.length" class="sample-list q-mt-xs">
              <div class="text-caption text-grey-4 q-mb-xs" style="font-size: 0.7rem">
                Muestra de contactos detectados:
              </div>
              <div class="row q-gutter-xs">
                <q-chip
                  v-for="s in previewResult.sample"
                  :key="s.id"
                  dense
                  dark
                  size="sm"
                  color="grey-9"
                  text-color="teal-2"
                >
                  <q-icon name="sym_r_person" size="12px" class="q-mr-xs" />
                  <span>{{ s.contact_name }}</span>
                  <span v-if="s.contact_phone" class="text-grey-5 q-ml-xs">({{ s.contact_phone }})</span>
                </q-chip>
              </div>
            </div>

            <div v-if="previewResult.conversations_count === 0" class="text-caption text-amber-4 text-center q-py-xs">
              No se encontraron mensajes con la frase "{{ searchText }}" para la dirección seleccionada.
            </div>
          </div>
        </div>

        <!-- 3. Acciones Masivas a Aplicar -->
        <div class="bulk-section-box q-pa-sm q-gutter-y-sm">
          <div class="text-caption text-weight-bold text-amber-3 row items-center q-gutter-x-xs">
            <q-icon name="sym_r_playlist_add_check" size="16px" />
            <span>2. ACCIONES MASIVAS A ASIGNAR EN LOTE</span>
          </div>

          <!-- A. Asignar Etiquetas (Tags) al Contacto -->
          <div>
            <div class="row items-center justify-between q-mb-xs">
              <span class="text-caption text-grey-3 text-weight-medium">
                Asignar Etiquetas al Contacto:
              </span>
              <span class="text-caption text-grey-5" style="font-size: 0.7rem">
                (Aparecerán en Contactos y Campañas Masivas)
              </span>
            </div>

            <q-select
              v-model="selectedTags"
              :options="tagOptions"
              multiple
              use-chips
              use-input
              new-value-mode="add-unique"
              outlined
              dark
              dense
              label="Selecciona o escribe una etiqueta (ej: Beca Anticipada)..."
              class="xf-select"
              @filter="filterTags"
            >
              <template #prepend>
                <q-icon name="sym_r_label" size="18px" color="teal-4" />
              </template>

              <template #selected-item="scope">
                <q-chip
                  dense
                  dark
                  removable
                  color="teal-9"
                  text-color="teal-2"
                  class="q-ma-none q-mr-xs q-my-xs"
                  @remove="scope.removeAtIndex(scope.index)"
                >
                  <span class="text-caption text-weight-bold">#{{ getTagLabel(scope.opt) }}</span>
                </q-chip>
              </template>

              <template #option="{ itemProps, opt, selected, toggleOption }">
                <q-item v-bind="itemProps" dense dark class="q-py-xs cursor-pointer" @click="toggleOption(opt)">
                  <q-item-section side>
                    <q-checkbox :model-value="selected" dense color="teal-4" @update:model-value="toggleOption(opt)" />
                  </q-item-section>
                  <q-item-section>
                    <q-item-label class="text-body2 text-white">
                      #{{ getTagLabel(opt) }}
                    </q-item-label>
                  </q-item-section>
                </q-item>
              </template>
            </q-select>
          </div>

          <!-- B. Asignar Categorías a la Conversación & Contacto -->
          <div>
            <div class="row items-center justify-between q-mb-xs">
              <span class="text-caption text-grey-3 text-weight-medium">
                Asignar Categorías al Ticket / Contacto:
              </span>
              <span class="text-caption text-grey-5" style="font-size: 0.7rem">
                (Árbol de sedes / carreras / materias)
              </span>
            </div>

            <q-select
              v-model="selectedCategories"
              :options="categoryOptions"
              multiple
              use-chips
              emit-value
              map-options
              outlined
              dark
              dense
              use-input
              clearable
              label="Busca y checkea categorías..."
              class="xf-select"
              @filter="filterCategories"
            >
              <template #prepend>
                <q-icon name="sym_r_category" size="18px" color="teal-4" />
              </template>

              <template #selected-item="scope">
                <q-chip
                  dense
                  dark
                  removable
                  color="dark"
                  text-color="teal-3"
                  class="q-ma-none q-mr-xs q-my-xs"
                  @remove="scope.removeAtIndex(scope.index)"
                >
                  <q-avatar size="16px" :style="{ backgroundColor: scope.opt.color || '#10b981' }" text-color="white">
                    <q-icon :name="scope.opt.icon || 'sym_r_sell'" size="10px" />
                  </q-avatar>
                  <span class="text-caption text-weight-medium q-ml-xs">{{ scope.opt.name }}</span>
                </q-chip>
              </template>

              <template #option="{ itemProps, opt, selected, toggleOption }">
                <q-item v-bind="itemProps" dense dark class="q-py-xs cursor-pointer" @click="toggleOption(opt)">
                  <q-item-section side>
                    <q-checkbox :model-value="selected" dense color="teal-4" @update:model-value="toggleOption(opt)" />
                  </q-item-section>
                  <q-item-section avatar style="min-width: 24px" class="q-pr-xs">
                    <q-avatar size="20px" :style="{ backgroundColor: opt.color ? opt.color + '22' : 'rgba(16,185,129,0.15)', border: `1px solid ${opt.color || '#10b981'}` }">
                      <q-icon :name="opt.icon || 'sym_r_sell'" size="11px" :style="{ color: opt.color || '#10b981' }" />
                    </q-avatar>
                  </q-item-section>
                  <q-item-section>
                    <q-item-label class="text-body2 text-white">{{ opt.name }}</q-item-label>
                    <q-item-label v-if="opt.display_path" caption class="text-teal-3 text-caption font-mono" style="font-size: 0.7rem">
                      {{ opt.display_path }}
                    </q-item-label>
                  </q-item-section>
                </q-item>
              </template>
            </q-select>
          </div>

          <!-- C. Actualizar Estado -->
          <div class="row q-col-gutter-sm">
            <div class="col-12 col-sm-6">
              <span class="text-caption text-grey-3 text-weight-medium">
                Cambiar Estado de Ticket:
              </span>
              <q-select
                v-model="selectedTicketStatus"
                :options="ticketStatusOptions"
                emit-value
                map-options
                outlined
                dark
                dense
                clearable
                label="No cambiar estado"
                class="xf-select q-mt-xs"
              >
                <template #prepend>
                  <q-icon name="sym_r_flag" size="18px" color="teal-4" />
                </template>
              </q-select>
            </div>

            <div class="col-12 col-sm-6">
              <span class="text-caption text-grey-3 text-weight-medium">
                Estado Personalizado (CRM):
              </span>
              <q-select
                v-model="selectedCustomStatusId"
                :options="customStatusOptions"
                emit-value
                map-options
                outlined
                dark
                dense
                clearable
                label="No cambiar personalizado"
                class="xf-select q-mt-xs"
              >
                <template #prepend>
                  <q-icon name="sym_r_stars" size="18px" color="teal-4" />
                </template>
              </q-select>
            </div>
          </div>
        </div>
      </q-card-section>

      <q-separator dark color="grey-8" />

      <!-- Acciones de Pie de Diálogo -->
      <q-card-actions align="between" class="q-pa-md">
        <div class="row items-center q-gutter-x-sm">
          <q-btn
            flat
            color="grey-4"
            label="Cancelar"
            no-caps
            :disable="isExecuting"
            @click="onClose"
          />

          <q-btn
            outline
            color="amber-4"
            icon="sym_r_download"
            label="Exportar Excel"
            no-caps
            class="q-px-md text-weight-medium"
            :loading="isExporting"
            :disable="!previewResult || previewResult.conversations_count === 0"
            @click="onExportReport"
          >
            <q-tooltip>Descargar lista con links clicables al CRM, números de WhatsApp y canal</q-tooltip>
          </q-btn>
        </div>

        <q-btn
          unelevated
          color="primary"
          icon="sym_r_bolt"
          :label="executeBtnLabel"
          class="text-weight-bold q-px-lg"
          :loading="isExecuting"
          :disable="isExecuteDisabled"
          @click="onExecute"
        >
          <template #loading>
            <q-spinner-dots size="18px" />
          </template>
        </q-btn>
      </q-card-actions>
    </q-card>
  </q-dialog>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { Notify } from 'quasar'
import { http } from '@/shared/api/http'
import {
  executeBulkTextClassify,
  exportBulkTextMatches,
  previewBulkTextMatches,
  type BulkTextPreviewResult,
} from '../api/conversations.api'

const props = defineProps<{
  modelValue: boolean
  initialSearchText?: string
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', val: boolean): void
  (e: 'completed', data: { affected_conversations: number; affected_contacts: number }): void
}>()

// Criterios
const searchText = ref('')
const direction = ref<'both' | 'inbound' | 'outbound'>('both')

// Estado de la Vista Previa
const isPreviewLoading = ref(false)
const previewResult = ref<BulkTextPreviewResult | null>(null)
let previewTimer: any = null

// Acciones a aplicar
const selectedTags = ref<any[]>([])
const selectedCategories = ref<string[]>([])
const selectedTicketStatus = ref<string | null>(null)
const selectedCustomStatusId = ref<string | null>(null)

// Opciones de Datos
const allTags = ref<any[]>([])
const tagOptions = ref<any[]>([])
const allCategories = ref<any[]>([])
const categoryOptions = ref<any[]>([])
const customStatusOptions = ref<{ label: string; value: string }[]>([])

const ticketStatusOptions = [
  { label: 'Abierto (Atendiendo)', value: 'open' },
  { label: 'En espera (Pendiente)', value: 'pending' },
  { label: 'Cerrado / Resuelto', value: 'closed' },
]

const isExecuting = ref(false)

function getTagLabel(opt: any): string {
  if (typeof opt === 'string') return opt
  return opt?.name || opt?.label || opt?.value || ''
}

// Watch initialSearchText when opened
watch(
  () => props.modelValue,
  (isOpen) => {
    if (isOpen) {
      if (props.initialSearchText && props.initialSearchText.trim()) {
        searchText.value = props.initialSearchText.trim()
      }
      fetchInitialCatalog()
      fetchPreview()
    }
  },
)

function triggerPreviewDebounced() {
  clearTimeout(previewTimer)
  previewTimer = setTimeout(() => {
    fetchPreview()
  }, 350)
}

async function fetchPreview() {
  const term = searchText.value?.trim()
  if (!term || term.length < 2) {
    previewResult.value = null
    return
  }

  isPreviewLoading.value = true
  try {
    const res = await previewBulkTextMatches({
      text: term,
      direction: direction.value,
    })
    previewResult.value = res
  } catch (error: any) {
    console.error('Error al previsualizar coincidencias masivas:', error)
  } finally {
    isPreviewLoading.value = false
  }
}

async function fetchInitialCatalog() {
  try {
    // 1. Cargar Tags existentes
    const tagRes = await http.get<{ data: any[] }>('/tags')
    allTags.value = tagRes.data?.data || []
    tagOptions.value = [...allTags.value]

    // 2. Cargar Categorías
    const catRes = await http.get<{ data: any[] }>('/categories')
    allCategories.value = catRes.data?.data || []
    categoryOptions.value = allCategories.value.map((c) => ({
      label: c.name,
      value: c.id,
      name: c.name,
      color: c.color,
      icon: c.icon,
      display_path: c.display_path || c.code || '',
    }))

    // 3. Cargar Estados Personalizados
    const statusRes = await http.get<{ data: any[] }>('/custom-statuses')
    const rawStatuses = statusRes.data?.data || []
    customStatusOptions.value = rawStatuses.map((s) => ({
      label: s.name,
      value: s.id,
    }))
  } catch (error) {
    console.warn('Error loading classification catalogs:', error)
  }
}

function filterTags(val: string, update: Function) {
  update(() => {
    if (!val) {
      tagOptions.value = [...allTags.value]
      return
    }
    const needle = val.toLowerCase()
    tagOptions.value = allTags.value.filter((t) =>
      getTagLabel(t).toLowerCase().includes(needle),
    )
  })
}

function filterCategories(val: string, update: Function) {
  update(() => {
    if (!val) {
      categoryOptions.value = allCategories.value.map((c) => ({
        label: c.name,
        value: c.id,
        name: c.name,
        color: c.color,
        icon: c.icon,
        display_path: c.display_path || c.code || '',
      }))
      return
    }
    const needle = val.toLowerCase()
    categoryOptions.value = allCategories.value
      .filter((c) => c.name.toLowerCase().includes(needle) || (c.display_path && c.display_path.toLowerCase().includes(needle)))
      .map((c) => ({
        label: c.name,
        value: c.id,
        name: c.name,
        color: c.color,
        icon: c.icon,
        display_path: c.display_path || c.code || '',
      }))
  })
}

const hasActionSelected = computed(() => {
  return (
    selectedTags.value.length > 0 ||
    selectedCategories.value.length > 0 ||
    selectedTicketStatus.value !== null ||
    selectedCustomStatusId.value !== null
  )
})

const isExecuteDisabled = computed(() => {
  if (!previewResult.value || previewResult.value.conversations_count === 0) return true
  if (!hasActionSelected.value) return true
  return isExecuting.value
})

const executeBtnLabel = computed(() => {
  if (!previewResult.value || previewResult.value.contacts_count === 0) {
    return 'Aplicar Acción Masiva'
  }
  return `Aplicar a ${previewResult.value.contacts_count} Contactos (${previewResult.value.conversations_count} Chats)`
})

async function onExecute() {
  const term = searchText.value?.trim()
  if (!term) return

  isExecuting.value = true
  try {
    // Extraer tag IDs o nuevos tags escritos
    const tagIds: string[] = []
    let newTagName: string | undefined = undefined

    for (const item of selectedTags.value) {
      if (typeof item === 'string') {
        newTagName = item.trim()
      } else if (item?.id) {
        tagIds.push(item.id)
      } else if (item?.value) {
        tagIds.push(item.value)
      }
    }

    const payload = {
      text: term,
      direction: direction.value,
      tag_ids: tagIds.length ? tagIds : undefined,
      new_tag_name: newTagName,
      category_ids: selectedCategories.value.length ? selectedCategories.value : undefined,
      status: selectedTicketStatus.value ? (selectedTicketStatus.value as any) : undefined,
      custom_status_id: selectedCustomStatusId.value ? selectedCustomStatusId.value : undefined,
    }

    const result = await executeBulkTextClassify(payload)

    Notify.create({
      type: 'positive',
      message: `¡Acción masiva completada! Se impactaron ${result.affected_contacts} contactos y ${result.affected_conversations} conversaciones.`,
      position: 'bottom-right',
      icon: 'sym_r_check_circle',
      timeout: 4500,
    })

    emit('completed', {
      affected_conversations: result.affected_conversations,
      affected_contacts: result.affected_contacts,
    })
    emit('update:modelValue', false)
  } catch (error: any) {
    Notify.create({
      type: 'negative',
      message: error?.response?.data?.message || 'Error al ejecutar la acción masiva.',
      position: 'bottom-right',
      icon: 'sym_r_error',
      timeout: 4000,
    })
  } finally {
    isExecuting.value = false
  }
}

const isExporting = ref(false)

async function onExportReport() {
  const term = searchText.value?.trim()
  if (!term) return

  isExporting.value = true
  try {
    const { blob, filename } = await exportBulkTextMatches({
      text: term,
      direction: direction.value,
      format: 'xlsx',
    })

    const url = window.URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    const safeTerm = term.replace(/[^a-zA-Z0-9_-]/g, '_')
    a.download = filename || `Reporte_Chats_${safeTerm}_${new Date().toISOString().slice(0, 10)}.xlsx`
    document.body.appendChild(a)
    a.click()
    window.URL.revokeObjectURL(url)
    document.body.removeChild(a)

    Notify.create({
      type: 'positive',
      message: 'Reporte Excel (.xlsx) descargado con éxito.',
      position: 'bottom-right',
      icon: 'sym_r_download_done',
      timeout: 3000,
    })
  } catch (error: any) {
    Notify.create({
      type: 'negative',
      message: 'Error al generar la descarga del reporte.',
      position: 'bottom-right',
      icon: 'sym_r_error',
      timeout: 3500,
    })
  } finally {
    isExporting.value = false
  }
}

function onClose() {
  emit('update:modelValue', false)
}

onMounted(() => {
  if (props.initialSearchText) {
    searchText.value = props.initialSearchText
  }
})
</script>

<style scoped lang="scss">
.bulk-classifier-card {
  width: 580px;
  max-width: 95vw;
  background: #0f172a;
  border: 1px solid rgba(255, 255, 255, 0.12);
  border-radius: 16px;
  box-shadow: 0 20px 45px -10px rgba(0, 0, 0, 0.8);
  overflow: hidden;
}

.bulk-header-bar {
  background: linear-gradient(135deg, rgba(13, 148, 136, 0.25) 0%, rgba(15, 23, 42, 0.95) 100%);
}

.bulk-scrollable-body {
  max-height: 70vh;
  overflow-y: auto;
}

.bulk-section-box {
  background: rgba(255, 255, 255, 0.03);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 10px;
}

.preview-results-box {
  background: rgba(13, 148, 136, 0.08);
  border: 1px dashed rgba(20, 184, 166, 0.35);
  border-radius: 10px;
}

.stat-pill {
  background: rgba(0, 0, 0, 0.35);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 8px;
}

.sample-list {
  background: rgba(0, 0, 0, 0.2);
  border-radius: 6px;
  padding: 6px;
}

.xf-select {
  :deep(.q-field__control) {
    background: rgba(0, 0, 0, 0.25);
  }
}
</style>
