<template>
  <div class="contact-list-page">
    <!-- Header de Audiencia & Contactos -->
    <div class="row items-center justify-between q-col-gutter-sm q-mb-lg">
      <div class="col-12 col-md-auto">
        <div class="row items-center q-gutter-x-sm">
          <h1 class="text-h5 text-bold text-white q-my-none">Audiencia & Contactos</h1>
          <q-badge color="teal-9" text-color="teal-2" rounded class="q-px-sm">
            {{ totalContacts }} total
          </q-badge>
        </div>
        <p class="text-caption text-grey-4 q-mt-xs q-mb-none">
          Directorio centralizado de clientes, segmentación omnicanal y trazabilidad 360°.
        </p>
      </div>

      <!-- Acciones Principales -->
      <div class="col-12 col-md-auto row items-center q-gutter-sm">
        <!-- Menú Desplegable de Exportación -->
        <q-btn-dropdown
          outline
          dense
          no-caps
          color="teal-3"
          icon="sym_r_download"
          label="Exportar"
          class="q-px-md xf-btn-secondary"
          :loading="isExporting"
        >
          <q-list dark class="bg-dark text-white" style="min-width: 270px;">
            <q-item-label header class="text-grey-4 text-caption text-bold text-uppercase">
              Descargar Datos
            </q-item-label>

            <!-- Opciones si hay seleccionados -->
            <template v-if="selectedContactIds.length > 0">
              <q-item clickable v-close-popup @click="exportSelected('excel')">
                <q-item-section avatar>
                  <q-icon name="sym_r_table_view" color="positive" />
                </q-item-section>
                <q-item-section>
                  <q-item-label class="text-bold">Selección a Excel (.xlsx)</q-item-label>
                  <q-item-label caption class="text-teal-3">
                    {{ selectedContactIds.length }} contactos marcados
                  </q-item-label>
                </q-item-section>
              </q-item>

              <q-item clickable v-close-popup @click="exportSelected('pdf')">
                <q-item-section avatar>
                  <q-icon name="sym_r_picture_as_pdf" color="red-5" />
                </q-item-section>
                <q-item-section>
                  <q-item-label class="text-bold">Selección a Reporte PDF</q-item-label>
                  <q-item-label caption class="text-red-3">
                    Reporte formal institucional
                  </q-item-label>
                </q-item-section>
              </q-item>

              <q-separator dark class="q-my-xs" />
            </template>

            <!-- Vista actual -->
            <q-item clickable v-close-popup @click="exportCurrentView('excel')">
              <q-item-section avatar>
                <q-icon name="sym_r_file_download" color="teal-4" />
              </q-item-section>
              <q-item-section>
                <q-item-label>Vista actual a Excel (.xlsx)</q-item-label>
                <q-item-label caption class="text-grey-4">
                  {{ contacts.length }} contactos visibles
                </q-item-label>
              </q-item-section>
            </q-item>

            <q-item clickable v-close-popup @click="exportCurrentView('pdf')">
              <q-item-section avatar>
                <q-icon name="sym_r_picture_as_pdf" color="cyan-4" />
              </q-item-section>
              <q-item-section>
                <q-item-label>Vista actual a Reporte PDF</q-item-label>
                <q-item-label caption class="text-grey-4">
                  {{ contacts.length }} contactos visibles
                </q-item-label>
              </q-item-section>
            </q-item>

            <q-separator dark class="q-my-xs" />

            <!-- Todos filtrados -->
            <q-item clickable v-close-popup @click="exportAllFiltered('excel')">
              <q-item-section avatar>
                <q-icon name="sym_r_download_for_offline" color="positive" />
              </q-item-section>
              <q-item-section>
                <q-item-label>Todos los filtrados a Excel</q-item-label>
                <q-item-label caption class="text-grey-4">
                  Catálogo total ({{ totalContacts }} contactos)
                </q-item-label>
              </q-item-section>
            </q-item>

            <q-item clickable v-close-popup @click="exportAllFiltered('pdf')">
              <q-item-section avatar>
                <q-icon name="sym_r_picture_as_pdf" color="red-4" />
              </q-item-section>
              <q-item-section>
                <q-item-label>Todos los filtrados a Reporte PDF</q-item-label>
                <q-item-label caption class="text-grey-4">
                  Catálogo total ({{ totalContacts }} contactos)
                </q-item-label>
              </q-item-section>
            </q-item>
          </q-list>
        </q-btn-dropdown>

        <q-btn
          outline
          dense
          no-caps
          color="cyan-4"
          icon="sym_r_upload_file"
          label="Importar Masivo"
          class="q-px-md xf-btn-secondary"
          @click="isImportOpen = true"
        />

        <q-btn
          unelevated
          no-caps
          color="primary"
          icon="sym_r_person_add"
          label="+ Nuevo Contacto"
          class="q-px-md xf-btn-primary"
          @click="openCreateDialog"
        />
      </div>
    </div>

    <!-- Barra de Filtros Desacoplada -->
    <ContactFilterBar
      v-model:search="filters.search"
      v-model:channel="filters.channel"
      v-model:category-ids="filters.category_ids"
      v-model:custom-status-ids="filters.custom_status_ids"
      v-model:tag-names="filters.tags"
      v-model:view-mode="viewMode"
      :tags="tagsQuery.data.value ?? []"
    />

    <!-- Barra de Acciones Masivas Flotante/Sticky al Seleccionar Contactos -->
    <transition name="q-transition--slide-down">
      <div
        v-if="selectedContactIds.length > 0"
        class="bulk-action-banner row items-center justify-between q-pa-sm q-px-md q-mb-md"
      >
        <div class="row items-center q-gutter-x-sm">
          <q-badge color="positive" text-color="dark" class="text-bold q-px-sm">
            {{ selectedContactIds.length }} seleccionados
          </q-badge>
          <span class="text-caption text-white">Contactos marcados para exportación o difusión</span>
          <q-btn
            flat
            dense
            no-caps
            size="sm"
            color="grey-4"
            icon="sym_r_close"
            label="Deseleccionar"
            @click="selectedContactIds = []"
          />
        </div>

        <div class="row items-center q-gutter-x-sm">
          <!-- Botones directos de descarga de selección solicitados por el usuario -->
          <q-btn
            unelevated
            dense
            no-caps
            color="positive"
            text-color="dark"
            icon="sym_r_table_view"
            label="Descargar Excel"
            class="text-bold q-px-md shadow-1"
            :loading="isExporting"
            @click="exportSelected('excel')"
          >
            <q-tooltip>Descargar los {{ selectedContactIds.length }} seleccionados en Excel (.xlsx)</q-tooltip>
          </q-btn>

          <q-btn
            unelevated
            dense
            no-caps
            color="red-7"
            icon="sym_r_picture_as_pdf"
            label="Reporte PDF"
            class="text-bold q-px-md shadow-1"
            :loading="isExporting"
            @click="exportSelected('pdf')"
          >
            <q-tooltip>Descargar reporte formal en PDF de los {{ selectedContactIds.length }} seleccionados</q-tooltip>
          </q-btn>

          <q-separator vertical dark inset class="q-mx-xs" />

          <q-btn
            outline
            dense
            no-caps
            color="teal-3"
            icon="sym_r_schedule_send"
            label="Programar Envío 📅"
            class="text-bold q-px-md xf-btn-secondary"
            @click="openBulkSchedule"
          />
          <q-btn
            unelevated
            no-caps
            color="positive"
            text-color="dark"
            icon="sym_r_forward_to_inbox"
            label="Enviar Ahora"
            class="text-bold q-px-md shadow-2"
            @click="openBulkImmediate"
          />
        </div>
      </div>
    </transition>

    <!-- Modo Vista: Tabla -->
    <ContactTable
      v-if="viewMode === 'table'"
      v-model:selected-ids="selectedContactIds"
      :contacts="contacts"
      :loading="contactsQuery.isLoading.value"
      @view="openDetailDrawer"
      @edit="openEditDialog"
      @delete="confirmDelete"
    />

    <!-- Modo Vista: Cuadrícula -->
    <div v-else class="row q-col-gutter-md">
      <div v-if="contactsQuery.isLoading.value" class="col-12 text-center q-pa-xl">
        <q-spinner-dots size="32px" color="primary" />
      </div>
      <div v-else-if="contacts.length === 0" class="col-12 text-center q-pa-xl text-grey-4">
        <q-icon name="sym_r_person_off" size="48px" class="q-mb-sm text-grey-6" />
        <div class="text-subtitle1 text-white text-bold">No se encontraron contactos</div>
      </div>
      <ContactGridCard
        v-for="c in contacts"
        v-else
        :key="c.id"
        :contact="c"
        :selected="selectedContactIds.includes(c.id)"
        @toggle-select="toggleSelection(c.id)"
        @view="openDetailDrawer"
        @edit="openEditDialog"
        @delete="confirmDelete"
      />
    </div>

    <!-- Paginación -->
    <div v-if="totalPages > 1" class="row justify-center q-mt-lg">
      <q-pagination
        v-model="filters.page"
        :max="totalPages"
        :max-pages="6"
        color="primary"
        dark
        boundary-numbers
      />
    </div>

    <!-- Dumb Components Modals & Drawer -->
    <ContactDetailDrawer
      v-model="isDrawerOpen"
      :contact="selectedContact"
      @edit="openEditDialog"
    />

    <ContactFormDialog
      v-model="isFormOpen"
      :contact="selectedContact"
      :available-tags="tagsQuery.data.value ?? []"
      :loading="createMutation.isPending.value || updateMutation.isPending.value"
      @submit="handleFormSubmit"
    />

    <ContactImportModal
      v-model="isImportOpen"
      :loading="importMutation.isPending.value"
      @import="handleBatchImport"
    />

    <!-- Modal de Envío Masivo de Mensajes WhatsApp -->
    <ContactBulkMessageModal
      v-model="isBulkMessageOpen"
      :selected-contact-ids="selectedContactIds"
      :contacts="contacts"
      :initial-mode="bulkModalMode"
      @sent="onBulkMessagesSent"
    />

    <!-- Diálogo de Confirmación para Eliminación -->
    <AppConfirmDialog
      v-model="isConfirmDeleteOpen"
      title="Eliminar contacto"
      :message="`¿Estás seguro de que deseas eliminar permanentemente a ${contactToDeleteName}? Esta acción no se puede deshacer.`"
      confirm-label="Eliminar"
      confirm-color="negative"
      :loading="deleteMutation.isPending.value"
      @confirm="executeDelete"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useAppNotify } from '@/shared/composables/useAppNotify'
