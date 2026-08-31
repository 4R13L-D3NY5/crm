<template>
  <AppPage
    eyebrow="Ventas"
    title="Pipeline comercial"
    description="Visualiza oportunidades por etapa, crea deals y actualiza el avance sin salir del tablero."
  >
    <template #actions>
      <q-select
        v-model="selectedPipelineId"
        :options="pipelineOptions"
        emit-value
        map-options
        outlined
        dense
        label="Pipeline"
        class="deal-board__pipeline-select"
      />
      <q-btn
        v-if="canManageDeals"
        color="primary"
        label="Nuevo deal"
        unelevated
        :disable="!selectedPipelineId"
        @click="openCreateDialog"
      />
    </template>

    <q-banner
      v-if="pipelinesQuery.isError.value"
      rounded
      class="deal-board__banner bg-red-1 text-negative"
    >
      No se pudieron cargar los pipelines.
    </q-banner>

    <q-card
      flat
      bordered
      class="deal-board__summary"
    >
      <q-card-section class="deal-board__summary-grid">
        <div>
          <div class="deal-board__label">
            Pipeline activo
          </div>
          <div class="deal-board__value">
            {{ boardQuery.data.value?.name ?? 'Sin pipeline' }}
          </div>
        </div>
        <div>
          <div class="deal-board__label">
            Deals activos
          </div>
          <div class="deal-board__value">
            {{ totalDeals }}
          </div>
        </div>
        <div>
          <div class="deal-board__label">
            Monto estimado
          </div>
          <div class="deal-board__value">
            {{ totalAmountLabel }}
          </div>
        </div>
      </q-card-section>
    </q-card>

    <AppLoadingState
      v-if="boardQuery.isLoading.value"
      title="Cargando pipeline"
      description="Estamos organizando etapas, montos y oportunidades del tablero comercial."
    />

    <div
      v-else-if="stages.length"
      class="deal-board"
    >
      <section
        v-for="stage in stages"
        :key="stage.id"
        class="deal-column"
      >
        <div
          class="deal-column__header"
          :style="{ '--stage-color': stage.color }"
        >
          <div>
            <div class="deal-column__name">
              {{ stage.name }}
            </div>
            <div class="deal-column__meta">
              {{ stage.deals.length }} deals
            </div>
          </div>
          <q-badge
            color="primary"
            outline
          >
            {{ getStageAmountLabel(stage) }}
          </q-badge>
        </div>

        <div class="deal-column__body">
          <article
            v-for="deal in stage.deals"
            :key="deal.id"
            class="deal-card"
          >
            <div class="deal-card__top">
              <div>
                <h3 class="deal-card__title">
                  {{ deal.name }}
                </h3>
                <p class="deal-card__amount">
                  {{ formatCurrency(deal.amount) }}
                </p>
              </div>
              <AppStatusBadge :status="deal.status" />
            </div>

            <div class="deal-card__details">
              <div v-if="deal.company">
                Empresa: {{ deal.company.name }}
              </div>
              <div v-if="deal.contact">
                Contacto: {{ deal.contact.name }}
              </div>
              <div v-if="deal.expected_close_date">
                Cierre: {{ deal.expected_close_date }}
              </div>
            </div>

            <q-select
              v-if="canManageDeals"
              :model-value="deal.pipeline_stage_id"
              :options="stageOptions"
              emit-value
              map-options
              dense
              outlined
              label="Mover a"
              :loading="moveStageMutation.isPending.value"
              @update:model-value="moveDeal(deal.id, $event)"
            />

            <div class="deal-card__actions">
              <q-btn
                flat
                dense
                round
                color="teal-4"
                icon="sym_r_forum"
                size="sm"
                to="/app/conversations"
              >
                <q-tooltip>Abrir chat en bandeja</q-tooltip>
              </q-btn>
              <template v-if="canManageDeals">
                <q-btn
                  flat
                  size="sm"
                  label="Editar"
                  color="grey-4"
                  @click="openEditDialog(deal)"
                />
                <q-btn
                  flat
                  color="negative"
                  size="sm"
                  label="Eliminar"
                  @click="removeDeal(deal.id)"
                />
              </template>
              <div
                v-else
                class="deal-card__readonly"
              >
                Solo lectura
              </div>
            </div>
          </article>

          <div
            v-if="stage.deals.length === 0"
            class="deal-column__empty"
          >
            No hay oportunidades en esta etapa.
          </div>
        </div>
      </section>
    </div>

    <AppEmptyState
      v-else
      title="Todavia no hay etapas configuradas"
      description="Configura un pipeline con al menos una etapa para empezar a mover oportunidades en el tablero."
      icon="sym_r_view_kanban"
      class="deal-board__empty"
    />

    <DealFormDialog
      v-model="isDialogOpen"
      :deal="selectedDeal"
      :pipeline-id="selectedPipelineId"
      :stages="stages"
      :contacts="contactsQuery.data.value ?? []"
      :companies="companiesQuery.data.value ?? []"
      :loading="isSubmitting"
      :read-only="!canManageDeals"
      @submit="saveDeal"
    />

    <AppConfirmDialog
      v-model="isDeleteDialogOpen"
      title="Eliminar oportunidad"
      message="Esta accion quitara la oportunidad del tablero actual. Asegurate de que ya no la necesitas antes de continuar."
      confirm-label="Eliminar deal"
      :loading="deleteMutation.isPending.value"
      @confirm="confirmRemoveDeal"
    />
  </AppPage>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'

