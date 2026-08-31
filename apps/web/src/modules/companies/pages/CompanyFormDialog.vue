<template>
  <AppDialog
    :model-value="modelValue"
    :title="isEditing ? 'Editar empresa' : 'Nueva empresa'"
    eyebrow="Empresas"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <q-banner
      v-if="readOnly"
      rounded
      class="company-form__banner bg-blue-1 text-primary"
    >
      Este formulario esta en modo solo lectura.
    </q-banner>

    <div class="company-form">
      <q-input
        v-model="form.name"
        label="Nombre"
        outlined
        :disable="readOnly"
        :error="hasError('name')"
        :error-message="firstError('name')"
      />
      <q-input
        v-model="form.industry"
        label="Industria"
        outlined
        :disable="readOnly"
        :error="hasError('industry')"
        :error-message="firstError('industry')"
      />
      <q-input
        v-model="form.website"
        label="Sitio web"
        outlined
        :disable="readOnly"
        :error="hasError('website')"
        :error-message="firstError('website')"
      />
      <q-input
        v-model="form.email"
        label="Correo"
        outlined
        :disable="readOnly"
        :error="hasError('email')"
        :error-message="firstError('email')"
      />
      <q-input
        v-model="form.phone"
        label="Telefono"
        outlined
        :disable="readOnly"
        :error="hasError('phone')"
        :error-message="firstError('phone')"
      />
      <q-select
        v-model="form.status"
        :options="statusOptions"
        emit-value
        map-options
        label="Estado"
        outlined
        :disable="readOnly"
        :error="hasError('status')"
        :error-message="firstError('status')"
      />
      <q-input
        v-model="form.notes"
        label="Notas"
        type="textarea"
        autogrow
        outlined
        :disable="readOnly"
        :error="hasError('notes')"
        :error-message="firstError('notes')"
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

import type { Company, CompanyPayload } from '../types/company.types'

const props = defineProps<{
  modelValue: boolean
  company?: Company | null
  loading?: boolean
  readOnly?: boolean
  errors?: Record<string, string[]>
}>()

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  submit: [payload: CompanyPayload]
}>()

const form = reactive<CompanyPayload>({
  name: '',
  industry: '',
  website: '',
  email: '',
  phone: '',
  status: 'active',
  notes: '',
  contact_ids: [],
})

const statusOptions = [
  { label: 'Activo', value: 'active' },
  { label: 'Lead', value: 'lead' },
  { label: 'Inactivo', value: 'inactive' },
]

const isEditing = computed(() => Boolean(props.company?.id))

watch(
  () => props.company,
  (company) => {
    form.name = company?.name ?? ''
    form.industry = company?.industry ?? ''
    form.website = company?.website ?? ''
    form.email = company?.email ?? ''
    form.phone = company?.phone ?? ''
    form.status = company?.status ?? 'active'
    form.notes = company?.notes ?? ''
    form.contact_ids = company?.contacts.map((contact) => contact.id) ?? []
  },
  { immediate: true },
)

function submit() {
  emit('submit', { ...form })
}

function hasError(field: string) {
  return Boolean(props.errors?.[field]?.length)
}

function firstError(field: string) {
  return props.errors?.[field]?.[0] ?? ''
}
</script>

<style scoped lang="scss">
.company-form {
  display: grid;
  gap: 16px;
}

.company-form__banner {
  margin-bottom: 16px;
}
</style>
