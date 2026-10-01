<template>
  <div class="tags-list-page">
    <!-- Header de Etiquetas -->
    <div class="row items-center justify-between q-mb-lg">
      <div>
        <div class="row items-center q-gutter-x-sm">
          <h1 class="text-h5 text-bold text-white q-my-none">Etiquetas & Segmentación</h1>
          <q-badge color="teal-9" text-color="teal-2" rounded class="q-px-sm">
            {{ tags.length }} registradas
          </q-badge>
        </div>
        <p class="text-caption text-grey-4 q-mt-xs q-mb-none">
          Clasifica tus contactos, conversaciones y oportunidades con identificadores visuales en color.
        </p>
      </div>

      <q-btn
        color="primary"
        icon="sym_r_add"
        label="+ Nueva Etiqueta"
        unelevated
        no-caps
        class="xf-btn-primary"
        @click="openCreateTagDialog"
      />
    </div>

    <!-- Barra de Búsqueda -->
    <div class="row items-center justify-between q-col-gutter-sm q-mb-md">
      <div class="col-12 col-sm-6 col-md-4">
        <q-input
          v-model="search"
          dense
          outlined
          dark
          placeholder="Buscar etiqueta..."
          class="tags-filter-input"
        >
          <template #prepend>
            <q-icon name="sym_r_search" size="18px" color="teal-4" />
          </template>
          <template v-if="search" #append>
            <q-icon
              name="sym_r_close"
              size="16px"
              class="cursor-pointer text-grey-5"
              @click="search = ''"
            />
          </template>
        </q-input>
      </div>
    </div>

    <!-- Tabla de Etiquetas con Colores Hexadecimales -->
    <q-card flat bordered class="tags-table-card">
      <q-markup-table flat dark class="tags-table">
        <thead>
          <tr>
            <th class="text-left">NOMBRE DE ETIQUETA</th>
            <th class="text-center" style="width: 220px">COLOR / IDENTIFICADOR</th>
            <th class="text-right" style="width: 140px">ACCIONES</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="tagsQuery.isLoading.value">
            <td colspan="3" class="text-center q-pa-lg text-grey-4">
              <q-spinner-dots size="32px" color="primary" />
              <div class="q-mt-sm">Cargando etiquetas del workspace...</div>
            </td>
          </tr>
          <tr v-else-if="filteredTags.length === 0">
            <td colspan="3" class="text-center q-pa-xl text-grey-4">
              <q-icon name="sym_r_label_off" size="44px" class="q-mb-sm text-grey-6" />
              <div class="text-subtitle1 text-white text-bold">No se encontraron etiquetas</div>
              <div class="text-caption text-grey-5">Crea una nueva etiqueta para comenzar a segmentar.</div>
            </td>
          </tr>
          <tr v-for="tag in filteredTags" v-else :key="tag.id" class="tags-table-row">
            <!-- Nombre -->
            <td class="text-left text-white text-weight-medium">
              <div class="row items-center q-gutter-x-sm">
                <span class="tag-bullet" :style="{ backgroundColor: tag.color_hex || '#00a884' }" />
                <span class="text-bold">{{ tag.name }}</span>
              </div>
            </td>

            <!-- Pastilla de Color -->
            <td class="text-center">
              <span
                class="tag-color-chip"
                :style="{ backgroundColor: tag.color_hex || '#00a884' }"
              >
                {{ tag.color_hex || '#00a884' }}
              </span>
            </td>

            <!-- Acciones -->
            <td class="text-right">
              <div class="row justify-end q-gutter-xs">
                <q-btn
                  flat
                  round
                  dense
                  icon="sym_r_edit"
                  color="grey-4"
                  size="sm"
                  @click="editTag(tag)"
                >
                  <q-tooltip>Editar etiqueta</q-tooltip>
                </q-btn>
                <q-btn
                  flat
                  round
                  dense
                  icon="sym_r_delete"
                  color="negative"
                  size="sm"
                  @click="confirmDeleteTag(tag)"
                >
                  <q-tooltip>Eliminar etiqueta</q-tooltip>
                </q-btn>
              </div>
            </td>
          </tr>
        </tbody>
      </q-markup-table>
    </q-card>

    <!-- Dialog Crear / Editar Etiqueta -->
    <q-dialog v-model="isDialogOpen">
      <q-card style="width: 440px; max-width: 95vw" class="tags-modal-card q-pa-md">
        <q-card-section class="q-pb-none">
          <div class="text-h6 text-bold text-white">
            {{ currentTag?.id ? 'Editar Etiqueta' : 'Nueva Etiqueta' }}
          </div>
          <div class="text-caption text-grey-4">
            Asigna un nombre representativo y un color distintivo.
          </div>
        </q-card-section>

        <q-card-section class="q-gutter-y-md q-pt-md">
          <q-input
            v-model="form.name"
            label="Nombre de la etiqueta *"
            outlined
            dark
            dense
            :rules="[val => !!val || 'El nombre es obligatorio']"
          />

          <div>
            <div class="text-caption text-grey-4 q-mb-xs">Color hexadecimal:</div>
            <div class="row items-center q-gutter-x-sm">
              <input
                v-model="form.color_hex"
                type="color"
                class="tag-color-picker"
              />
              <span class="text-bold text-caption text-white font-mono">{{ form.color_hex }}</span>
            </div>

            <!-- Paleta rápida de colores recomendados -->
            <div class="row q-gutter-xs q-mt-sm">
              <span
                v-for="color in presetColors"
                :key="color"
                class="tag-preset-dot cursor-pointer"
                :style="{ backgroundColor: color }"
                :class="{ 'tag-preset-dot--selected': form.color_hex === color }"
                @click="form.color_hex = color"
              />
            </div>
          </div>
        </q-card-section>

        <q-card-actions align="right" class="q-pt-md">
          <q-btn flat label="Cancelar" color="grey-4" no-caps v-close-popup />
          <q-btn
            unelevated
            label="Guardar Etiqueta"
            color="primary"
            no-caps
            class="xf-btn-primary"
            :loading="createTagMutation.isPending.value || updateTagMutation.isPending.value"
            @click="saveTag"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Diálogo de Confirmación para Eliminar Etiqueta -->
    <AppConfirmDialog
      v-model="isConfirmDeleteOpen"
      title="Eliminar etiqueta"
      :message="`¿Deseas eliminar la etiqueta '${tagToDelete?.name}'? Los contactos asociados perderán esta etiqueta.`"
      confirm-label="Eliminar"
      confirm-color="negative"
      :loading="deleteTagMutation.isPending.value"
      @confirm="executeDeleteTag"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useAppNotify } from '@/shared/composables/useAppNotify'
