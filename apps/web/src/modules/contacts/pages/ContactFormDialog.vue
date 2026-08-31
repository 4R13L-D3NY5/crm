<template>
  <AppDialog
    :model-value="modelValue"
    :title="isEditing ? 'Editar contacto' : 'Nuevo contacto'"
    eyebrow="Contactos"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <q-banner
      v-if="readOnly"
      rounded
      class="contact-form__banner bg-blue-1 text-primary"
    >
      Este formulario esta en modo solo lectura.
    </q-banner>

    <div class="contact-form">
      <q-input
        v-model="form.first_name"
        label="Nombre"
        outlined
        :disable="readOnly"
      />
      <q-input
        v-model="form.last_name"
        label="Apellido"
        outlined
        :disable="readOnly"
      />
      <q-input
        v-model="form.email"
        label="Correo"
        outlined
        :disable="readOnly"
      />
      <q-input
        v-model="form.phone"
        label="Telefono"
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
      <q-input
        v-model="tagsInput"
        label="Etiquetas"
        hint="Separadas por coma"
        outlined
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
import { computed, reactive, ref, watch } from 'vue'

import AppDialog from '@/shared/components/AppDialog.vue'

import type { Contact, ContactPayload } from '../types/contact.types'

const props = defineProps<{
  modelValue: boolean
  contact?: Contact | null
  loading?: boolean
  readOnly?: boolean
}>()

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  submit: [payload: ContactPayload]
}>()

const form = reactive<ContactPayload>({
  first_name: '',
  last_name: '',
  email: '',
  phone: '',
  status: 'active',
  notes: '',
  tags: [],
})

const tagsInput = ref('')

const statusOptions = [
  { label: 'Activo', value: 'active' },
  { label: 'Lead', value: 'lead' },
  { label: 'Inactivo', value: 'inactive' },
]

const isEditing = computed(() => Boolean(props.contact?.id))

watch(
  () => props.contact,
  (contact) => {
    form.first_name = contact?.first_name ?? ''
    form.last_name = contact?.last_name ?? ''
    form.email = contact?.email ?? ''
    form.phone = contact?.phone ?? ''
    form.status = contact?.status ?? 'active'
    form.notes = contact?.notes ?? ''
    tagsInput.value = contact?.tags.map((tag) => tag.name).join(', ') ?? ''
  },
  { immediate: true },
)

function submit() {
  emit('submit', {
    ...form,
    tags: tagsInput.value
      .split(',')
      .map((tag) => tag.trim())
      .filter(Boolean),
  })
}
</script>

<style scoped lang="scss">
.contact-form {
  display: grid;
  gap: 16px;
}

.contact-form__banner {
  margin-bottom: 16px;
}
</style>
