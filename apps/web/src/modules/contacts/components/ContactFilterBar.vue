<template>
  <div class="contact-filter-bar row items-center justify-between q-col-gutter-sm q-mb-md">
    <div class="col-12 col-md-8 row items-center q-gutter-sm">
      <!-- Búsqueda Instantánea -->
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

      <!-- Filtro de Etiqueta -->
      <q-select
        :model-value="tag"
        :options="tagOptions"
        emit-value
        map-options
        dense
        outlined
        dark
        clearable
        placeholder="Filtrar por etiqueta"
        class="contact-filter-bar__select"
        @update:model-value="emit('update:tag', $event ?? null)"
      >
        <template #prepend>
          <q-icon name="sym_r_label" size="16px" color="teal-4" />
        </template>
      </q-select>

      <!-- Filtro de Estado -->
      <q-select
        :model-value="status"
        :options="statusOptions"
        emit-value
        map-options
        dense
        outlined
        dark
        clearable
        placeholder="Estado"
        class="contact-filter-bar__select-sm"
        @update:model-value="emit('update:status', $event ?? null)"
      />
    </div>

    <!-- Toggle de Modo de Vista -->
    <div class="col-12 col-md-4 row items-center justify-end q-gutter-xs">
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
import { computed } from 'vue'
import type { ContactTag } from '../types/contact.types'

const props = withDefaults(
  defineProps<{
    search?: string
    tag?: string | null
    status?: string | null
    viewMode?: 'table' | 'grid'
    tags?: ContactTag[]
  }>(),
  {
    search: '',
    tag: null,
    status: null,
    viewMode: 'table',
    tags: () => [],
  },
)

const emit = defineEmits<{
  'update:search': [value: string]
  'update:tag': [value: string | null]
  'update:status': [value: string | null]
  'update:viewMode': [value: 'table' | 'grid']
}>()

const statusOptions = [
  { label: 'Todos los estados', value: null },
  { label: 'Activo', value: 'active' },
  { label: 'Lead / Prospecto', value: 'lead' },
  { label: 'Inactivo', value: 'inactive' },
]

const tagOptions = computed(() => {
  return [
    { label: 'Todas las etiquetas', value: null },
    ...props.tags.map((t) => ({
      label: t.name,
      value: t.name,
    })),
  ]
})
</script>

<style scoped lang="scss">
.contact-filter-bar__input {
  min-width: 260px;
}

.contact-filter-bar__select {
  min-width: 190px;
}

.contact-filter-bar__select-sm {
  min-width: 140px;
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
