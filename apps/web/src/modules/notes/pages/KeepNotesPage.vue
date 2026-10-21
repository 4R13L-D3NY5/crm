<template>
  <q-page class="xf-keep-page q-pa-md q-pa-md-lg">
    <!-- Header Principal -->
    <header class="xf-keep-header row items-center justify-between q-col-gutter-sm q-mb-md">
      <div class="col-12 col-sm-auto">
        <div class="row items-center q-gutter-x-sm">
          <q-avatar size="36px" color="primary" text-color="white">
            <q-icon name="sym_r_sticky_note_2" size="20px" />
          </q-avatar>
          <div>
            <h1 class="text-h6 text-weight-bold text-white q-my-none">
              Notas & Base de Conocimiento
            </h1>
            <p class="text-caption text-grey-4 q-my-none">
              Espacio ágil de micro-conocimiento, respuestas rápidas y guías con IA
            </p>
          </div>
        </div>
      </div>

      <div class="col-12 col-sm-auto row items-center q-gutter-x-sm">
        <q-btn
          unelevated
          color="primary"
          icon="sym_r_add"
          label="Nueva Nota"
          class="q-px-md text-weight-bold"
          @click="openCreateDialog"
        />
      </div>
    </header>

    <!-- Barra de Filtros y Búsqueda -->
    <div class="xf-keep-filters-card q-pa-sm q-mb-md rounded-borders">
      <div class="row items-center justify-between q-col-gutter-sm">
        <!-- Pestañas de Vista -->
        <div class="col-12 col-md-auto">
          <q-tabs
            v-model="activeTab"
            dense
            no-caps
            indicator-color="primary"
            class="text-grey-4"
            active-color="primary"
            @update:model-value="onTabChange"
          >
            <q-tab name="active" icon="sym_r_note" label="Notas Activas" />
            <q-tab name="pinned" icon="sym_r_push_pin" label="Fijadas" />
            <q-tab name="archived" icon="sym_r_archive" label="Archivadas" />
          </q-tabs>
        </div>

        <!-- Búsqueda de Texto -->
        <div class="col-12 col-md-4">
          <q-input
            v-model="searchQuery"
            dense
            outlined
            placeholder="Buscar por texto, título o #atajo..."
            class="xf-search-input"
            debounce="300"
            @update:model-value="onSearchInput"
          >
            <template #prepend>
              <q-icon name="sym_r_search" size="18px" color="teal-3" />
            </template>
            <template #append v-if="searchQuery">
              <q-icon name="sym_r_close" size="16px" class="cursor-pointer" @click="clearSearch" />
            </template>
          </q-input>
        </div>

        <!-- Filtro por Categoría / Subcategoría -->
        <div class="col-12 col-sm-6 col-md-3">
          <q-select
            v-model="selectedCategory"
            dense
            outlined
            clearable
            emit-value
            map-options
            use-input
            input-debounce="0"
            option-value="id"
            option-label="name"
            :options="filteredCategoryOptions"
            label="Categoría / Área"
            class="xf-filter-select"
            @filter="filterCategories"
            @update:model-value="applyFilters"
          >
            <template #selected-item="scope">
              <span class="ellipsis text-white text-caption text-weight-medium">
                {{ scope.opt.name }}
              </span>
              <q-tooltip class="bg-dark text-white font-mono text-caption">{{ scope.opt.display_path || scope.opt.name }}</q-tooltip>
            </template>
            <template #option="{ itemProps, opt }">
              <q-item v-bind="itemProps" dense dark class="cursor-pointer q-py-xs">
                <q-item-section avatar style="min-width: 20px" class="q-pr-xs">
                  <span class="filter-dot" :style="{ backgroundColor: opt.color || '#10b981' }"></span>
                </q-item-section>
                <q-item-section>
                  <q-item-label class="text-body2 text-white">{{ opt.name }}</q-item-label>
                  <q-item-label caption class="text-teal-3 font-mono text-caption">{{ opt.display_path }}</q-item-label>
                </q-item-section>
              </q-item>
            </template>
            <template #no-option>
              <q-item dark>
                <q-item-section class="text-grey-5 text-caption text-center q-pa-sm">
                  No se encontraron categorías
                </q-item-section>
              </q-item>
            </template>
          </q-select>
        </div>

        <!-- Filtro por Estado -->
        <div class="col-12 col-sm-6 col-md-2">
          <q-select
            v-model="selectedStatus"
            dense
            outlined
            clearable
            emit-value
            map-options
            option-value="id"
            option-label="name"
            :options="statusOptions"
            label="Estado Lead"
            class="xf-filter-select"
            @update:model-value="applyFilters"
          />
        </div>
      </div>
    </div>

    <!-- Indicador de Carga -->
    <div v-if="isLoading" class="row justify-center q-py-xl">
      <q-spinner-dots size="48px" color="primary" />
    </div>

    <!-- Tablero de Notas Estilo Keep -->
    <div v-else-if="notes.length > 0">
      <!-- Sección: Notas Fijadas -->
      <section v-if="pinnedNotes.length > 0 && activeTab !== 'pinned'" class="q-mb-lg">
        <div class="row items-center q-gutter-x-xs q-mb-sm">
          <q-icon name="sym_r_push_pin" size="16px" color="amber-4" />
          <span class="text-caption text-weight-bold text-uppercase text-amber-4 font-mono letter-spacing-wide">
            Fijadas en la parte superior ({{ pinnedNotes.length }})
          </span>
        </div>

        <div class="keep-grid">
          <NoteCard
            v-for="note in pinnedNotes"
            :key="note.id"
            :note="note"
            :can-edit="canEditNote(note)"
            @edit="openEditDialog"
            @delete="confirmDelete"
            @toggle-pin="handleTogglePin"
            @toggle-archive="handleToggleArchive"
            @find-similar="openSimilarDialog"
          />
        </div>
      </section>

      <!-- Sección: Otras Notas -->
      <section v-if="otherNotes.length > 0">
        <div v-if="pinnedNotes.length > 0 && activeTab !== 'pinned'" class="row items-center q-gutter-x-xs q-mb-sm">
          <span class="text-caption text-weight-bold text-uppercase text-grey-5 font-mono letter-spacing-wide">
            Otras notas ({{ otherNotes.length }})
          </span>
        </div>

        <div class="keep-grid">
          <NoteCard
            v-for="note in otherNotes"
            :key="note.id"
            :note="note"
            :can-edit="canEditNote(note)"
            @edit="openEditDialog"
            @delete="confirmDelete"
            @toggle-pin="handleTogglePin"
            @toggle-archive="handleToggleArchive"
            @find-similar="openSimilarDialog"
          />
        </div>
      </section>
    </div>

    <!-- Estado Vacío -->
    <div v-else class="empty-keep-state column items-center justify-center q-py-xl text-center">
      <q-avatar size="64px" color="grey-9" text-color="grey-5" class="q-mb-md">
        <q-icon name="sym_r_note_alt" size="36px" />
      </q-avatar>
      <div class="text-h6 text-weight-bold text-white q-mb-xs">
        No se encontraron notas
      </div>
      <p class="text-caption text-grey-4" style="max-width: 440px">
        {{
          searchQuery || selectedCategory || selectedStatus
            ? 'No hay notas que coincidan con los filtros aplicados. Prueba cambiando los términos de búsqueda.'
            : activeTab === 'archived'
              ? 'No tienes notas archivadas en este momento.'
              : 'Empieza a documentar respuestas rápidas, objeciones y conocimiento útil para el equipo de asesores.'
        }}
      </p>
      <q-btn
        v-if="!searchQuery && !selectedCategory && !selectedStatus && activeTab !== 'archived'"
        unelevated
        color="primary"
        icon="sym_r_add"
        label="Crear primera nota"
        class="q-mt-sm"
        @click="openCreateDialog"
      />
    </div>

    <!-- Modal de Creación / Edición de Nota -->
    <NoteEditDialog
      v-model="isDialogOpen"
      :note="selectedNote"
      @saved="onNoteSaved"
    />

    <!-- Modal de Detección de Notas Similares con IA -->
    <SimilarNotesDialog
      v-model="isSimilarDialogOpen"
      :note="similarDialogNote"
      @select-note="openEditDialog"
    />
  </q-page>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useQuasar } from 'quasar'
