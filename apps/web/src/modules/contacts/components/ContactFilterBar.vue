<template>
  <div class="contact-filter-bar row items-center justify-between q-col-gutter-sm q-mb-md">
    <div class="col-12 col-lg-9 row items-center q-gutter-sm">
      <!-- 1. Búsqueda Instantánea -->
      <q-input
        :model-value="search"
        dense
        outlined
        dark
        debounce="250"
        placeholder="Buscar por nombre, teléfono o correo..."
        class="contact-filter-bar__input"
        @update:model-value="emit('update:search', ($event as string) || '')"
      >
        <template #prepend>
          <q-icon name="sym_r_search" size="18px" color="teal-4" />
        </template>
        <template v-if="search" #append>
          <q-icon
            name="sym_r_close"
            size="16px"
            class="cursor-pointer text-grey-5"
            @click="emit('update:search', '')"
          />
        </template>
      </q-input>

      <!-- 2. Filtro de Categorías (Buscable y Checkable) -->
      <q-select
        :model-value="categoryIds"
        :options="filteredCategoryOptions"
        multiple
        use-input
        emit-value
        map-options
        dense
        outlined
        dark
        clearable
        placeholder="Categorías"
        class="contact-filter-bar__select"
        @filter="filterCategories"
        @update:model-value="emit('update:categoryIds', $event || [])"
      >
        <template #prepend>
          <q-icon name="sym_r_category" size="16px" color="cyan-4" />
        </template>

        <!-- Cabecera del Select con Contador o Resumen -->
        <template #selected>
          <div v-if="categoryIds && categoryIds.length > 0" class="row items-center no-wrap ellipsis text-caption text-white">
            <q-badge color="cyan-9" text-color="cyan-2" class="q-mr-xs text-bold">
              {{ categoryIds.length }}
            </q-badge>
            <span class="ellipsis">{{ getSelectedCategoryText(categoryIds) }}</span>
          </div>
          <span v-else class="text-grey-5 text-caption">Categorías</span>
        </template>

        <!-- Opciones Checkables con Buscador -->
        <template #option="{ itemProps, opt, selected, toggleOption }">
          <q-item v-bind="itemProps" dense dark class="filter-option-item cursor-pointer" @click="toggleOption(opt)">
            <q-item-section side class="q-pr-xs">
              <q-checkbox :model-value="selected" dense dark @update:model-value="toggleOption(opt)" />
            </q-item-section>
            <q-item-section avatar style="min-width: 20px" class="q-pr-xs">
              <span
                class="filter-dot"
                :style="{ backgroundColor: opt.color || '#06b6d4' }"
              ></span>
            </q-item-section>
            <q-item-section>
              <q-item-label class="text-body2 text-white">{{ opt.label }}</q-item-label>
              <q-item-label v-if="opt.full_path" caption class="text-grey-5">
                {{ opt.full_path }}
              </q-item-label>
            </q-item-section>
          </q-item>
        </template>
      </q-select>

      <!-- 3. Filtro de Estados Oficiales (Buscable y Checkable) -->
      <q-select
        :model-value="customStatusIds"
        :options="filteredStatusOptions"
        multiple
        use-input
        emit-value
        map-options
        dense
        outlined
        dark
        clearable
        placeholder="Estados"
        class="contact-filter-bar__select"
        @filter="filterStatuses"
        @update:model-value="emit('update:customStatusIds', $event || [])"
      >
        <template #prepend>
          <q-icon name="sym_r_flag" size="16px" color="teal-4" />
        </template>

        <!-- Cabecera del Select con Contador o Resumen -->
        <template #selected>
          <div v-if="customStatusIds && customStatusIds.length > 0" class="row items-center no-wrap ellipsis text-caption text-white">
            <q-badge color="teal-9" text-color="teal-2" class="q-mr-xs text-bold">
              {{ customStatusIds.length }}
            </q-badge>
            <span class="ellipsis">{{ getSelectedStatusText(customStatusIds) }}</span>
          </div>
          <span v-else class="text-grey-5 text-caption">Estados</span>
        </template>

        <!-- Opciones Checkables con Buscador -->
        <template #option="{ itemProps, opt, selected, toggleOption }">
          <q-item v-bind="itemProps" dense dark class="filter-option-item cursor-pointer" @click="toggleOption(opt)">
            <q-item-section side class="q-pr-xs">
              <q-checkbox :model-value="selected" dense dark @update:model-value="toggleOption(opt)" />
            </q-item-section>
            <q-item-section avatar style="min-width: 20px" class="q-pr-xs">
              <span
                class="filter-dot"
                :style="{ backgroundColor: opt.color || '#10b981' }"
              ></span>
            </q-item-section>
            <q-item-section>
              <div class="row items-center justify-between">
                <span class="text-body2 text-white">{{ opt.label }}</span>
                <q-badge
                  v-if="opt.stage_type"
                  :color="getStageColor(opt.stage_type)"
                  class="text-caption q-ml-sm"
                >
                  {{ formatStage(opt.stage_type) }}
                </q-badge>
              </div>
            </q-item-section>
          </q-item>
        </template>
      </q-select>

      <!-- 4. Filtro de Etiquetas (Buscable y Checkable) -->
      <q-select
        :model-value="tagNames"
        :options="filteredTagOptions"
        multiple
        use-input
        emit-value
        map-options
        dense
        outlined
        dark
        clearable
        placeholder="Etiquetas"
        class="contact-filter-bar__select"
        @filter="filterTags"
        @update:model-value="emit('update:tagNames', $event || [])"
      >
        <template #prepend>
          <q-icon name="sym_r_label" size="16px" color="amber-4" />
        </template>

        <!-- Cabecera del Select con Contador o Resumen -->
        <template #selected>
          <div v-if="tagNames && tagNames.length > 0" class="row items-center no-wrap ellipsis text-caption text-white">
            <q-badge color="amber-9" text-color="amber-2" class="q-mr-xs text-bold">
              {{ tagNames.length }}
            </q-badge>
            <span class="ellipsis">{{ tagNames.join(', ') }}</span>
          </div>
          <span v-else class="text-grey-5 text-caption">Etiquetas</span>
        </template>

        <!-- Opciones Checkables con Buscador -->
        <template #option="{ itemProps, opt, selected, toggleOption }">
          <q-item v-bind="itemProps" dense dark class="filter-option-item cursor-pointer" @click="toggleOption(opt)">
            <q-item-section side class="q-pr-xs">
              <q-checkbox :model-value="selected" dense dark @update:model-value="toggleOption(opt)" />
            </q-item-section>
            <q-item-section>
              <q-item-label class="text-body2 text-white">{{ opt.label }}</q-item-label>
            </q-item-section>
          </q-item>
        </template>
      </q-select>
    </div>

    <!-- Toggle de Modo de Vista -->
    <div class="col-12 col-lg-3 row items-center justify-end q-gutter-xs">
      <div class="contact-filter-bar__toggle">
        <q-btn
          flat
          dense
          round
          size="sm"
          icon="sym_r_table_rows"
          :color="viewMode === 'table' ? 'primary' : 'grey-5'"
          :class="{ 'contact-filter-bar__toggle--active': viewMode === 'table' }"
          @click="emit('update:viewMode', 'table')"
        >
          <q-tooltip>Vista Tabla</q-tooltip>
        </q-btn>
        <q-btn
          flat
          dense
          round
          size="sm"
          icon="sym_r_grid_view"
          :color="viewMode === 'grid' ? 'primary' : 'grey-5'"
          :class="{ 'contact-filter-bar__toggle--active': viewMode === 'grid' }"
          @click="emit('update:viewMode', 'grid')"
        >
          <q-tooltip>Vista Cuadrícula</q-tooltip>
        </q-btn>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useQuery } from '@tanstack/vue-query'
