<template>
  <AppPage
    eyebrow="Automations"
    title="Automatizaciones"
    description="Crea reglas simples para asignar conversaciones y mover estados cuando llega actividad nueva."
  >
    <template
      v-if="canManageAutomations"
      #actions
    >
      <q-btn
        color="primary"
        label="Nueva regla"
        unelevated
        @click="openCreateDialog"
      />
    </template>

    <q-card
      flat
      bordered
      class="automation-filters"
    >
      <q-card-section class="automation-filters__grid">
        <q-select
          v-model="filters.trigger_type"
          :options="triggerFilterOptions"
          emit-value
          map-options
          label="Trigger"
          outlined
          clearable
        />
        <q-select
          v-model="filters.is_active"
          :options="activeFilterOptions"
          emit-value
          map-options
          label="Estado"
          outlined
          clearable
        />
      </q-card-section>
    </q-card>

    <AppLoadingState
      v-if="rulesQuery.isLoading.value && !hasRows"
      title="Cargando automatizaciones"
      description="Estamos reuniendo reglas, triggers y estados activos del workspace."
    />

    <AppEmptyState
      v-else-if="!hasRows"
      title="Todavia no hay reglas para este filtro"
      description="Crea una automatizacion para asignar conversaciones, cambiar estados o activar flujos operativos."
      icon="sym_r_auto_awesome"
    >
      <template
        v-if="canManageAutomations"
        #actions
      >
        <q-btn
          color="primary"
          label="Crear regla"
          unelevated
          @click="openCreateDialog"
        />
      </template>
    </AppEmptyState>

    <AppTable
      v-else
      :rows="rulesQuery.data.value?.data ?? []"
      :columns="columns"
      :loading="rulesQuery.isLoading.value"
    >
      <template #body-cell-trigger_type="props">
        <q-td :props="props">
          {{ triggerLabel(props.row.trigger_type) }}
        </q-td>
      </template>

      <template #body-cell-is_active="props">
        <q-td :props="props">
          <AppStatusBadge
            :status="props.row.is_active ? 'active' : 'inactive'"
            :label="props.row.is_active ? 'Activa' : 'Inactiva'"
          />
        </q-td>
      </template>

      <template #body-cell-actions="props">
        <q-td
          v-if="canManageAutomations"
          :props="props"
        >
          <div class="automation-actions">
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
              @click="removeRule(props.row.id)"
            />
          </div>
        </q-td>
      </template>
    </AppTable>

    <AutomationRuleFormDialog
      v-model="isDialogOpen"
      :rule="selectedRule"
      :users="usersQuery.data.value ?? []"
      :loading="isSubmitting"
      :read-only="!canManageAutomations"
      @submit="saveRule"
    />

    <AppConfirmDialog
      v-model="isDeleteDialogOpen"
      title="Eliminar automatizacion"
      message="Esta accion quitara la regla del workspace actual. Asegurate de que no dependa ningun flujo operativo antes de borrarla."
      confirm-label="Eliminar regla"
      :loading="deleteMutation.isPending.value"
      @confirm="confirmRemoveRule"
    />
  </AppPage>
</template>

<script setup lang="ts">
import type { QTableProps } from 'quasar'
import { computed, reactive, ref } from 'vue'

import { useAuthStore } from '@/modules/auth/stores/auth.store'
import { useConversationFormOptions } from '@/modules/conversations/composables/useConversations'
import { useAppNotify } from '@/shared/composables/useAppNotify'
import AppConfirmDialog from '@/shared/components/AppConfirmDialog.vue'
import AppEmptyState from '@/shared/components/AppEmptyState.vue'
import AppLoadingState from '@/shared/components/AppLoadingState.vue'
import AppPage from '@/shared/components/AppPage.vue'
import AppStatusBadge from '@/shared/components/AppStatusBadge.vue'
import AppTable from '@/shared/components/AppTable.vue'

import { useAutomationMutations, useAutomationRules } from '../composables/useAutomations'
import AutomationRuleFormDialog from './AutomationRuleFormDialog.vue'
import type { AutomationFilters, AutomationRule, AutomationRulePayload } from '../types/automation.types'