import { useAuthStore } from '@/modules/auth/stores/auth.store'
import { http } from '@/shared/api/http'
import { formatCategoryDisplayPath, matchesCategorySearch } from '@/modules/parameters/utils/category-formatter'
import { useNotes } from '../composables/useNotes'
import type { Note } from '../types/note.types'
import NoteCard from '../components/NoteCard.vue'
import NoteEditDialog from '../components/NoteEditDialog.vue'
import SimilarNotesDialog from '../components/SimilarNotesDialog.vue'

const $q = useQuasar()
const authStore = useAuthStore()
const { notes, isLoading, fetchNotes, togglePin, toggleArchive, removeNote } = useNotes()

const isAdmin = computed(() => {
  return authStore.currentRole === 'admin' || authStore.currentRole === 'owner'
})

function canEditNote(note: Note): boolean {
  if (isAdmin.value) return true
  const uid = authStore.user?.id
  return Boolean(uid && (note.user_id === uid || note.user?.id === uid))
}

const isSimilarDialogOpen = ref(false)
const similarDialogNote = ref<Note | null>(null)

function openSimilarDialog(note: Note) {
  similarDialogNote.value = note
  isSimilarDialogOpen.value = true
}

const activeTab = ref<'active' | 'pinned' | 'archived'>('active')
const searchQuery = ref('')
const selectedCategory = ref<string | null>(null)
const selectedStatus = ref<string | null>(null)

const categoryOptions = ref<Array<{ id: string; name: string }>>([])
const statusOptions = ref<Array<{ id: string; name: string }>>([])

