<template>
  <div
    class="xf-keep-card"
    :style="{ backgroundColor: cardBgColor, borderColor: cardBorderColor }"
  >
    <!-- Card Header: Título y Botón Pin -->
    <div class="xf-keep-card__header row items-start justify-between no-wrap q-mb-xs">
      <div class="xf-keep-card__title ellipsis text-weight-bold text-subtitle1">
        {{ note.title || 'Nota sin título' }}
      </div>

      <div class="row items-center no-wrap q-gutter-x-xs">
        <q-btn
          flat
          round
          dense
          size="sm"
          :color="note.is_pinned ? 'amber-5' : 'grey-5'"
          :icon="note.is_pinned ? 'sym_r_push_pin' : 'sym_r_push_pin'"
          :class="{ 'text-amber-4': note.is_pinned }"
          :disable="!canEdit"
          @click.stop="emit('toggle-pin', note.id)"
        >
          <q-tooltip>{{ note.is_pinned ? 'Desfijar de la parte superior' : 'Fijar nota en la parte superior' }}</q-tooltip>
        </q-btn>
      </div>
    </div>

    <!-- Atajo & Visibilidad Badges -->
    <div class="row items-center q-gutter-xs q-mb-sm">
      <q-badge
        v-if="note.shortcut"
        rounded
        color="deep-purple-9"
        text-color="deep-purple-1"
        class="text-weight-bold font-mono text-caption q-px-xs"
      >
        #{{ note.shortcut }}
      </q-badge>

      <q-badge
        rounded
        :color="visibilityColor"
        class="text-caption q-px-xs"
      >
        <q-icon :name="visibilityIcon" size="12px" class="q-mr-xs" />
        {{ visibilityLabel }}
      </q-badge>

      <q-badge
        v-if="note.is_archived"
        rounded
        color="grey-8"
        text-color="grey-3"
        class="text-caption q-px-xs"
      >
        <q-icon name="sym_r_archive" size="12px" class="q-mr-xs" />
        Archivada
      </q-badge>
    </div>

    <!-- Contenido de la Nota -->
    <div class="xf-keep-card__body q-mb-sm" @click="canEdit ? emit('edit', note) : undefined">
      <p class="xf-keep-card__text">{{ note.content }}</p>
    </div>

    <!-- Metadatos de Enlace: Categorías, Estados, Etiquetas -->
    <div
      v-if="hasEntities"
      class="xf-keep-card__entities row items-center q-gutter-xs q-mb-sm"
    >
      <!-- Categorías / Subcategorías -->
      <q-chip
        v-for="cat in note.categories"
        :key="cat.id"
        dense
        size="sm"
        class="entity-chip"
        :style="{ borderColor: cat.color || '#10b981', color: '#e2e8f0' }"
      >
        <q-avatar size="14px" :style="{ backgroundColor: cat.color || '#10b981' }">
          <q-icon name="sym_r_school" size="10px" color="white" />
        </q-avatar>
        {{ cat.name }}
      </q-chip>

      <!-- Estados de Lead -->
      <q-chip
        v-for="st in note.custom_statuses"
        :key="st.id"
        dense
        size="sm"
        class="entity-chip"
        :style="{ borderColor: st.color || '#3b82f6', color: '#e2e8f0' }"
      >
        <q-avatar size="14px" :style="{ backgroundColor: st.color || '#3b82f6' }">
          <q-icon name="sym_r_flag" size="10px" color="white" />
        </q-avatar>
        {{ st.name }}
      </q-chip>

      <!-- Etiquetas -->
      <q-chip
        v-for="tag in note.tags"
        :key="tag.id"
        dense
        size="sm"
        class="entity-chip"
        :style="{ borderColor: tag.color_hex || '#00a884', color: '#e2e8f0' }"
      >
        <q-icon name="sym_r_label" size="12px" :style="{ color: tag.color_hex || '#00a884' }" class="q-mr-xs" />
        {{ tag.name }}
      </q-chip>
    </div>

    <!-- Card Footer: Autor y Acciones -->
    <div class="xf-keep-card__footer row items-center justify-between no-wrap q-pt-xs">
      <div class="text-caption text-grey-5 ellipsis font-mono" style="font-size: 0.72rem">
        {{ note.user?.name || 'Administración' }}
      </div>

      <div class="row items-center no-wrap q-gutter-x-none">
        <q-btn
          flat
          round
          dense
          size="sm"
          color="grey-4"
          icon="sym_r_content_copy"
          @click.stop="copyContent"
        >
          <q-tooltip>Copiar texto al portapapeles</q-tooltip>
        </q-btn>

        <q-btn
          v-if="canEdit"
          flat
          round
          dense
          size="sm"
          color="teal-3"
          icon="sym_r_edit"
          @click.stop="emit('edit', note)"
        >
          <q-tooltip>Editar nota</q-tooltip>
        </q-btn>

        <q-btn
          v-if="canEdit"
          flat
          round
          dense
          size="sm"
          :color="note.is_archived ? 'amber-4' : 'grey-4'"
          :icon="note.is_archived ? 'sym_r_unarchive' : 'sym_r_archive'"
          @click.stop="emit('toggle-archive', note.id)"
        >
          <q-tooltip>{{ note.is_archived ? 'Desarchivar nota' : 'Archivar nota' }}</q-tooltip>
        </q-btn>

        <q-btn
          v-if="canEdit"
          flat
          round
          dense
          size="sm"
          color="negative"
          icon="sym_r_delete"
          @click.stop="emit('delete', note.id)"
        >
          <q-tooltip>Eliminar nota</q-tooltip>
        </q-btn>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { copyToClipboard, useQuasar } from 'quasar'
