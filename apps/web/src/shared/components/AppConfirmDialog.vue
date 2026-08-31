<template>
  <AppDialog
    :model-value="modelValue"
    eyebrow="Confirmacion"
    :title="title"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <div class="app-confirm-dialog">
      <q-icon
        :name="icon"
        size="28px"
        class="app-confirm-dialog__icon"
      />
      <p class="app-confirm-dialog__message">
        {{ message }}
      </p>
    </div>

    <template #actions>
      <q-btn
        flat
        label="Cancelar"
        @click="emit('update:modelValue', false)"
      />
      <q-btn
        :color="confirmColor"
        :label="confirmLabel"
        unelevated
        :loading="loading"
        @click="emit('confirm')"
      />
    </template>
  </AppDialog>
</template>

<script setup lang="ts">
import AppDialog from './AppDialog.vue'

withDefaults(
  defineProps<{
    modelValue: boolean
    title: string
    message: string
    confirmLabel?: string
    confirmColor?: string
    icon?: string
    loading?: boolean
  }>(),
  {
    confirmLabel: 'Confirmar',
    confirmColor: 'negative',
    icon: 'sym_r_warning',
    loading: false,
  },
)

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  confirm: []
}>()
</script>

<style scoped lang="scss">
.app-confirm-dialog {
  display: grid;
  gap: 14px;
}

.app-confirm-dialog__icon {
  color: var(--crm-color-primary);
}

.app-confirm-dialog__message {
  margin: 0;
  color: var(--crm-color-muted);
  line-height: 1.7;
}
</style>
