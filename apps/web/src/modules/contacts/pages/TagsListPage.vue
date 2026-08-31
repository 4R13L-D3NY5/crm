<template>
  <div class="whaticket-tags-page">
    <!-- Header de Etiquetas -->
    <div class="q-mb-md">
      <h1 class="text-h5 text-bold text-white q-my-none">Etiquetas</h1>
      <p class="text-caption text-grey-4 q-mt-xs q-mb-none">
        Crea y organiza las etiquetas usadas para clasificar contactos y atenciones.
      </p>
    </div>

    <!-- Barra de Búsqueda y Botón Nueva Etiqueta -->
    <div class="row items-center justify-between q-col-gutter-sm q-mb-md">
      <div class="col-12 col-sm-6 col-md-4">
        <q-input
          v-model="search"
          dense
          outlined
          dark
          placeholder="Buscar etiqueta..."
          class="whaticket-filter-input"
        >
          <template #prepend>
            <q-icon name="sym_r_search" size="18px" color="grey-5" />
          </template>
        </q-input>
      </div>

      <div class="col-12 col-sm-6 col-md-4 row items-center justify-end q-gutter-sm">
        <!-- Switch vista -->
        <div class="whaticket-view-toggle">
          <button class="whaticket-view-btn whaticket-view-btn--active">
            <q-icon name="sym_r_list" size="18px" />
          </button>
          <button class="whaticket-view-btn">
            <q-icon name="sym_r_grid_view" size="18px" />
          </button>
        </div>

        <q-btn
          color="primary"
          label="+ Nueva etiqueta"
          unelevated
          no-caps
          class="whaticket-btn-primary"
          @click="openCreateTagDialog"
        />
      </div>
    </div>

    <!-- Tabla de Etiquetas (Exacto a la Captura 5 de Whaticket) -->
    <q-card flat bordered class="whaticket-table-card">
      <q-markup-table flat dark class="whaticket-table">
        <thead>
          <tr>
            <th class="text-left">NOMBRE</th>
            <th class="text-center" style="width: 250px">
              <span class="row items-center justify-center q-gutter-x-xs">
                <span>Color</span>
                <q-icon name="sym_r_info" size="14px" color="grey-5" />
              </span>
            </th>
            <th class="text-right" style="width: 150px">ACCIONES</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="tag in filteredTags" :key="tag.id" class="whaticket-table-row">
            <!-- Nombre -->
            <td class="text-left text-white text-weight-medium">
              {{ tag.name }}
            </td>

            <!-- Pastilla de Color -->
            <td class="text-center">
              <span
                v-if="tag.color"
                class="whaticket-color-chip"
                :style="{ backgroundColor: tag.color }"
              >
                {{ tag.color }}
              </span>
              <span v-else class="whaticket-color-chip whaticket-color-chip--none">
                Sin color
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
                />
                <q-btn
                  flat
                  round
                  dense
                  icon="sym_r_delete"
                  color="negative"
                  size="sm"
                  @click="deleteTag(tag.id)"
                />
              </div>
            </td>
          </tr>
        </tbody>
      </q-markup-table>
    </q-card>

    <!-- Dialog Crear / Editar Etiqueta -->
    <q-dialog v-model="isDialogOpen">
      <q-card style="min-width: 400px" class="whaticket-modal-card q-pa-md">
        <q-card-section>
          <div class="text-h6 text-bold text-white">
            {{ currentTag?.id ? 'Editar Etiqueta' : 'Nueva Etiqueta' }}
          </div>
        </q-card-section>

        <q-card-section class="q-gutter-y-md">
          <q-input
            v-model="form.name"
            label="Nombre de la etiqueta"
            outlined
            dark
            dense
          />
          <div class="row items-center q-gutter-x-sm">
            <span class="text-caption text-grey-4">Color de etiqueta:</span>
            <input
              v-model="form.color"
              type="color"
              style="width: 40px; height: 32px; border: none; border-radius: 6px; cursor: pointer"
            />
            <span class="text-bold text-caption text-white">{{ form.color }}</span>
          </div>
        </q-card-section>

        <q-card-actions align="right" class="q-pt-md">
          <q-btn flat label="Cancelar" color="grey-4" no-caps v-close-popup />
          <q-btn
            unelevated
            label="Guardar"
            color="primary"
            no-caps
            class="whaticket-btn-primary"
            @click="saveTag"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </div>
</template>

<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useAppNotify } from '@/shared/composables/useAppNotify'