import type { Note } from '../types/note.types'

const props = defineProps<{
  note: Note
  canEdit?: boolean
}>()

const emit = defineEmits<{
  edit: [note: Note]
  delete: [id: string]
  'toggle-pin': [id: string]
  'toggle-archive': [id: string]
}>()

const $q = useQuasar()

const cardBgColor = computed(() => {
  return props.note.color || '#1e293b'
})

const cardBorderColor = computed(() => {
  if (props.note.is_pinned) return 'rgba(251, 191, 36, 0.4)'
  return 'rgba(255, 255, 255, 0.08)'
})

const visibilityLabel = computed(() => {
  switch (props.note.visibility) {
    case 'categories':
      return 'Por Categorías'
    case 'private':
      return 'Privada'
    case 'all':
    default:
      return 'Todo el Equipo'
  }
})

const visibilityIcon = computed(() => {
  switch (props.note.visibility) {
    case 'categories':
      return 'sym_r_school'
    case 'private':
      return 'sym_r_lock'
    case 'all':
    default:
      return 'sym_r_groups'
  }
})

const visibilityColor = computed(() => {
  switch (props.note.visibility) {
    case 'categories':
      return 'purple-8'
    case 'private':
      return 'amber-9'
    case 'all':
    default:
      return 'teal-8'
  }
})

const hasEntities = computed(() => {
  return (
    (props.note.categories && props.note.categories.length > 0) ||
    (props.note.custom_statuses && props.note.custom_statuses.length > 0) ||
    (props.note.tags && props.note.tags.length > 0)
  )
})

async function copyContent() {
  try {
    await copyToClipboard(props.note.content)
    $q.notify({
      type: 'positive',
      message: 'Texto de la nota copiado al portapapeles',
      timeout: 2000,
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
</script>

<style scoped lang="scss">
.xf-keep-card {
  position: relative;
  display: flex;
  flex-direction: column;
  padding: 14px 16px;
  border-radius: 14px;
  border: 1px solid rgba(255, 255, 255, 0.08);
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.25);
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  color: #f8fafc;
  min-height: 160px;
  break-inside: avoid;
  margin-bottom: 16px;

  &:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.35);
    border-color: rgba(255, 255, 255, 0.2);
  }

  &__title {
    color: #ffffff;
    letter-spacing: -0.01em;
  }

  &__body {
    flex: 1;
    cursor: pointer;
  }

  &__text {
    margin: 0;
    white-space: pre-wrap;
    word-break: break-word;
    font-size: 0.88rem;
    line-height: 1.5;
    color: #e2e8f0;
  }

  &__entities {
    margin-top: auto;
  }

  &__footer {
    border-top: 1px solid rgba(255, 255, 255, 0.08);
  }
}

.entity-chip {
  background: rgba(15, 23, 42, 0.6) !important;
  border: 1px solid;
  font-weight: 500;
  font-size: 0.72rem;
}
</style>
