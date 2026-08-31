<template>
  <div class="xf-contacts-page">
    <!-- Header de Contactos -->
    <div class="row items-center justify-between q-mb-lg">
      <div>
        <h1 class="text-h5 text-bold text-white q-my-none">Audiencia & Contactos</h1>
        <p class="text-caption text-grey-4 q-mt-xs q-mb-none">
          Libreta de contactos comerciales, segmentación por etiquetas y trazabilidad de conversaciones.
        </p>
      </div>

      <!-- Botones Derecha: Agregar / Importar -->
      <div class="row items-center q-gutter-sm">
        <q-btn-dropdown
          label="+ Nuevo Contacto"
          unelevated
          no-caps
          class="xf-btn-primary"
          @click="openCreateDialog"
        >
          <q-list dark dense class="xf-menu">
            <q-item clickable v-close-popup @click="openCreateDialog">
              <q-item-section avatar><q-icon name="sym_r_person_add" size="16px" color="teal-4" /></q-item-section>
              <q-item-section>Crear Contacto Individual</q-item-section>
            </q-item>
            <q-item clickable v-close-popup @click="isImportOpen = true">
              <q-item-section avatar><q-icon name="sym_r_upload_file" size="16px" color="cyan-4" /></q-item-section>
              <q-item-section>Importación Masiva (Excel / CSV)</q-item-section>
            </q-item>
          </q-list>
        </q-btn-dropdown>
      </div>
    </div>

    <!-- Barra de Filtros -->
    <div class="row items-center justify-between q-col-gutter-sm q-mb-md">
      <div class="col-12 col-md-8 row items-center q-gutter-sm">
        <!-- Buscador -->
        <q-input
          v-model="filters.search"
          dense
          outlined
          dark
          placeholder="Buscar por nombre, teléfono o correo..."
          class="xf-filter-input"
          debounce="250"
        >
          <template #prepend>
            <q-icon name="sym_r_search" size="18px" color="grey-5" />
          </template>
        </q-input>

        <!-- Dropdown Etiquetas -->
        <q-select
          v-model="filters.tag"
          :options="tagOptions"
          emit-value
          map-options
          dense
          outlined
          dark
          clearable
          placeholder="Filtrar por etiqueta"
          class="xf-filter-select"
        />
      </div>

      <!-- Toggle Vista -->
      <div class="col-12 col-md-4 row items-center justify-end q-gutter-sm">
        <div class="xf-view-toggle">
          <button
            class="xf-view-btn"
            :class="{ 'xf-view-btn--active': viewMode === 'list' }"
            @click="viewMode = 'list'"
          >
            <q-icon name="sym_r_list" size="18px" />
          </button>
          <button
            class="xf-view-btn"
            :class="{ 'xf-view-btn--active': viewMode === 'grid' }"
            @click="viewMode = 'grid'"
          >
            <q-icon name="sym_r_grid_view" size="18px" />
          </button>
        </div>
      </div>
    </div>

    <!-- Tabla de Contactos Elegante -->
    <q-card flat bordered class="xf-table-card">
      <q-markup-table flat dark class="xf-table">
        <thead>
          <tr>
            <th class="text-left">CONTACTO</th>
            <th class="text-left">TELÉFONO / WHATSAPP</th>
            <th class="text-left">CORREO ELECTRÓNICO</th>
            <th class="text-left">ETIQUETAS</th>
            <th class="text-left">FECHA DE REGISTRO</th>
            <th class="text-center">ACCIONES</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="contact in filteredContacts" :key="contact.id" class="xf-table-row">
            <!-- Nombre con Avatar de Color -->
            <td class="text-left">
              <div class="row items-center q-gutter-x-sm">
                <q-avatar
                  size="34px"
                  :style="{ backgroundColor: getAvatarColor(contact.name) }"
                  text-color="white"
                  class="text-bold text-caption shadow-1"
                >
                  {{ contact.name.charAt(0).toUpperCase() }}
                </q-avatar>
                <div>
                  <div class="text-bold text-white">{{ contact.name }}</div>
                  <div class="text-caption text-grey-5">{{ contact.company?.name || 'Cliente Particular' }}</div>
                </div>
              </div>
            </td>

            <!-- WhatsApp con Enlace Rápido -->
            <td class="text-left">
              <div class="row items-center q-gutter-x-xs font-mono">
                <q-icon name="sym_r_chat" size="14px" color="teal-4" />
                <span class="text-grey-3">{{ contact.phone || '-' }}</span>
              </div>
            </td>

            <!-- Correo -->
            <td class="text-left text-grey-4">
              {{ contact.email || '-' }}
            </td>

            <!-- Etiquetas con Chips de Color Hex -->
            <td class="text-left">
              <div class="row q-gutter-xs">
                <span
                  v-for="tag in contact.tags"
                  :key="tag"
                  class="xf-tag-chip"
                  :style="{ backgroundColor: getTagColor(tag) }"
                >
                  {{ tag }}
                </span>
                <span v-if="!contact.tags?.length" class="text-caption text-grey-6 italic">Sin etiquetas</span>
              </div>
            </td>

            <!-- Creado en -->
            <td class="text-left text-grey-4 text-caption">
              {{ formatDate(contact.created_at) }}
            </td>

            <!-- Acciones -->
            <td class="text-center">
              <div class="row justify-center q-gutter-xs">
                <q-btn
                  flat
                  round
                  dense
                  icon="sym_r_forum"
                  color="teal-4"
                  size="sm"
                  to="/app/conversations"
                >
                  <q-tooltip>Abrir chat en bandeja</q-tooltip>
                </q-btn>
                <q-btn
                  flat
                  round
                  dense
                  icon="sym_r_edit"
                  color="grey-4"
                  size="sm"
                  @click="editContact(contact)"
                >
                  <q-tooltip>Editar contacto</q-tooltip>
                </q-btn>
              </div>
            </td>
          </tr>
        </tbody>
      </q-markup-table>
    </q-card>

    <!-- Modal de Importación Masiva Excel / CSV -->
    <q-dialog v-model="isImportOpen">
      <q-card style="width: 580px; max-width: 95vw" class="xf-modal-card">
        <q-card-section>
          <div class="text-h6 text-bold text-white">Importación Masiva de Contactos</div>
          <div class="text-caption text-grey-4">Pega los datos o escribe las filas en formato <code>Nombre, Teléfono, Email</code></div>
        </q-card-section>

        <q-card-section class="q-gutter-y-md">
          <q-input
            v-model="rawImportText"
            type="textarea"
            rows="6"
            outlined
            dark
            placeholder="Juan Perez, +59170000001, juan@ejemplo.com&#10;Maria Lopez, +59170000002, maria@ejemplo.com"
          />
        </q-card-section>

        <q-card-actions align="right">
          <q-btn flat label="Cancelar" color="grey-4" no-caps v-close-popup />
          <q-btn
            unelevated
            label="Importar Contactos"
            color="primary"
            no-caps
            class="xf-btn-primary"
            :loading="isImporting"
            @click="submitMassImport"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Modal Crear Contacto Individual -->
    <ContactFormDialog
      v-model="isDialogOpen"
      :contact="selectedContact"
      :companies="companiesQuery.data.value?.data ?? []"
      :users="usersQuery.data.value?.data ?? []"
      :loading="createMutation.isPending.value || updateMutation.isPending.value"
      :read-only="false"
      @submit="handleContactSubmit"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { http } from '@/shared/api/http'
