<template>
  <q-dialog
    :model-value="modelValue"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <q-card class="app-dialog">
      <q-card-section class="app-dialog__header">
        <div>
          <div class="app-dialog__eyebrow">
            {{ eyebrow }}
          </div>
          <div class="app-dialog__title">
            {{ title }}
          </div>
        </div>
      </q-card-section>

      <q-separator />

      <q-card-section class="app-dialog__body">
        <slot />
      </q-card-section>

      <q-separator />

      <q-card-actions
        align="right"
        class="app-dialog__actions"
      >
        <slot name="actions" />
      </q-card-actions>
    </q-card>
  </q-dialog>
</template>

<script setup lang="ts">
defineProps<{
  modelValue: boolean
  title: string
  eyebrow?: string
}>()

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
}>()
</script>

<style scoped lang="scss">
.app-dialog {
  width: min(680px, calc(100vw - 32px));
  border: 1px solid rgba(136, 240, 255, 0.12);
  border-radius: 30px;
  background:
    radial-gradient(circle at top right, rgba(53, 194, 255, 0.08), transparent 28%),
    rgba(8, 19, 31, 0.88);
  box-shadow: var(--crm-shadow-card);
  backdrop-filter: blur(18px);
}

.app-dialog__header {
  padding: 24px 28px 18px;
}

.app-dialog__eyebrow {
  color: var(--crm-color-primary);
  font-size: 0.75rem;
  font-weight: 700;
  letter-spacing: 0.14em;
  text-transform: uppercase;
}

.app-dialog__title {
  margin-top: 8px;
  font-family: var(--crm-font-display);
  color: var(--crm-color-ink);
  font-size: 1.8rem;
  font-weight: 700;
  letter-spacing: -0.03em;
}

.app-dialog__body {
  padding: 28px;
}

.app-dialog__actions {
  padding: 18px 28px 24px;
}
</style>
