<template>
  <AppDialog
    :model-value="modelValue"
    :title="isEditing ? 'Editar deal' : 'Nuevo deal'"
    eyebrow="Pipeline"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <q-banner
      v-if="readOnly"
      rounded
      class="deal-form__banner bg-blue-1 text-primary"
    >
      Este formulario esta en modo solo lectura.
    </q-banner>

    <div class="deal-form">
      <q-input
        v-model="form.name"
        label="Nombre de la oportunidad"
        outlined
        :disable="readOnly"
      />
      <div class="deal-form__grid">
        <q-select
          v-model="form.pipeline_stage_id"
          :options="stageOptions"
          emit-value
          map-options
          label="Etapa"
          outlined
          :disable="readOnly"
        />
        <q-select
          v-model="form.status"
          :options="statusOptions"
          emit-value
          map-options
          label="Estado"
          outlined
          :disable="readOnly"
        />
      </div>
      <div class="deal-form__grid">
        <q-input
          v-model.number="form.amount"
          type="number"
          min="0"
          step="0.01"
          label="Monto estimado"
          outlined
          :disable="readOnly"
        />
        <q-input
          v-model.number="form.probability"
          type="number"
          min="0"
          max="100"
          label="Probabilidad"
          outlined
          :disable="readOnly"
        />
      </div>
      <div class="deal-form__grid">
        <q-input
          v-model="form.expected_close_date"
          type="date"
          label="Cierre esperado"
          outlined
          :disable="readOnly"
        />
        <q-select
          v-model="form.contact_id"
          :options="contactOptions"
          emit-value
          map-options
          label="Contacto"
          outlined
          clearable
          :disable="readOnly"
        />
      </div>
      <q-select
        v-model="form.company_id"
        :options="companyOptions"
        emit-value
        map-options
        label="Empresa"
        outlined
        clearable
        :disable="readOnly"
      />
      <q-input
        v-model="form.notes"
        label="Notas"
        type="textarea"
        autogrow
        outlined
        :disable="readOnly"
      />
    </div>

    <template #actions>
      <q-btn
        flat
        label="Cancelar"
        @click="emit('update:modelValue', false)"
      />
      <q-btn
        v-if="!readOnly"
        color="primary"
        label="Guardar"
        unelevated
        :loading="loading"
        @click="submit"
      />
    </template>
  </AppDialog>
</template>

<script setup lang="ts">
import { computed, reactive, watch } from 'vue'

import AppDialog from '@/shared/components/AppDialog.vue'

import type { Company } from '@/modules/companies/types/company.types'
import type { Contact } from '@/modules/contacts/types/contact.types'

import type { Deal, DealPayload, PipelineStage } from '../types/deal.types'

const props = defineProps<{
  modelValue: boolean
  deal?: Deal | null
  pipelineId: string
  stages: PipelineStage[]
  contacts: Contact[]
  companies: Company[]
  loading?: boolean
  readOnly?: boolean
}>()

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  submit: [payload: DealPayload]
}>()

const form = reactive<DealPayload>({
  pipeline_id: '',
  pipeline_stage_id: '',
  name: '',
  status: 'open',
  amount: 0,
  probability: 0,
  expected_close_date: null,
  notes: '',
  contact_id: null,
  company_id: null,
})

const isEditing = computed(() => Boolean(props.deal?.id))

const statusOptions = [
  { label: 'Abierto', value: 'open' },
  { label: 'Ganado', value: 'won' },
  { label: 'Perdido', value: 'lost' },
]

const stageOptions = computed(() =>
  props.stages.map((stage) => ({
    label: `${stage.name} (${stage.probability}%)`,
    value: stage.id,
  })),
)

const contactOptions = computed(() =>
  props.contacts.map((contact) => ({
    label: contact.name,
    value: contact.id,
  })),
)

const companyOptions = computed(() =>
  props.companies.map((company) => ({
    label: company.name,
    value: company.id,
  })),
)

watch(
  () => [props.deal, props.pipelineId, props.stages] as const,
  () => {
    const defaultStage = props.stages[0] ?? null

    form.pipeline_id = props.pipelineId
    form.pipeline_stage_id = props.deal?.pipeline_stage_id ?? defaultStage?.id ?? ''
    form.name = props.deal?.name ?? ''
    form.status = props.deal?.status ?? 'open'
    form.amount = props.deal?.amount ?? 0
    form.probability = props.deal?.probability ?? defaultStage?.probability ?? 0
    form.expected_close_date = props.deal?.expected_close_date ?? null
    form.notes = props.deal?.notes ?? ''
    form.contact_id = props.deal?.contact?.id ?? null
    form.company_id = props.deal?.company?.id ?? null
  },
  { immediate: true },
)

watch(
  () => form.pipeline_stage_id,
  (stageId) => {
    const stage = props.stages.find((item) => item.id === stageId)

    if (stage) {
      form.probability = stage.probability
    }
  },
)

function submit() {
  emit('submit', {
    ...form,
    expected_close_date: form.expected_close_date || null,
  })
}
</script>

<style scoped lang="scss">
.deal-form {
  display: grid;
  gap: 16px;
}

.deal-form__banner {
  margin-bottom: 16px;
}

.deal-form__grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 16px;
}

@media (max-width: 960px) {
  .deal-form__grid {
    grid-template-columns: 1fr;
  }
}
</style>