const filters = reactive<AutomationFilters>({
  trigger_type: '',
  is_active: '',
  page: 1,
  per_page: 20,
})

const rulesQuery = useAutomationRules(computed(() => ({ ...filters })))
const { usersQuery } = useConversationFormOptions()
const { createMutation, updateMutation, deleteMutation } = useAutomationMutations()
const authStore = useAuthStore()
const notify = useAppNotify()

const isDialogOpen = ref(false)
const selectedRule = ref<AutomationRule | null>(null)
const pendingDeleteRuleId = ref<string | null>(null)
const canManageAutomations = computed(() => authStore.hasPermission('automations.manage'))
const hasRows = computed(() => (rulesQuery.data.value?.data?.length ?? 0) > 0)

const columns = computed<QTableProps['columns']>(() => {
  const baseColumns: NonNullable<QTableProps['columns']> = [
    { name: 'name', label: 'Regla', field: 'name', align: 'left' },
    { name: 'trigger_type', label: 'Trigger', field: 'trigger_type', align: 'left' },
    { name: 'is_active', label: 'Estado', field: 'is_active', align: 'left' },
  ]

  if (canManageAutomations.value) {
    baseColumns.push({
      name: 'actions',
      label: 'Acciones',
      field: 'actions',
      align: 'right',
    })
  }

  return baseColumns
})

const isSubmitting = computed(
  () => createMutation.isPending.value || updateMutation.isPending.value,
)
const isDeleteDialogOpen = computed({
  get: () => Boolean(pendingDeleteRuleId.value),
  set: (value: boolean) => {
    if (!value) {
      pendingDeleteRuleId.value = null
    }
  },
})

const triggerFilterOptions = [
  { label: 'Todos', value: '' },
  { label: 'Conversacion creada', value: 'conversation.created' },
  { label: 'Mensaje inbound recibido', value: 'message.inbound.received' },
]

const activeFilterOptions = [
  { label: 'Todos', value: '' },
  { label: 'Activas', value: true },
  { label: 'Inactivas', value: false },
]

function openCreateDialog() {
  selectedRule.value = null
  isDialogOpen.value = true
}

function openEditDialog(rule: AutomationRule) {
  selectedRule.value = rule
  isDialogOpen.value = true
}

async function saveRule(payload: AutomationRulePayload) {
  try {
    if (selectedRule.value) {
      await updateMutation.mutateAsync({
        id: selectedRule.value.id,
        payload,
      })
      notify.success('Regla actualizada.', 'La automatizacion ya refleja los cambios.')
    } else {
      await createMutation.mutateAsync(payload)
      notify.success('Regla creada.', 'La automatizacion ya esta disponible para el equipo.')
    }

    isDialogOpen.value = false
    selectedRule.value = null
  } catch (error) {
    notify.fromError(error, 'No se pudo guardar la automatizacion.')
  }
}

async function removeRule(ruleId: string) {
  pendingDeleteRuleId.value = ruleId
}

async function confirmRemoveRule() {
  if (!pendingDeleteRuleId.value) {
    return
  }

  try {
    await deleteMutation.mutateAsync(pendingDeleteRuleId.value)
    notify.info('Regla eliminada.', 'La automatizacion ya no forma parte del workspace.')
    pendingDeleteRuleId.value = null
  } catch (error) {
    notify.fromError(error, 'No se pudo eliminar la automatizacion.')
  }
}

function triggerLabel(triggerType: string) {
  switch (triggerType) {
    case 'conversation.created':
      return 'Conversacion creada'
    case 'message.inbound.received':
      return 'Mensaje inbound recibido'
    default:
      return triggerType
  }
}
</script>

<style scoped lang="scss">
.automation-filters {
  border-radius: 24px;
  background:
    radial-gradient(circle at top right, rgba(73, 194, 255, 0.08), transparent 26%),
    linear-gradient(180deg, rgba(13, 26, 42, 0.94) 0%, rgba(9, 20, 33, 0.92) 100%);
}

.automation-filters__grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 16px;
}

.automation-actions {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

@media (max-width: 960px) {
  .automation-filters__grid {
    grid-template-columns: 1fr;
  }
}
</style>
