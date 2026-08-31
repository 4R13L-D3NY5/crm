<template>
  <AppDialog
    :model-value="modelValue"
    :title="isEditing ? 'Editar automatizacion' : 'Nueva automatizacion'"
    eyebrow="Automations"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <q-banner
      v-if="readOnly"
      rounded
      class="automation-form__banner bg-blue-1 text-primary"
    >
      Este formulario esta en modo solo lectura.
    </q-banner>

    <div class="automation-form">
      <q-input
        v-model="form.name"
        label="Nombre"
        outlined
        :disable="readOnly"
      />

      <div class="automation-form__grid">
        <q-select
          v-model="form.trigger_type"
          :options="triggerOptions"
          emit-value
          map-options
          label="Trigger"
          outlined
          :disable="readOnly"
        />
        <q-select
          v-model="form.conditions.channel"
          :options="channelOptions"
          emit-value
          map-options
          label="Canal"
          outlined
          clearable
          :disable="readOnly"
        />
      </div>

      <q-input
        v-model="form.conditions.message_contains"
        label="Mensaje contiene"
        hint="Opcional. Solo aplica para mensajes inbound."
        outlined
        :disable="readOnly"
      />

      <div class="automation-form__grid">
        <q-select
          v-model="statusActionValue"
          :options="statusOptions"
          emit-value
          map-options
          label="Cambiar estado a"
          outlined
          clearable
          :disable="readOnly"
        />
        <q-select
          v-model="assignActionValue"
          :options="userOptions"
          emit-value
          map-options
          label="Asignar a"
          outlined
          clearable
          :disable="readOnly"
        />
      </div>

      <q-input
        v-model="autoReplyActionValue"
        label="Respuesta automatica WhatsApp"
        hint="Opcional. Se envia una sola vez por conversacion cuando la regla coincide."
        type="textarea"
        autogrow
        outlined
        :disable="readOnly"
      />

      <q-toggle
        v-model="form.is_active"
        label="Regla activa"
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

import type { UserOption } from '@/modules/conversations/types/conversation.types'
import AppDialog from '@/shared/components/AppDialog.vue'

import type { AutomationRule, AutomationRulePayload } from '../types/automation.types'

const props = defineProps<{
  modelValue: boolean
  rule?: AutomationRule | null
  users: UserOption[]
  loading?: boolean
  readOnly?: boolean
}>()

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  submit: [payload: AutomationRulePayload]
}>()

const form = reactive<AutomationRulePayload>({
  name: '',
  trigger_type: 'message.inbound.received',
  conditions: {
    channel: 'whatsapp',
    message_contains: '',
  },
  actions: [],
  is_active: true,
})

const statusActionValue = ref<string | null>(null)
const assignActionValue = ref<string | null>(null)
const autoReplyActionValue = ref('')

const isEditing = computed(() => Boolean(props.rule?.id))

const triggerOptions = [
  { label: 'Conversacion creada', value: 'conversation.created' },
  { label: 'Mensaje inbound recibido', value: 'message.inbound.received' },
]

const channelOptions = [
  { label: 'Manual', value: 'manual' },
  { label: 'WhatsApp', value: 'whatsapp' },
  { label: 'Email', value: 'email' },
]

const statusOptions = [
  { label: 'Abierta', value: 'open' },
  { label: 'Pendiente', value: 'pending' },
  { label: 'Resuelta', value: 'resolved' },
]

const userOptions = computed(() =>
  props.users.map((user) => ({
    label: user.name,
    value: user.id,
  })),
)

watch(
  () => props.rule,
  (rule) => {
    form.name = rule?.name ?? ''
    form.trigger_type = rule?.trigger_type ?? 'message.inbound.received'
    form.conditions = {
      channel: rule?.conditions?.channel ?? 'whatsapp',
      message_contains: rule?.conditions?.message_contains ?? '',
    }
    form.is_active = rule?.is_active ?? true

    const statusAction = rule?.actions.find((action) => action.type === 'set_status')
    const assignAction = rule?.actions.find((action) => action.type === 'assign_user')
    const autoReplyAction = rule?.actions.find((action) => action.type === 'send_whatsapp_message')
    statusActionValue.value = statusAction?.value ?? null
    assignActionValue.value = assignAction?.value ?? null
    autoReplyActionValue.value = autoReplyAction?.value ?? ''
  },
  { immediate: true },
)

function submit() {
  const actions = []

  if (statusActionValue.value) {
    actions.push({
      type: 'set_status' as const,
      value: statusActionValue.value,
    })
  }

  if (assignActionValue.value) {
    actions.push({
      type: 'assign_user' as const,
      value: assignActionValue.value,
    })
  }

  if (autoReplyActionValue.value.trim()) {
    actions.push({
      type: 'send_whatsapp_message' as const,
      value: autoReplyActionValue.value.trim(),
    })
  }

  emit('submit', {
    ...form,
    conditions: {
      channel: form.conditions.channel || '',
      message_contains: form.conditions.message_contains || '',
    },
    actions,
  })
}
</script>

<style scoped lang="scss">
.automation-form {
  display: grid;
  gap: 16px;
}

.automation-form__banner {
  margin-bottom: 16px;
}

.automation-form__grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 16px;
}

@media (max-width: 960px) {
  .automation-form__grid {
    grid-template-columns: 1fr;
  }
}
</style>
