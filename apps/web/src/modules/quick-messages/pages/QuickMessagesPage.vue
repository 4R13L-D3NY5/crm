<template>
  <AppPage
    eyebrow="Productividad"
    title="Respuestas rápidas"
    description="Configura atajos (ej: /precios, /bienvenida) para responder al instante en tus chats sin redactar el mismo mensaje repetidas veces."
  >
    <template #actions>
      <q-input
        v-model="search"
        outlined
        dense
        clearable
        placeholder="Buscar atajo o texto..."
        class="quick-messages__search"
      >
        <template #prepend>
          <q-icon name="sym_r_search" size="18px" />
        </template>
      </q-input>

      <q-btn
        color="primary"
        label="Nueva respuesta rápida"
        icon="sym_r_add"
        unelevated
        @click="openCreateDialog"
      />
    </template>

    <q-banner
      v-if="quickMessagesQuery.isError.value"
      rounded
      class="quick-messages__banner bg-red-1 text-negative q-mb-md"
    >
      No se pudieron cargar las respuestas rápidas desde el servidor.
    </q-banner>

    <AppLoadingState
      v-if="quickMessagesQuery.isLoading.value"
      title="Cargando respuestas rápidas"
      description="Obteniendo la lista de atajos y plantillas configuradas..."
    />

    <div
      v-else-if="filteredQuickMessages.length > 0"
      class="quick-messages__grid"
    >
      <q-card
        v-for="item in filteredQuickMessages"
        :key="item.id"
        flat
        bordered
        class="quick-messages__card"
      >
        <q-card-section class="quick-messages__card-header">
          <div class="row items-center q-gutter-x-sm">
            <q-badge
              color="teal-10"
              text-color="teal-2"
              class="quick-messages__badge text-weight-bold"
            >
              /{{ item.shortcut.replace(/^\/+/, '') }}
            </q-badge>
            <q-badge
              :color="item.is_general ? 'blue-9' : 'purple-9'"
              outline
              class="text-caption"
            >
              {{ item.is_general ? 'General' : 'Personal' }}
            </q-badge>
          </div>

          <div class="row items-center q-gutter-x-xs">
            <q-btn
              flat
              round
              dense
              size="sm"
              icon="sym_r_edit"
              color="grey-4"
              @click="openEditDialog(item)"
            >
              <q-tooltip>Editar plantilla</q-tooltip>
            </q-btn>
            <q-btn
              flat
              round
              dense
              size="sm"
              icon="sym_r_delete"
              color="negative"
              @click="promptDelete(item)"
            >
              <q-tooltip>Eliminar</q-tooltip>
            </q-btn>
          </div>
        </q-card-section>

        <q-separator dark class="quick-messages__card-separator" />

        <q-card-section class="quick-messages__card-body">
          <p class="quick-messages__card-text">
            {{ item.message }}
          </p>
        </q-card-section>
      </q-card>
    </div>

    <AppEmptyState
      v-else-if="search"
      title="Sin coincidencias"
      description="No encontramos respuestas rápidas que coincidan con tu búsqueda."
      icon="sym_r_search_off"
      class="quick-messages__empty"
    />

    <AppEmptyState
      v-else
      title="No hay respuestas rápidas configuradas"
      description="Crea tu primer atajo para responder con un solo clic o escribiendo / desde la bandeja de WhatsApp."
      icon="sym_r_bolt"
      class="quick-messages__empty"
    />

    <!-- Diálogo Crear / Editar -->
    <q-dialog v-model="isDialogOpen" persistent>
      <q-card style="width: 540px; max-width: 95vw" class="quick-messages__dialog-card">
        <q-card-section class="row items-center justify-between q-pb-none">
          <div class="text-subtitle1 text-weight-bold text-white">
            {{ editingItem ? 'Editar respuesta rápida' : 'Nueva respuesta rápida' }}
          </div>
          <q-btn flat round dense icon="sym_r_close" color="grey-4" v-close-popup />
        </q-card-section>

        <q-card-section class="q-gutter-y-md q-pt-md">
          <q-input
            v-model="form.shortcut"
            label="Atajo *"
            prefix="/"
            outlined
            dense
            placeholder="ej: precios, bienvenida, soporte"
            hint="Escribe este atajo en el chat para insertar la plantilla."
            :rules="[val => !!val?.trim() || 'El atajo es requerido']"
          />

          <div>
            <div class="row items-center justify-between q-mb-xs">
              <span class="text-caption text-grey-4">Variables dinámicas:</span>
              <div class="row q-gutter-x-xs">
                <q-chip
                  clickable
                  dense
                  outline
                  color="teal-4"
                  size="sm"
                  @click="insertVariable('{{name}}')"
                >
                  &#123;&#123;name&#125;&#125;
                </q-chip>
                <q-chip
                  clickable
                  dense
                  outline
                  color="teal-4"
                  size="sm"
                  @click="insertVariable('{{phone}}')"
                >
                  &#123;&#123;phone&#125;&#125;
                </q-chip>
              </div>
            </div>

            <q-input
              v-model="form.message"
              label="Mensaje completo *"
              type="textarea"
              outlined
              dense
              rows="4"
              placeholder="Escribe el texto que se enviará al cliente..."
              :rules="[val => !!val?.trim() || 'El mensaje es requerido']"
            />
          </div>

          <q-toggle
            v-model="form.is_general"
            label="Compartir con todo el equipo (General)"
            color="primary"
            class="text-grey-3"
          />
        </q-card-section>

        <q-card-actions align="right" class="q-px-md q-pb-md">
          <q-btn flat label="Cancelar" color="grey-4" v-close-popup />
          <q-btn
            unelevated
            label="Guardar respuesta"
            color="primary"
            :loading="isSubmitting"
            @click="handleSubmit"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Diálogo Confirmar Eliminación -->
    <AppConfirmDialog
      v-model="isDeleteDialogOpen"
      title="Eliminar respuesta rápida"
      message="¿Seguro que deseas eliminar este atajo? Los asesores ya no podrán utilizarlo en la bandeja."
      confirm-label="Eliminar"
      :loading="deleteMutation.isPending.value"
      @confirm="handleConfirmDelete"
    />
  </AppPage>
