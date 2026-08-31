<template>
  <AppPage
    eyebrow="Relacionamiento"
    title="Empresas"
    description="Gestiona cuentas, clasifica industrias y prepara asociaciones comerciales."
  >
    <template
      v-if="canManageCompanies"
      #actions
    >
      <q-btn
        color="primary"
        label="Nueva empresa"
        unelevated
        @click="openCreateDialog"
      />
    </template>

    <q-card
      flat
      bordered
      class="companies-filters"
    >
      <q-card-section class="companies-filters__grid">
        <q-input
          v-model="filters.search"
          label="Buscar"
          outlined
        />
        <q-select
          v-model="filters.status"
          :options="statusOptions"
          emit-value
          map-options
          label="Estado"
          outlined
          clearable
        />
      </q-card-section>
    </q-card>

    <AppLoadingState
      v-if="companiesQuery.isLoading.value && !hasRows"
      title="Cargando empresas"
      description="Estamos preparando tus cuentas y asociaciones comerciales."
    />

    <AppEmptyState
      v-else-if="!hasRows"
      title="No encontramos empresas para este filtro"
      description="Prueba otra busqueda o crea una nueva empresa para seguir relacionando contactos y deals."
      icon="sym_r_domain"
    >
      <template
        v-if="canManageCompanies"
        #actions
      >
        <q-btn
          color="primary"
          label="Crear empresa"
          unelevated
          @click="openCreateDialog"
        />
      </template>
    </AppEmptyState>

    <AppTable
      v-else
      :rows="companiesQuery.data.value?.data ?? []"
      :columns="columns"
      :loading="companiesQuery.isLoading.value"
      :pagination="pagination"
      binary-state-sort
      @request="onRequest"
    >
      <template #body-cell-name="props">
        <q-td :props="props">
          <router-link
            :to="`/app/companies/${props.row.id}`"
            class="crm-detail-link"
          >
            {{ props.row.name }}
          </router-link>
        </q-td>
      </template>

      <template #body-cell-status="props">
        <q-td :props="props">
          <AppStatusBadge :status="props.row.status" />
        </q-td>
      </template>

      <template #body-cell-contacts="props">
        <q-td :props="props">
          {{ props.row.contacts.length }}
        </q-td>
      </template>

      <template #body-cell-actions="props">
        <q-td
          v-if="canManageCompanies"
          :props="props"
        >
          <div class="companies-actions">
            <q-btn
              flat
              size="sm"
              label="Editar"
              @click="openEditDialog(props.row)"
            />
            <q-btn
              flat
              color="negative"
              size="sm"
              label="Eliminar"
              @click="removeCompany(props.row.id)"
            />
          </div>
        </q-td>
      </template>
    </AppTable>

    <CompanyFormDialog
      v-model="isDialogOpen"
      :company="selectedCompany"
      :loading="isSubmitting"
      :read-only="!canManageCompanies"
      :errors="formErrors"
      @submit="saveCompany"
    />

    <AppConfirmDialog
      v-model="isDeleteDialogOpen"
      title="Eliminar empresa"
      message="Esta accion quitara la empresa del workspace actual. Revisa primero si aun esta relacionada con contactos o procesos comerciales."
      confirm-label="Eliminar empresa"
      :loading="deleteMutation.isPending.value"
      @confirm="confirmRemoveCompany"
    />
  </AppPage>
</template>

<script setup lang="ts">
import type { AxiosError } from 'axios'
import type { QTableProps } from 'quasar'
import { computed, reactive, ref, watch } from 'vue'

import { useAuthStore } from '@/modules/auth/stores/auth.store'
import { useAppNotify } from '@/shared/composables/useAppNotify'
import AppConfirmDialog from '@/shared/components/AppConfirmDialog.vue'
import AppEmptyState from '@/shared/components/AppEmptyState.vue'
import AppLoadingState from '@/shared/components/AppLoadingState.vue'
import AppPage from '@/shared/components/AppPage.vue'
import AppStatusBadge from '@/shared/components/AppStatusBadge.vue'
import AppTable from '@/shared/components/AppTable.vue'

import { useCompanies, useCompanyMutations } from '../composables/useCompanies'
import CompanyFormDialog from './CompanyFormDialog.vue'
import type { Company, CompanyFilters, CompanyPayload } from '../types/company.types'

const filters = reactive<CompanyFilters>({
  search: '',
  status: '',
  page: 1,
  per_page: 10,
})

const companiesQuery = useCompanies(computed(() => ({ ...filters })))
const { createMutation, updateMutation, deleteMutation } = useCompanyMutations()
const authStore = useAuthStore()
const notify = useAppNotify()