import AppConfirmDialog from '@/shared/components/AppConfirmDialog.vue'

import ContactFilterBar from '../components/ContactFilterBar.vue'
import ContactTable from '../components/ContactTable.vue'
import ContactGridCard from '../components/ContactGridCard.vue'
import ContactDetailDrawer from '../components/ContactDetailDrawer.vue'
import ContactFormDialog from '../components/ContactFormDialog.vue'
import ContactImportModal from '../components/ContactImportModal.vue'
import ContactBulkMessageModal from '../components/ContactBulkMessageModal.vue'

import {
  useContactMutations,
  useContacts,
  useContactTags,
} from '../composables/useContacts'
import { getContacts } from '../api/contacts.api'
import { exportContactsToExcel, exportContactsToPdf } from '../utils/contactExport'
import type {
  Contact,
  ContactPayload,
  ImportContactItem,
} from '../types/contact.types'

const notify = useAppNotify()

// Estado Reactivo de Filtros y Selección
const viewMode = ref<'table' | 'grid'>('table')
const isDrawerOpen = ref(false)
const isFormOpen = ref(false)
const isImportOpen = ref(false)
const isConfirmDeleteOpen = ref(false)
const isBulkMessageOpen = ref(false)
const isExporting = ref(false)
const bulkModalMode = ref<'immediate' | 'scheduled'>('immediate')
const selectedContactIds = ref<string[]>([])