import { http } from '@/shared/api/http'
import type { ContactTag } from '../types/contact.types'

const props = withDefaults(
  defineProps<{
    search?: string
    tagNames?: string[]
    customStatusIds?: string[]
    categoryIds?: string[]
    viewMode?: 'table' | 'grid'
    tags?: ContactTag[]
  }>(),
  {
    search: '',
    tagNames: () => [],
    customStatusIds: () => [],
    categoryIds: () => [],
    viewMode: 'table',
    tags: () => [],
  },
)

const emit = defineEmits<{
  'update:search': [value: string]
  'update:tagNames': [value: string[]]
  'update:customStatusIds': [value: string[]]
  'update:categoryIds': [value: string[]]
  'update:viewMode': [value: 'table' | 'grid']
}>()

// Filtros de búsqueda textual en los selects
const categorySearch = ref('')
const statusSearch = ref('')
const tagSearch = ref('')

function filterCategories(val: string, update: (fn: () => void) => void) {
  update(() => {
    categorySearch.value = val
  })
}

function filterStatuses(val: string, update: (fn: () => void) => void) {
  update(() => {
    statusSearch.value = val
  })
}

function filterTags(val: string, update: (fn: () => void) => void) {
  update(() => {
    tagSearch.value = val
  })
}

// Cargar Estados Oficiales de la BD
const { data: customStatusesQuery } = useQuery({
  queryKey: ['custom-statuses'],
  queryFn: async () => {
    const res = await http.get('/custom-statuses')
    return res.data.data || []
  },
})

