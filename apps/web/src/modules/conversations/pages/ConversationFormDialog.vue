<template>
  <AppDialog
    :model-value="modelValue"
    title="Nueva conversacion"
    eyebrow="Inbox"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <q-banner
      v-if="readOnly"
      rounded
      class="conversation-form__banner bg-blue-1 text-primary"
    >
      Este formulario esta en modo solo lectura.
    </q-banner>

    <div class="conversation-form">
      <q-input
        v-model="form.subject"
        label="Asunto"
        outlined
        :disable="readOnly"
      />
      <div class="conversation-form__grid">
        <q-select
          v-model="form.status"
          :options="statusOptions"
          emit-value
          map-options
          label="Estado"
          outlined
          :disable="readOnly"
        />
        <q-select
          v-model="form.channel"
          :options="channelOptions"
          emit-value
          map-options
          label="Canal"
          outlined
          :disable="readOnly"
        />
      </div>
      <div class="conversation-form__grid">
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
      </div>
      <q-select
        v-model="form.assigned_to_user_id"
        :options="userOptions"
        emit-value
        map-options
        label="Asignar a"
        outlined
        clearable
        :disable="readOnly"
      />
      <q-input
        v-model="form.message"
        label="Mensaje inicial"
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
        label="Crear conversacion"
        unelevated
        :loading="loading"
        @click="emit('submit', form)"
      />
    </template>
  </AppDialog>
</template>

<script setup lang="ts">
import { computed, reactive } from 'vue'

import type { Company } from '@/modules/companies/types/company.types'
import type { Contact } from '@/modules/contacts/types/contact.types'
import AppDialog from '@/shared/components/AppDialog.vue'

import type { CreateConversationPayload, UserOption } from '../types/conversation.types'

const props = defineProps<{
  modelValue: boolean
  contacts: Contact[]
  companies: Company[]
  users: UserOption[]
  loading?: boolean
  readOnly?: boolean
}>()

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  submit: [payload: CreateConversationPayload]
}>()

const form = reactive<CreateConversationPayload>({
  subject: '',
  status: 'open',
  channel: 'manual',
  contact_id: null,
  company_id: null,
  message: '',
  assigned_to_user_id: null,
})

const statusOptions = [
  { label: 'Abierta', value: 'open' },
  { label: 'Pendiente', value: 'pending' },
  { label: 'Resuelta', value: 'resolved' },
]

const channelOptions = [
  { label: 'Manual', value: 'manual' },
  { label: 'WhatsApp', value: 'whatsapp' },
  { label: 'Email', value: 'email' },
]

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

const userOptions = computed(() =>
  props.users.map((user) => ({
    label: user.name,
    value: user.id,
  })),
)
</script>

<style scoped lang="scss">
.conversation-form {
  display: grid;
  gap: 16px;
}

.conversation-form__banner {
  margin-bottom: 16px;
}

.conversation-form__grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 16px;
}

@media (max-width: 960px) {
  .conversation-form__grid {
    grid-template-columns: 1fr;
  }
}
</style>