const categoryFilterSearch = ref('')

const formattedCategoryOptions = computed(() => {
  return categoryOptions.value.map((c: any) => {
    const displayPath = formatCategoryDisplayPath(c, categoryOptions.value)
    return {
      ...c,
      label: displayPath,
      name: c.name,
      display_path: displayPath,
      value: c.id,
    }
  })
})

const filteredCategoryOptions = computed(() => {
  const q = categoryFilterSearch.value.toLowerCase().trim()
  if (!q) return formattedCategoryOptions.value
  return formattedCategoryOptions.value.filter((c: any) =>
    matchesCategorySearch(c, q, c.display_path)
  )
})

function filterCategories(val: string, update: (fn: () => void) => void) {
  update(() => {
    categoryFilterSearch.value = val
  })
}

const isDialogOpen = ref(false)
const selectedNote = ref<Note | null>(null)

const pinnedNotes = computed(() => notes.value.filter((n) => n.is_pinned))
const otherNotes = computed(() => {
  if (activeTab.value === 'pinned') return notes.value.filter((n) => n.is_pinned)
  return notes.value.filter((n) => !n.is_pinned)
})

onMounted(async () => {
  loadFiltersData()
  applyFilters()
})

async function loadFiltersData() {
  try {
    const [catRes, stRes] = await Promise.all([
      http.get('/categories'),
      http.get('/custom-statuses'),
    ])
    categoryOptions.value = catRes.data?.data || []
    statusOptions.value = stRes.data?.data || []
  } catch (err) {
    console.error('Error cargando filtros para notas:', err)
  }
}

function applyFilters() {
  const params: any = {}
  if (searchQuery.value.trim()) params.search = searchQuery.value.trim()
  if (selectedCategory.value) params.category_id = selectedCategory.value
  if (selectedStatus.value) params.custom_status_id = selectedStatus.value

  if (activeTab.value === 'archived') {
    params.is_archived = true
  } else if (activeTab.value === 'pinned') {
    params.is_pinned = true
    params.is_archived = false
  } else {
    params.is_archived = false
  }

  fetchNotes(params)
}

function onTabChange() {
  applyFilters()
}

function onSearchInput() {
  applyFilters()
}

function clearSearch() {
  searchQuery.value = ''
  applyFilters()
}

function openCreateDialog() {
  selectedNote.value = null
  isDialogOpen.value = true
}

function openEditDialog(note: Note) {
  selectedNote.value = note
  isDialogOpen.value = true
}

function onNoteSaved() {
  applyFilters()
}

async function handleTogglePin(id: string) {
  try {
    const isPinned = await togglePin(id)
    $q.notify({
      type: 'positive',
      message: isPinned ? 'Nota fijada en la parte superior' : 'Nota desfijada',
      timeout: 1500,
      position: 'bottom-right',
    })
  } catch (err: any) {
    $q.notify({
      type: 'negative',
      message: err?.response?.data?.message || 'Error al fijar nota',
      position: 'bottom-right',
    })
  }
}

async function handleToggleArchive(id: string) {
  try {
    const isArchived = await toggleArchive(id)
    $q.notify({
      type: 'positive',
      message: isArchived ? 'Nota enviada al archivo' : 'Nota restaurada del archivo',
      timeout: 1500,
      position: 'bottom-right',
    })
  } catch (err: any) {
    $q.notify({
      type: 'negative',
      message: err?.response?.data?.message || 'Error al archivar nota',
      position: 'bottom-right',
    })
  }
}

function confirmDelete(id: string) {
  $q.dialog({
    title: '¿Eliminar nota?',
    message: 'Esta acción removerá la nota del tablero de conocimiento. Podrás seguir viendo las notas existentes.',
    cancel: true,
    persistent: true,
    ok: {
      color: 'negative',
      label: 'Eliminar',
    },
  }).onOk(async () => {
    try {
      await removeNote(id)
      $q.notify({
        type: 'positive',
        message: 'Nota eliminada correctamente',
        position: 'bottom-right',
      })
    } catch (err: any) {
      $q.notify({
        type: 'negative',
        message: err?.response?.data?.message || 'Error al eliminar nota',
        position: 'bottom-right',
      })
    }
  })
}
</script>

<style scoped lang="scss">
.xf-keep-page {
  background: #090d16;
  min-height: calc(100vh - 50px);
}

.xf-keep-filters-card {
  background: rgba(15, 23, 42, 0.7);
  border: 1px solid rgba(255, 255, 255, 0.08);
}

.keep-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 16px;
  align-items: start;
}

@media (max-width: 599px) {
  .keep-grid {
    grid-template-columns: 1fr;
    gap: 12px;
  }
}

@media (min-width: 1200px) {
  .keep-grid {
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
  }
}

.empty-keep-state {
  border: 1px dashed rgba(255, 255, 255, 0.1);
  border-radius: 16px;
  background: rgba(15, 23, 42, 0.4);
}
</style>