const notify = useAppNotify()

interface TagItem {
  id: string
  name: string
  color: string | null
}

const search = ref('')
const isDialogOpen = ref(false)
const currentTag = ref<TagItem | null>(null)

const form = reactive({
  name: '',
  color: '#00a884',
})

const tags = ref<TagItem[]>([
  { id: '1', name: 'Adm. de Empresas', color: null },
  { id: '2', name: 'agos', color: null },
  { id: '3', name: 'Agosto-26', color: null },
  { id: '4', name: 'ALTO 1/2025', color: '#33ab9f' },
  { id: '5', name: 'Arte y Escultura', color: '#9500ae' },
  { id: '6', name: 'Beca Anticipada', color: null },
  { id: '7', name: 'Beca patriota', color: '#1c5c56' },
  { id: '8', name: 'beca retoma', color: null },
  { id: '9', name: 'Bioquímica y Farmacia', color: '#9778be' },
  { id: '10', name: 'cbba', color: null },
])

const filteredTags = computed(() => {
  if (!search.value) return tags.value
  return tags.value.filter((t) =>
    t.name.toLowerCase().includes(search.value.toLowerCase()),
  )
})

function openCreateTagDialog() {
  currentTag.value = null
  form.name = ''
  form.color = '#00a884'
  isDialogOpen.value = true
}

function editTag(tag: TagItem) {
  currentTag.value = tag
  form.name = tag.name
  form.color = tag.color || '#00a884'
  isDialogOpen.value = true
}

function saveTag() {
  if (!form.name.trim()) return

  if (currentTag.value) {
    currentTag.value.name = form.name
    currentTag.value.color = form.color
    notify.success({ message: 'Etiqueta actualizada.' })
  } else {
    tags.value.push({
      id: Date.now().toString(),
      name: form.name,
      color: form.color,
    })
    notify.success({ message: 'Etiqueta creada.' })
  }
  isDialogOpen.value = false
}

function deleteTag(id: string) {
  tags.value = tags.value.filter((t) => t.id !== id)
  notify.warning({ message: 'Etiqueta eliminada.' })
}
</script>

<style scoped lang="scss">
.whaticket-tags-page {
  padding: 24px 32px;
  background-color: var(--crm-bg-app);
  min-height: calc(100vh - 52px);
}

.whaticket-filter-input {
  .q-field__control {
    background: #182229 !important;
    border-radius: 8px !important;
    height: 36px !important;
    min-height: 36px !important;
    font-size: 0.85rem;
  }
}

.whaticket-view-toggle {
  display: flex;
  background: #182229;
  border-radius: 8px;
  padding: 2px;
  border: 1px solid rgba(255, 255, 255, 0.08);
}

.whaticket-view-btn {
  background: transparent;
  border: none;
  color: #8696a0;
  padding: 4px 8px;
  border-radius: 6px;
  cursor: pointer;
  display: flex;
  align-items: center;

  &--active {
    background: #202c33;
    color: #ffffff;
  }
}

.whaticket-btn-primary {
  background-color: #00a884 !important;
  color: #ffffff !important;
  font-weight: 600;
  border-radius: 8px;
  height: 36px;
}

.whaticket-table-card {
  background-color: #182229 !important;
  border: 1px solid rgba(255, 255, 255, 0.08) !important;
  border-radius: 12px !important;
  overflow: hidden;
}

.whaticket-table {
  background-color: transparent !important;

  thead tr {
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    th {
      font-size: 0.72rem;
      font-weight: 700;
      letter-spacing: 0.05em;
      color: #8696a0;
      padding: 12px 16px;
    }
  }

  tbody tr {
    border-bottom: 1px solid rgba(255, 255, 255, 0.04);
    transition: background-color 0.12s ease;

    &:hover {
      background-color: rgba(255, 255, 255, 0.03);
    }

    td {
      padding: 12px 16px;
      font-size: 0.85rem;
    }
  }
}

.whaticket-color-chip {
  display: inline-block;
  padding: 2px 12px;
  border-radius: 12px;
  font-size: 0.75rem;
  font-weight: 600;
  color: #ffffff;

  &--none {
    background-color: rgba(255, 255, 255, 0.06);
    color: #8696a0;
  }
}

.whaticket-modal-card {
  background-color: #111b21 !important;
  border: 1px solid rgba(255, 255, 255, 0.1) !important;
  border-radius: 16px !important;
}
</style>