const selectedContact = ref<Contact | null>(null)
const contactToDelete = ref<Contact | null>(null)

const filters = reactive({
  search: '',
  channel: null as string | null,
  category_ids: [] as string[],
  custom_status_ids: [] as string[],
  tags: [] as string[],
  page: 1,
  per_page: 15,
})

// Reiniciar a la primera página si cambian los filtros y vaciar selección
watch(
  () => [filters.search, filters.channel, filters.category_ids, filters.custom_status_ids, filters.tags],
  () => {
    filters.page = 1
    selectedContactIds.value = []
  },
  { deep: true },
)

// Queries y Mutaciones
const tagsQuery = useContactTags()
const contactsQuery = useContacts(computed(() => ({
  search: filters.search || undefined,
  channel: filters.channel || undefined,
  category_ids: filters.category_ids.length ? filters.category_ids : undefined,
  custom_status_ids: filters.custom_status_ids.length ? filters.custom_status_ids : undefined,
  tags: filters.tags.length ? filters.tags : undefined,
  page: filters.page,
  per_page: filters.per_page,
})))

const { createMutation, updateMutation, deleteMutation, importMutation } = useContactMutations()

// Datos calculados
const contacts = computed<Contact[]>(() => contactsQuery.data.value?.data ?? [])
const totalContacts = computed(() => contactsQuery.data.value?.meta?.total ?? contacts.value.length)
const totalPages = computed(() => contactsQuery.data.value?.meta?.last_page ?? 1)
const contactToDeleteName = computed(() => {
  if (!contactToDelete.value) return ''
  return contactToDelete.value.name || `${contactToDelete.value.first_name || ''} ${contactToDelete.value.last_name || ''}`.trim()
})