import { useAuthStore } from '@/modules/auth/stores/auth.store'
import { useAppNotify } from '@/shared/composables/useAppNotify'
import AppConfirmDialog from '@/shared/components/AppConfirmDialog.vue'
import AppEmptyState from '@/shared/components/AppEmptyState.vue'
import AppLoadingState from '@/shared/components/AppLoadingState.vue'
import AppPage from '@/shared/components/AppPage.vue'
import AppStatusBadge from '@/shared/components/AppStatusBadge.vue'

import { useDealBoard, useDealFormOptions, useDealMutations, usePipelines } from '../composables/useDealBoard'
import DealFormDialog from './DealFormDialog.vue'
import type { Deal, DealPayload, PipelineStage } from '../types/deal.types'

const pipelinesQuery = usePipelines()
const selectedPipelineId = ref('')
const boardQuery = useDealBoard(selectedPipelineId)
const { contactsQuery, companiesQuery } = useDealFormOptions()
const { createMutation, updateMutation, moveStageMutation, deleteMutation } = useDealMutations(selectedPipelineId)
const authStore = useAuthStore()
const notify = useAppNotify()

const isDialogOpen = ref(false)
const selectedDeal = ref<Deal | null>(null)
const pendingDeleteDealId = ref<string | null>(null)
const canManageDeals = computed(() => authStore.hasPermission('deals.manage'))

const pipelineOptions = computed(() =>
  (pipelinesQuery.data.value ?? []).map((pipeline) => ({
    label: pipeline.name,
    value: pipeline.id,
  })),
)

const stages = computed(() => boardQuery.data.value?.stages ?? [])

const stageOptions = computed(() =>
  stages.value.map((stage) => ({
    label: `${stage.name} (${stage.probability}%)`,
    value: stage.id,
  })),
)

const totalDeals = computed(() =>
  stages.value.reduce((total, stage) => total + stage.deals.length, 0),
)

const totalAmountLabel = computed(() =>
  formatCurrency(
    stages.value.reduce((total, stage) => {
      return total + stage.deals.reduce((stageTotal, deal) => stageTotal + deal.amount, 0)
    }, 0),
  ),
)
const isDeleteDialogOpen = computed({
  get: () => Boolean(pendingDeleteDealId.value),
  set: (value: boolean) => {
    if (!value) {
      pendingDeleteDealId.value = null
    }
  },
})

const isSubmitting = computed(
  () => createMutation.isPending.value || updateMutation.isPending.value,
)

watch(
  () => pipelinesQuery.data.value,
  (pipelines) => {
    if (!pipelines?.length) {
      selectedPipelineId.value = ''

      return
    }

    const currentExists = pipelines.some((pipeline) => pipeline.id === selectedPipelineId.value)

    if (!currentExists) {
      const defaultPipeline = pipelines.find((pipeline) => pipeline.is_default)
      selectedPipelineId.value = defaultPipeline?.id ?? pipelines[0]?.id ?? ''
    }
  },
  { immediate: true },
)

function openCreateDialog() {
  selectedDeal.value = null
  isDialogOpen.value = true
}

function openEditDialog(deal: Deal) {
  selectedDeal.value = deal
  isDialogOpen.value = true
}

async function saveDeal(payload: DealPayload) {
  try {
    if (selectedDeal.value) {
      await updateMutation.mutateAsync({
        id: selectedDeal.value.id,
        payload,
      })
      notify.success('Deal actualizado.', 'La oportunidad ya refleja los cambios en el tablero.')
    } else {
      await createMutation.mutateAsync(payload)
      notify.success('Deal creado.', 'La nueva oportunidad ya forma parte del pipeline.')
    }

    isDialogOpen.value = false
    selectedDeal.value = null
  } catch (error) {
    notify.fromError(error, 'No se pudo guardar la oportunidad.')
  }
}