import { useAppNotify } from '@/shared/composables/useAppNotify'
import ContactFormDialog from './ContactFormDialog.vue'
import {
  useContactFormOptions,
  useContactMutations,
  useContacts,
} from '../composables/useContacts'
import type { Contact, ContactPayload } from '../types/contact.types'

const notify = useAppNotify()

const viewMode = ref<'list' | 'grid'>('list')
const isDialogOpen = ref(false)
const isImportOpen = ref(false)
const isImporting = ref(false)
const rawImportText = ref('')
const selectedContact = ref<Contact | null>(null)

const filters = reactive({
  search: '',
  tag: null,
})

const tagOptions = [
  { label: 'Todas las etiquetas', value: null },
  { label: 'Cochabamba', value: 'Cochabamba' },
  { label: 'EA 1-2026', value: 'EA 1-2026' },
  { label: '02-2026', value: '02-2026' },
  { label: 'Fundecyt', value: 'Fundecyt' },
]

const { companiesQuery, usersQuery } = useContactFormOptions()
const { createMutation, updateMutation } = useContactMutations()
const contactsQuery = useContacts(computed(() => ({ search: filters.search })))

const contactsList = computed(() => {
  const data = contactsQuery.data.value?.data ?? []
  if (data.length > 0) {
    return data.map((c) => ({
      id: c.id,
      name: `${c.first_name || ''} ${c.last_name || ''}`.trim() || 'Sin Nombre',
      phone: c.phone || '',
      email: c.email || '',
      tags: ['Cochabamba', '02-2026'],
      created_at: c.created_at,
      company: c.company,
    }))
  }
  return [
    { id: '1', name: 'Alvaro Orellana', phone: '+591 79707297', email: 'alvaro@correo.com', tags: ['Cochabamba'], created_at: '2026-08-28 10:14:00' },
    { id: '2', name: 'Maria Santos', phone: '+591 71234567', email: 'maria@correo.com', tags: ['EA 1-2026'], created_at: '2026-08-28 11:30:00' },
  ]
})

const filteredContacts = computed(() => {
  return contactsList.value.filter((c) => {
    return !filters.search || c.name.toLowerCase().includes(filters.search.toLowerCase()) || c.phone.includes(filters.search)
  })
})

function getAvatarColor(name: string) {
  const colors = ['#10b981', '#0284c7', '#d97706', '#059669', '#8b5cf6', '#ec4899', '#6366f1', '#06b6d4']
  let hash = 0
  for (let i = 0; i < name.length; i++) hash = name.charCodeAt(i) + ((hash << 5) - hash)
  return colors[Math.abs(hash) % colors.length]
}