// Cargar Categorías Oficiales de la BD
const { data: categoriesQuery } = useQuery({
  queryKey: ['categories'],
  queryFn: async () => {
    const res = await http.get('/categories')
    return res.data.data || []
  },
})

const allCategoryOptions = computed(() => {
  const list = (categoriesQuery.value as any[]) || []
  return list.map((c: any) => ({
    label: c.code ? `[${c.code}] ${c.name}` : c.name,
    value: c.id,
    color: c.color,
    icon: c.icon || 'sym_r_school',
    full_path: c.full_path,
  }))
})

const filteredCategoryOptions = computed(() => {
  const needle = categorySearch.value.toLowerCase().trim()
  if (!needle) return allCategoryOptions.value
  return allCategoryOptions.value.filter(
    (c) =>
      c.label.toLowerCase().includes(needle) ||
      (c.full_path && c.full_path.toLowerCase().includes(needle)),
  )
})

const allStatusOptions = computed(() => {
  const list = (customStatusesQuery.value as any[]) || []
  return list.map((s: any) => ({
    label: s.name,
    value: s.id,
    color: s.color,
    icon: s.icon || 'sym_r_flag',
    stage_type: s.stage_type,
  }))
})

const filteredStatusOptions = computed(() => {
  const needle = statusSearch.value.toLowerCase().trim()
  if (!needle) return allStatusOptions.value
  return allStatusOptions.value.filter((s) => s.label.toLowerCase().includes(needle))
})

const allTagOptions = computed(() => {
  return props.tags.map((t) => ({
    label: t.name,
    value: t.name,
  }))
})

const filteredTagOptions = computed(() => {
  const needle = tagSearch.value.toLowerCase().trim()
  if (!needle) return allTagOptions.value
  return allTagOptions.value.filter((t) => t.label.toLowerCase().includes(needle))
})

function getSelectedCategoryText(ids: string[]): string {
  const names = allCategoryOptions.value
    .filter((c) => ids.includes(c.value))
    .map((c) => c.label)
  return names.join(', ')
}

function getSelectedStatusText(ids: string[]): string {
  const names = allStatusOptions.value
    .filter((s) => ids.includes(s.value))
    .map((s) => s.label)
  return names.join(', ')
}

function formatStage(stage: string): string {
  switch (stage) {
    case 'initial': return 'Inicial'
    case 'in_progress': return 'En Proceso'
    case 'won': return 'Ganado'
    case 'lost': return 'Perdido'
    default: return stage
  }
}

function getStageColor(stage: string): string {
  switch (stage) {
    case 'initial': return 'blue-9'
    case 'in_progress': return 'cyan-9'
    case 'won': return 'positive'
    case 'lost': return 'negative'
    default: return 'grey-8'
  }
}
</script>

<style scoped lang="scss">
.contact-filter-bar__input {
  min-width: 220px;
  flex: 1 1 220px;
}

.contact-filter-bar__select {
  min-width: 170px;
  max-width: 230px;
  flex: 1 1 170px;
}

.filter-dot {
  width: 9px;
  height: 9px;
  border-radius: 50%;
  display: inline-block;
  flex-shrink: 0;
}

.filter-option-item {
  border-bottom: 1px solid rgba(255, 255, 255, 0.04);
  &:hover {
    background: rgba(255, 255, 255, 0.05);
  }
}

.contact-filter-bar__toggle {
  display: inline-flex;
  align-items: center;
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 8px;
  padding: 2px 4px;
  gap: 2px;

  &--active {
    background: rgba(16, 185, 129, 0.15);
  }
}
</style>