async function moveDeal(dealId: string, stageId: string) {
  if (!stageId) {
    return
  }

  try {
    await moveStageMutation.mutateAsync({
      dealId,
      pipelineStageId: stageId,
    })
    notify.info('Deal movido.', 'La oportunidad cambio de etapa correctamente.')
  } catch (error) {
    notify.fromError(error, 'No se pudo mover la oportunidad.')
  }
}

async function removeDeal(dealId: string) {
  pendingDeleteDealId.value = dealId
}

async function confirmRemoveDeal() {
  if (!pendingDeleteDealId.value) {
    return
  }

  try {
    await deleteMutation.mutateAsync(pendingDeleteDealId.value)
    notify.info('Deal eliminado.', 'La oportunidad ya no aparece en este pipeline.')
    pendingDeleteDealId.value = null
  } catch (error) {
    notify.fromError(error, 'No se pudo eliminar la oportunidad.')
  }
}

function getStageAmountLabel(stage: PipelineStage) {
  const total = stage.deals.reduce((sum, deal) => sum + deal.amount, 0)

  return formatCurrency(total)
}

function formatCurrency(value: number) {
  return new Intl.NumberFormat('es-BO', {
    style: 'currency',
    currency: 'BOB',
    maximumFractionDigits: 2,
  }).format(value)
}
</script>

<style scoped lang="scss">
.deal-board__pipeline-select {
  min-width: 220px;
}

.deal-board__banner,
.deal-board__summary,
.deal-board__empty {
  border-radius: var(--crm-radius-card);
  background-color: var(--crm-bg-card);
  border: 1px solid var(--crm-color-border);
}

.deal-board__summary-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 16px;
}

.deal-board__label {
  color: var(--crm-color-muted);
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.deal-board__value {
  margin-top: 4px;
  color: var(--crm-color-ink);
  font-size: 1.6rem;
  font-weight: 700;
}

.deal-board__loading {
  display: flex;
  justify-content: center;
  padding: 40px 0;
}

.deal-board {
  display: grid;
  grid-auto-flow: column;
  grid-auto-columns: minmax(300px, 1fr);
  gap: 16px;
  overflow-x: auto;
  padding-bottom: 12px;
}

.deal-column {
  display: grid;
  gap: 12px;
  align-content: start;
}

.deal-column__header {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  align-items: flex-start;
  padding: 14px 16px;
  border-radius: 12px;
  background-color: var(--crm-bg-card);
  border: 1px solid var(--crm-color-border);
}

.deal-column__name {
  color: var(--crm-color-ink);
  font-size: 1.1rem;
  font-weight: 700;
}

.deal-column__meta {
  margin-top: 6px;
  color: var(--crm-color-muted);
  font-size: 0.9rem;
}

.deal-column__body {
  display: grid;
  gap: 14px;
}

.deal-card {
  display: grid;
  gap: 14px;
  padding: 18px;
  border: 1px solid rgba(118, 198, 255, 0.12);
  border-radius: 24px;
  background:
    radial-gradient(circle at top right, rgba(73, 194, 255, 0.06), transparent 24%),
    linear-gradient(180deg, rgba(14, 27, 43, 0.96) 0%, rgba(9, 20, 33, 0.94) 100%);
  box-shadow: 0 14px 36px rgba(2, 12, 26, 0.18);
}

.deal-card__top {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  align-items: flex-start;
}

.deal-card__title {
  margin: 0;
  color: var(--crm-color-ink);
  font-size: 1.05rem;
}

.deal-card__amount {
  margin: 6px 0 0;
  color: var(--crm-color-muted);
  font-weight: 600;
}

.deal-card__details {
  display: grid;
  gap: 6px;
  color: var(--crm-color-muted);
  font-size: 0.92rem;
}

.deal-card__actions {
  display: flex;
  justify-content: flex-end;
  align-items: center;
  gap: 8px;
}

.deal-card__readonly {
  color: var(--crm-color-muted);
  font-size: 0.85rem;
  font-weight: 600;
}

.deal-column__empty {
  padding: 20px;
  border: 1px dashed rgba(122, 226, 231, 0.18);
  border-radius: 20px;
  color: var(--crm-color-muted);
  background: rgba(10, 22, 36, 0.88);
}

@media (max-width: 960px) {
  .deal-board__summary-grid {
    grid-template-columns: 1fr;
  }
}
</style>
