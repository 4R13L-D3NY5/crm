<template>
  <q-dialog
    :model-value="modelValue"
    persistent
    @update:model-value="emit('update:modelValue', $event)"
  >
    <q-card style="width: 440px; max-width: 95vw" class="transfer-dialog-card">
      <q-card-section class="row items-center justify-between q-pb-none">
        <div class="text-subtitle1 text-weight-bold text-white">Transferir Conversación</div>
        <q-btn flat round dense icon="sym_r_close" color="grey-4" v-close-popup />
      </q-card-section>

      <q-card-section class="q-pt-md">
        <q-form class="q-gutter-y-sm" @submit.prevent="handleSubmit">
          <div>
            <label class="text-caption text-grey-4 q-mb-xs block">Operador de Destino</label>
            <q-select
              v-model="selectedUserId"
              :options="userOptions"
              emit-value
              map-options
              outlined
              dark
              dense
              clearable
              label="Seleccionar operador"
            >
              <template #prepend>
                <q-icon name="sym_r_person" size="16px" class="text-grey-5" />
              </template>
            </q-select>
          </div>

          <div>
            <label class="text-caption text-grey-4 q-mb-xs block">Nota de Transferencia (opcional)</label>
            <q-input
              v-model="note"
              type="textarea"
              rows="2"
              outlined
              dark
              dense
              placeholder="Motivo de la transferencia..."
            />
          </div>

          <q-card-actions align="right" class="q-px-none q-pt-md">
            <q-btn flat label="Cancelar" color="grey-4" no-caps v-close-popup />
            <q-btn
              type="submit"
              unelevated
              label="Confirmar Transferencia"
              icon="sym_r_swap_horiz"
              class="xf-btn-primary"
              no-caps
              :loading="loading"
            />
          </q-card-actions>
        </q-form>
      </q-card-section>
    </q-card>
  </q-dialog>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'

const props = defineProps<{
  modelValue: boolean
  loading?: boolean
  users?: { id: string; name: string }[]
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', val: boolean): void
  (e: 'submit', payload: {
    assigned_to_user_id?: string | null
    transfer_note?: string | null
    user_id?: string | null
    note?: string | null
  }): void
}>()

const selectedUserId = ref<string | null>(null)
const note = ref('')

const userOptions = computed(() => {
  if (!props.users || props.users.length === 0) return []
  return props.users.map(u => ({ label: u.name, value: u.id }))
})

watch(
  () => props.modelValue,
  (isOpen) => {
    if (!isOpen) {
      selectedUserId.value = null
      note.value = ''
    }
  },
)

function handleSubmit() {
  emit('submit', {
    assigned_to_user_id: selectedUserId.value || null,
    user_id: selectedUserId.value || null,
    transfer_note: note.value || null,
    note: note.value || null,
  })
}
</script>

<style scoped lang="scss">
.transfer-dialog-card {
  background: var(--crm-bg-card);
  border: 1px solid var(--crm-color-border);
  border-radius: var(--crm-radius-card);
}
</style>