// Manejadores de Interacción
function openCreateDialog() {
  selectedContact.value = null
  isFormOpen.value = true
}

function openEditDialog(contact: Contact) {
  selectedContact.value = contact
  isFormOpen.value = true
}

function openDetailDrawer(contact: Contact) {
  selectedContact.value = contact
  isDrawerOpen.value = true
}

function confirmDelete(contact: Contact) {
  contactToDelete.value = contact
  isConfirmDeleteOpen.value = true
}

async function executeDelete() {
  if (!contactToDelete.value) return
  try {
    await deleteMutation.mutateAsync(contactToDelete.value.id)
    notify.success({ message: 'Contacto eliminado exitosamente.' })
    isConfirmDeleteOpen.value = false
    contactToDelete.value = null
  } catch {
    notify.error({ message: 'No se pudo eliminar el contacto.' })
  }
}

async function handleFormSubmit(payload: ContactPayload) {
  try {
    if (selectedContact.value?.id) {
      await updateMutation.mutateAsync({ id: selectedContact.value.id, payload })
      notify.success({ message: 'Contacto actualizado correctamente.' })
    } else {
      await createMutation.mutateAsync(payload)
      notify.success({ message: 'Contacto registrado exitosamente.' })
    }
    isFormOpen.value = false
    selectedContact.value = null
  } catch {
    notify.error({ message: 'Error al procesar la información del contacto.' })
  }
}

async function handleBatchImport(items: ImportContactItem[]) {
  try {
    const res = await importMutation.mutateAsync({ contacts: items })
    notify.success({
      message: res.message || `${res.data.created_count} contactos importados exitosamente.`,
    })
    isImportOpen.value = false
  } catch {
    notify.error({ message: 'Error durante la importación masiva.' })
  }
}

function openBulkSchedule() {
  bulkModalMode.value = 'scheduled'
  isBulkMessageOpen.value = true
}

function openBulkImmediate() {
  bulkModalMode.value = 'immediate'
  isBulkMessageOpen.value = true
}

function onBulkMessagesSent() {
  selectedContactIds.value = []
  contactsQuery.refetch()
}

function toggleSelection(id: string) {
  if (selectedContactIds.value.includes(id)) {
    selectedContactIds.value = selectedContactIds.value.filter((item) => item !== id)
  } else {
    selectedContactIds.value = [...selectedContactIds.value, id]
  }
}

async function resolveSelectedContacts(): Promise<Contact[]> {
  if (selectedContactIds.value.length === 0) return []

  // Si todos los contactos seleccionados están presentes en la lista actual visible
  const currentMap = new Map(contacts.value.map((c) => [c.id, c]))
  const allInCurrent = selectedContactIds.value.every((id) => currentMap.has(id))
  if (allInCurrent) {
    return selectedContactIds.value
      .map((id) => currentMap.get(id))
      .filter((c): c is Contact => c !== undefined)
  }

  // Si hay seleccionados de otras páginas, consultar al backend
  const res = await getContacts({
    ids: selectedContactIds.value,
    per_page: Math.min(Math.max(selectedContactIds.value.length, 15), 1000),
  })
  return res.data || []
}