import AppConfirmDialog from '@/shared/components/AppConfirmDialog.vue'
import { useContactTags, useTagMutations } from '../composables/useContacts'
import type { ContactTag } from '../types/contact.types'

const notify = useAppNotify()

const search = ref('')
const isDialogOpen = ref(false)
const isConfirmDeleteOpen = ref(false)
const currentTag = ref<ContactTag | null>(null)
const tagToDelete = ref<ContactTag | null>(null)

const presetColors = [
  '#00a884',
  '#10b981',
  '#0284c7',
  '#06b6d4',
  '#6366f1',
  '#8b5cf6',
  '#ec4899',
  '#f59e0b',
  '#ef4444',
]

const form = reactive({
  name: '',
  color_hex: '#00a884',
})

const tagsQuery = useContactTags()
const { createTagMutation, updateTagMutation, deleteTagMutation } = useTagMutations()

const tags = computed<ContactTag[]>(() => tagsQuery.data.value ?? [])

const filteredTags = computed(() => {
  if (!search.value.trim()) return tags.value
  const q = search.value.toLowerCase()
  return tags.value.filter((t) => t.name.toLowerCase().includes(q))
})

function openCreateTagDialog() {
  currentTag.value = null
  form.name = ''
  form.color_hex = '#00a884'
  isDialogOpen.value = true
}

function editTag(tag: ContactTag) {
  currentTag.value = tag
  form.name = tag.name
  form.color_hex = tag.color_hex || '#00a884'
  isDialogOpen.value = true
}

function confirmDeleteTag(tag: ContactTag) {
  tagToDelete.value = tag
  isConfirmDeleteOpen.value = true
}

async function executeDeleteTag() {
  if (!tagToDelete.value) return
  try {
    await deleteTagMutation.mutateAsync(tagToDelete.value.id)
    notify.success({ message: 'Etiqueta eliminada correctamente.' })
    isConfirmDeleteOpen.value = false
    tagToDelete.value = null
  } catch {
    notify.error({ message: 'No se pudo eliminar la etiqueta.' })
  }
}

async function saveTag() {
  if (!form.name.trim()) return

  try {
    if (currentTag.value) {
      await updateTagMutation.mutateAsync({
        id: currentTag.value.id,
        payload: { name: form.name.trim(), color_hex: form.color_hex },
      })
      notify.success({ message: 'Etiqueta actualizada exitosamente.' })
    } else {
      await createTagMutation.mutateAsync({
        name: form.name.trim(),
        color_hex: form.color_hex,
      })
      notify.success({ message: 'Etiqueta creada exitosamente.' })
    }
    isDialogOpen.value = false
  } catch {
    notify.error({ message: 'No se pudo guardar la etiqueta.' })
  }
}
</script>

<style scoped lang="scss">
.tags-list-page {
  padding: 28px 36px;
  background-color: var(--crm-bg-app, #080c14);
  min-height: calc(100vh - 56px);
}

.tags-filter-input {
  min-width: 240px;
}

.tags-table-card {
  background: var(--crm-bg-card, #111827);
  border-radius: var(--crm-radius-card, 14px);
  overflow: hidden;
}

.tags-table {
  background: transparent;

  thead tr th {
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    color: var(--crm-color-muted, #94a3b8);
    border-bottom: 1px solid var(--crm-color-border, rgba(255, 255, 255, 0.07));
    padding: 14px 16px;
  }

  tbody tr {
    transition: background 0.15s ease;
    border-bottom: 1px solid var(--crm-color-border, rgba(255, 255, 255, 0.05));

    &:hover {
      background: rgba(255, 255, 255, 0.03);
    }
  }

  tbody td {
    padding: 12px 16px;
  }
}

.tag-bullet {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  display: inline-block;
}

.tag-color-chip {
  display: inline-flex;
  align-items: center;
  font-size: 0.75rem;
  font-weight: 600;
  font-family: var(--crm-font-mono, monospace);
  padding: 3px 10px;
  border-radius: 999px;
  color: #ffffff;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
}

.tag-color-picker {
  width: 42px;
  height: 34px;
  border: 1px solid rgba(255, 255, 255, 0.15);
  border-radius: 6px;
  cursor: pointer;
  background: transparent;
}

.tag-preset-dot {
  width: 22px;
  height: 22px;
  border-radius: 50%;
  display: inline-block;
  transition: transform 0.15s ease;

  &:hover {
    transform: scale(1.2);
  }

  &--selected {
    box-shadow: 0 0 0 2px #ffffff;
    transform: scale(1.15);
  }
}

.tags-modal-card {
  background: var(--crm-bg-card, #111827);
  border: 1px solid var(--crm-color-border, rgba(255, 255, 255, 0.08));
  border-radius: 14px;
}

.xf-btn-primary {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%) !important;
  font-weight: 600;
  border-radius: 8px;
}
</style>