const isDialogOpen = ref(false)
const selectedCompany = ref<Company | null>(null)
const pendingDeleteCompanyId = ref<string | null>(null)
const formErrors = ref<Record<string, string[]>>({})
const canManageCompanies = computed(() => authStore.hasPermission('companies.manage'))
const hasRows = computed(() => (companiesQuery.data.value?.data?.length ?? 0) > 0)
const isDeleteDialogOpen = computed({
  get: () => Boolean(pendingDeleteCompanyId.value),
  set: (value: boolean) => {
    if (!value) {
      pendingDeleteCompanyId.value = null
    }
  },
})

const statusOptions = [
  { label: 'Todos', value: '' },
  { label: 'Activo', value: 'active' },
  { label: 'Lead', value: 'lead' },
  { label: 'Inactivo', value: 'inactive' },
]

const columns = computed<QTableProps['columns']>(() => {
  const baseColumns: NonNullable<QTableProps['columns']> = [
    { name: 'name', label: 'Empresa', field: 'name', align: 'left' },
    { name: 'industry', label: 'Industria', field: 'industry', align: 'left' },
    { name: 'email', label: 'Correo', field: 'email', align: 'left' },
    { name: 'status', label: 'Estado', field: 'status', align: 'left' },
    { name: 'contacts', label: 'Contactos', field: 'contacts', align: 'center' },
  ]

  if (canManageCompanies.value) {
    baseColumns.push({
      name: 'actions',
      label: 'Acciones',
      field: 'actions',
      align: 'right',
    })
  }

  return baseColumns
})

const pagination = computed(() => ({
  page: companiesQuery.data.value?.meta.current_page ?? filters.page ?? 1,
  rowsPerPage: companiesQuery.data.value?.meta.per_page ?? filters.per_page ?? 10,
  rowsNumber: companiesQuery.data.value?.meta.total ?? 0,
}))

const isSubmitting = computed(
  () => createMutation.isPending.value || updateMutation.isPending.value,
)

watch(
  () => [filters.search, filters.status],
  () => {
    filters.page = 1
  },
)

function onRequest(payload: { pagination: { page: number; rowsPerPage: number } }) {
  filters.page = payload.pagination.page
  filters.per_page = payload.pagination.rowsPerPage
}

function openCreateDialog() {
  selectedCompany.value = null
  formErrors.value = {}
  isDialogOpen.value = true
}

function openEditDialog(company: Company) {
  selectedCompany.value = company
  formErrors.value = {}
  isDialogOpen.value = true
}

async function saveCompany(payload: CompanyPayload) {
  try {
    formErrors.value = {}

    if (selectedCompany.value) {
      await updateMutation.mutateAsync({
        id: selectedCompany.value.id,
        payload,
      })
      notify.success('Empresa actualizada.', 'La cuenta ya refleja los cambios.')
    } else {
      await createMutation.mutateAsync(payload)
      notify.success('Empresa creada.', 'Ya puedes asociarle contactos y oportunidades.')
    }

    isDialogOpen.value = false
    selectedCompany.value = null
  } catch (error) {
    formErrors.value = getValidationErrors(error)

    if (Object.keys(formErrors.value).length > 0) {
      notify.warning(
        'Revisa los campos marcados.',
        'Hay datos que necesitan correccion antes de guardar la empresa.',
      )
      return
    }

    notify.fromError(error, 'No se pudo guardar la empresa.')
  }
}

async function removeCompany(companyId: string) {
  pendingDeleteCompanyId.value = companyId
}

async function confirmRemoveCompany() {
  if (!pendingDeleteCompanyId.value) {
    return
  }

  try {
    await deleteMutation.mutateAsync(pendingDeleteCompanyId.value)
    notify.info('Empresa eliminada.', 'La cuenta se removio del listado actual.')
    pendingDeleteCompanyId.value = null
  } catch (error) {
    notify.fromError(error, 'No se pudo eliminar la empresa.')
  }
}

function getValidationErrors(error: unknown): Record<string, string[]> {
  const responseErrors = (error as AxiosError<{ errors?: Record<string, string[]> }>)?.response?.data?.errors

  if (responseErrors && typeof responseErrors === 'object') {
    return responseErrors
  }

  return {}
}
</script>

<style scoped lang="scss">
.companies-filters {
  border-radius: 24px;
  background:
    radial-gradient(circle at top right, rgba(73, 194, 255, 0.08), transparent 26%),
    linear-gradient(180deg, rgba(13, 26, 42, 0.94) 0%, rgba(9, 20, 33, 0.92) 100%);
}

.companies-filters__grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 16px;
}

.companies-actions {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

@media (max-width: 960px) {
  .companies-filters__grid {
    grid-template-columns: 1fr;
  }
}
</style>