</template>

<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import AppPage from '@/shared/components/AppPage.vue'
import AppLoadingState from '@/shared/components/AppLoadingState.vue'
import AppEmptyState from '@/shared/components/AppEmptyState.vue'
import AppConfirmDialog from '@/shared/components/AppConfirmDialog.vue'
import { useAppNotify } from '@/shared/composables/useAppNotify'
import { useQuickMessageMutations, useQuickMessages } from '../composables/useQuickMessages'
import type { QuickMessage } from '../types/quick-message.types'

const notify = useAppNotify()
const quickMessagesQuery = useQuickMessages()
const { createMutation, updateMutation, deleteMutation } = useQuickMessageMutations()

const search = ref('')
const isDialogOpen = ref(false)
const isDeleteDialogOpen = ref(false)
const editingItem = ref<QuickMessage | null>(null)
const itemToDelete = ref<QuickMessage | null>(null)

const form = reactive({
  shortcut: '',
  message: '',
  is_general: true,
})

const isSubmitting = computed(
  () => createMutation.isPending.value || updateMutation.isPending.value,
)

const filteredQuickMessages = computed(() => {
  const list = quickMessagesQuery.data.value ?? []
  const query = search.value.trim().toLowerCase()

  if (!query) return list

  return list.filter((item) => {
    return (
      item.shortcut.toLowerCase().includes(query) ||
      item.message.toLowerCase().includes(query)
    )
  })
})

function openCreateDialog() {
  editingItem.value = null
  form.shortcut = ''
  form.message = ''
  form.is_general = true
  isDialogOpen.value = true
}

function openEditDialog(item: QuickMessage) {
  editingItem.value = item
  form.shortcut = item.shortcut.replace(/^\/+/, '')
  form.message = item.message
  form.is_general = item.is_general
  isDialogOpen.value = true
}

function insertVariable(variable: string) {
  form.message += ` ${variable} `
}

async function handleSubmit() {
  const cleanShortcut = form.shortcut.trim().replace(/^\/+/, '')
  const cleanMessage = form.message.trim()

  if (!cleanShortcut || !cleanMessage) {
    notify.error('Datos incompletos', 'Completa el atajo y el mensaje.')
    return
  }

  try {
    if (editingItem.value) {
      await updateMutation.mutateAsync({
        id: editingItem.value.id,
        payload: {
          shortcut: cleanShortcut,
          message: cleanMessage,
          is_general: form.is_general,
        },
      })
      notify.success('Respuesta rápida actualizada', `/${cleanShortcut} se guardó correctamente.`)
    } else {
      await createMutation.mutateAsync({
        shortcut: cleanShortcut,
        message: cleanMessage,
        is_general: form.is_general,
      })
      notify.success('Respuesta rápida creada', `/${cleanShortcut} ya está disponible para el equipo.`)
    }

    isDialogOpen.value = false
  } catch (error) {
    notify.fromError(error, 'No se pudo guardar la respuesta rápida.')
  }
}

function promptDelete(item: QuickMessage) {
  itemToDelete.value = item
  isDeleteDialogOpen.value = true
}

async function handleConfirmDelete() {
  if (!itemToDelete.value) return

  try {
    await deleteMutation.mutateAsync(itemToDelete.value.id)
    notify.info('Respuesta rápida eliminada', `El atajo /${itemToDelete.value.shortcut} fue retirado.`)
    itemToDelete.value = null
    isDeleteDialogOpen.value = false
  } catch (error) {
    notify.fromError(error, 'No se pudo eliminar la respuesta rápida.')
  }
}
</script>

<style scoped lang="scss">
.quick-messages__search {
  min-width: 260px;
}

.quick-messages__grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
  gap: 16px;
}

.quick-messages__card {
  border-radius: var(--crm-radius-card, 16px);
  background: var(--crm-bg-card, #0f1c2b);
  border: 1px solid var(--crm-color-border, rgba(255, 255, 255, 0.08));
  display: flex;
  flex-direction: column;
  transition: transform 0.2s ease, border-color 0.2s ease;

  &:hover {
    border-color: rgba(77, 208, 225, 0.35);
  }
}

.quick-messages__card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 16px;
}

.quick-messages__card-separator {
  border-color: var(--crm-color-border, rgba(255, 255, 255, 0.06));
}

.quick-messages__card-body {
  padding: 14px 16px;
  flex: 1;
}

.quick-messages__card-text {
  margin: 0;
  color: var(--crm-color-ink, #e0e6ed);
  font-size: 0.92rem;
  line-height: 1.5;
  white-space: pre-line;
}

.quick-messages__badge {
  font-family: monospace;
  font-size: 0.85rem;
  padding: 4px 8px;
  border-radius: 6px;
}

.quick-messages__dialog-card {
  background: var(--crm-bg-card, #101e2e);
  border-radius: 16px;
  border: 1px solid var(--crm-color-border, rgba(255, 255, 255, 0.1));
}

.quick-messages__empty,
.quick-messages__banner {
  border-radius: var(--crm-radius-card, 16px);
}
</style>