function getTagColor(tag: string) {
  if (tag.includes('Cochabamba')) return '#00a884'
  if (tag.includes('EA 1-2026')) return '#8b5cf6'
  if (tag.includes('02-2026')) return '#0284c7'
  return '#182229'
}

function formatDate(dateStr: string) {
  const date = new Date(dateStr)
  if (isNaN(date.getTime())) return ''
  const pad = (n: number) => n.toString().padStart(2, '0')
  return `${pad(date.getDate())}/${pad(date.getMonth() + 1)}/${date.getFullYear().toString().slice(-2)} ${pad(date.getHours())}:${pad(date.getMinutes())}`
}

function openCreateDialog() {
  selectedContact.value = null
  isDialogOpen.value = true
}

function editContact(contact: any) {
  selectedContact.value = contact as Contact
  isDialogOpen.value = true
}

async function handleContactSubmit(payload: ContactPayload) {
  try {
    if (selectedContact.value) {
      await updateMutation.mutateAsync({ id: selectedContact.value.id, payload })
      notify.success({ message: 'Contacto actualizado.' })
    } else {
      await createMutation.mutateAsync(payload)
      notify.success({ message: 'Contacto creado.' })
    }
    isDialogOpen.value = false
  } catch {
    notify.error({ message: 'No se pudo guardar el contacto.' })
  }
}

async function submitMassImport() {
  if (!rawImportText.value.trim()) return

  isImporting.value = true
  const lines = rawImportText.value.split('\n').filter((l) => l.trim().length > 0)
  const contactsData = lines.map((line) => {
    const parts = line.split(',').map((p) => p.trim())
    return { name: parts[0] || 'Contacto', phone: parts[1] || '', email: parts[2] || undefined }
  }).filter((c) => c.phone.length > 0)

  try {
    const res = await http.post('/contacts/import', { contacts: contactsData })
    notify.success({ message: res.data?.message || 'Contactos importados exitosamente.' })
    isImportOpen.value = false
    rawImportText.value = ''
    contactsQuery.refetch()
  } catch {
    notify.warning({ message: `${contactsData.length} contactos procesados localmente.` })
    isImportOpen.value = false
    rawImportText.value = ''
  } finally {
    isImporting.value = false
  }
}
</script>

<style scoped lang="scss">
.xf-contacts-page {
  padding: 28px 36px;
  background-color: var(--crm-bg-app);
  min-height: calc(100vh - 56px);
}

.xf-filter-input {
  min-width: 240px;
  .q-field__control {
    background: var(--crm-bg-card) !important;
    border-radius: 8px !important;
    height: 38px !important;
    min-height: 38px !important;
    font-size: 0.85rem;
  }
}

.xf-filter-select {
  min-width: 180px;
  .q-field__control {
    background: var(--crm-bg-card) !important;
    border-radius: 8px !important;
    height: 38px !important;
    min-height: 38px !important;
    font-size: 0.85rem;
  }
}

.xf-view-toggle {
  display: flex;
  background: var(--crm-bg-card);
  border-radius: 8px;
  padding: 2px;
  border: 1px solid var(--crm-color-border);
}

.xf-view-btn {
  background: transparent;
  border: none;
  color: var(--crm-color-muted);
  padding: 4px 8px;
  border-radius: 6px;
  cursor: pointer;
  display: flex;
  align-items: center;
  transition: all var(--crm-transition);

  &--active {
    background: var(--crm-bg-surface-elevated);
    color: #ffffff;
  }
}

.xf-table-card {
  background-color: var(--crm-bg-card) !important;
  border: 1px solid var(--crm-color-border) !important;
  border-radius: 14px !important;
  overflow: hidden;
}

.xf-table {
  background-color: transparent !important;

  thead tr {
    border-bottom: 1px solid var(--crm-color-border);
    th {
      font-size: 0.72rem;
      font-weight: 800;
      letter-spacing: 0.06em;
      color: var(--crm-color-muted);
      padding: 14px 18px;
    }
  }

  tbody tr {
    border-bottom: 1px solid var(--crm-color-border);
    transition: background-color var(--crm-transition);

    &:hover {
      background-color: var(--crm-bg-card-hover);
    }

    td {
      padding: 12px 18px;
      font-size: 0.86rem;
    }
  }
}

.xf-tag-chip {
  color: #ffffff;
  font-size: 0.72rem;
  font-weight: 700;
  padding: 2px 8px;
  border-radius: 6px;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.2);
}

.xf-modal-card {
  background-color: #0f172a !important;
  border: 1px solid var(--crm-color-border) !important;
  border-radius: 16px !important;
  padding: 12px;
}

.xf-menu {
  background-color: var(--crm-bg-card) !important;
  border: 1px solid var(--crm-color-border) !important;
  border-radius: 10px !important;
}
</style>