async function exportSelected(format: 'excel' | 'pdf') {
  if (selectedContactIds.value.length === 0) {
    notify.warning({ message: 'No hay contactos seleccionados para exportar.' })
    return
  }

  isExporting.value = true
  try {
    const list = await resolveSelectedContacts()
    if (list.length === 0) {
      notify.warning({ message: 'No se encontraron datos para los contactos seleccionados.' })
      return
    }

    if (format === 'excel') {
      const ok = exportContactsToExcel(list)
      if (ok) {
        notify.success({ message: `Se exportaron ${list.length} contactos seleccionados a Excel (.xlsx).` })
      }
    } else {
      const ok = exportContactsToPdf(list, {
        title: 'UNITEPC — SELECCIÓN DE CONTACTOS & AUDIENCIA',
        subtitle: `Reporte de ${list.length} contactos seleccionados para gestión institucional`,
      })
      if (ok) {
        notify.success({ message: `Se descargó el Reporte PDF de ${list.length} contactos seleccionados.` })
      }
    }
  } catch {
    notify.error({ message: 'Ocurrió un error al generar la exportación de contactos.' })
  } finally {
    isExporting.value = false
  }
}

function exportCurrentView(format: 'excel' | 'pdf') {
  if (contacts.value.length === 0) {
    notify.warning({ message: 'No hay contactos en la página actual para exportar.' })
    return
  }

  isExporting.value = true
  try {
    if (format === 'excel') {
      const ok = exportContactsToExcel(contacts.value)
      if (ok) {
        notify.success({ message: `Se exportaron los ${contacts.value.length} contactos de la vista actual a Excel.` })
      }
    } else {
      const ok = exportContactsToPdf(contacts.value, {
        title: 'UNITEPC — DIRECTORIO DE CONTACTOS (VISTA ACTUAL)',
        subtitle: `Listado de ${contacts.value.length} contactos en pantalla`,
      })
      if (ok) {
        notify.success({ message: `Reporte PDF generado con ${contacts.value.length} contactos.` })
      }
    }
  } catch {
    notify.error({ message: 'Error al exportar los contactos.' })
  } finally {
    isExporting.value = false
  }
}

async function exportAllFiltered(format: 'excel' | 'pdf') {
  isExporting.value = true
  try {
    const res = await getContacts({
      search: filters.search || undefined,
      channel: filters.channel || undefined,
      category_ids: filters.category_ids.length ? filters.category_ids : undefined,
      custom_status_ids: filters.custom_status_ids.length ? filters.custom_status_ids : undefined,
      tags: filters.tags.length ? filters.tags : undefined,
      page: 1,
      per_page: Math.min(Math.max(totalContacts.value, 15), 5000),
    })

    const allContacts = res.data || []
    if (allContacts.length === 0) {
      notify.warning({ message: 'No se encontraron contactos para los filtros aplicados.' })
      return
    }

    if (format === 'excel') {
      const ok = exportContactsToExcel(allContacts)
      if (ok) {
        notify.success({ message: `Se exportó el catálogo completo de ${allContacts.length} contactos a Excel.` })
      }
    } else {
      const ok = exportContactsToPdf(allContacts, {
        title: 'UNITEPC — REPORTE GENERAL DE CONTACTOS',
        subtitle: `Catálogo de prospectos y audiencia (${allContacts.length} contactos)`,
      })
      if (ok) {
        notify.success({ message: `Reporte PDF generado exitosamente con ${allContacts.length} contactos.` })
      }
    }
  } catch {
    notify.error({ message: 'Error al exportar el catálogo completo de contactos.' })
  } finally {
    isExporting.value = false
  }
}
</script>

<style scoped lang="scss">
.contact-list-page {
  padding: 28px 36px;
  background-color: var(--crm-bg-app, #080c14);
  min-height: calc(100vh - 56px);
}

.bulk-action-banner {
  background: linear-gradient(90deg, rgba(16, 185, 129, 0.16) 0%, rgba(6, 182, 212, 0.12) 100%);
  border: 1px solid rgba(16, 185, 129, 0.35);
  border-radius: 12px;
  backdrop-filter: blur(8px);
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.25);
}

.xf-btn-primary {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%) !important;
  font-weight: 600;
  border-radius: 8px;
  box-shadow: 0 4px 14px -2px rgba(16, 185, 129, 0.35);
}

.xf-btn-secondary {
  border-color: rgba(6, 182, 212, 0.3);
  border-radius: 8px;
}
</style>
