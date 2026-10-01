<template>
  <div class="contact-list-page">
    <!-- Header de Audiencia & Contactos -->
    <div class="row items-center justify-between q-mb-lg">
      <div>
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
      <div class="row items-center q-gutter-sm">
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
      v-model:tag="filters.tag"
      v-model:status="filters.status"
      v-model:view-mode="viewMode"
      :tags="tagsQuery.data.value ?? []"
    />

    <!-- Modo Vista: Tabla -->
    <ContactTable
      v-if="viewMode === 'table'"
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
import { computed, reactive, ref } from 'vue'
import { useAppNotify } from '@/shared/composables/useAppNotify'
import AppConfirmDialog from '@/shared/components/AppConfirmDialog.vue'

import ContactFilterBar from '../components/ContactFilterBar.vue'
import ContactTable from '../components/ContactTable.vue'
import ContactGridCard from '../components/ContactGridCard.vue'
import ContactDetailDrawer from '../components/ContactDetailDrawer.vue'
import ContactFormDialog from '../components/ContactFormDialog.vue'
import ContactImportModal from '../components/ContactImportModal.vue'

import {
  useContactMutations,
  useContacts,
  useContactTags,
} from '../composables/useContacts'
import type {
  Contact,
  ContactPayload,
  ImportContactItem,
} from '../types/contact.types'

const notify = useAppNotify()

// Estado Reactivo de Filtros
const viewMode = ref<'table' | 'grid'>('table')
const isDrawerOpen = ref(false)
const isFormOpen = ref(false)
const isImportOpen = ref(false)
const isConfirmDeleteOpen = ref(false)

const selectedContact = ref<Contact | null>(null)
const contactToDelete = ref<Contact | null>(null)

const filters = reactive({
  search: '',
  tag: null as string | null,
  status: null as string | null,
  page: 1,
  per_page: 15,
})

// Queries y Mutaciones
const tagsQuery = useContactTags()
const contactsQuery = useContacts(computed(() => ({
  search: filters.search || undefined,
  tag: filters.tag || undefined,
  status: filters.status || undefined,
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
</script>

<style scoped lang="scss">
.contact-list-page {
  padding: 28px 36px;
  background-color: var(--crm-bg-app, #080c14);
  min-height: calc(100vh - 56px);
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
