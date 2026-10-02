<template>
  <q-dialog
    :model-value="modelValue"
    persistent
    @update:model-value="emit('update:modelValue', $event)"
  >
    <q-card style="width: 480px; max-width: 95vw" class="whatsapp-form-card">
      <q-card-section class="row items-center justify-between q-pb-none">
        <div class="text-subtitle1 text-weight-bold text-white">Nueva Línea de WhatsApp</div>
        <q-btn flat round dense icon="sym_r_close" color="grey-4" v-close-popup />
      </q-card-section>

      <q-card-section class="q-pt-md">
        <q-form class="q-gutter-y-sm" @submit.prevent="handleSubmit">
          <div>
            <label class="text-caption text-grey-4 q-mb-xs block">Nombre del Canal / Línea *</label>
            <q-input
              v-model="form.name"
              outlined
              dark
              dense
              placeholder="Ej. Línea Comercial Principal"
              :rules="[val => !!val || 'El nombre es obligatorio']"
            >
              <template #prepend>
                <q-icon name="sym_r_label" size="16px" class="text-grey-5" />
              </template>
            </q-input>
          </div>

          <div>
            <label class="text-caption text-grey-4 q-mb-xs block">Número de Teléfono Visible (opcional)</label>
            <q-input
              v-model="form.display_phone_number"
              outlined
              dark
              dense
              placeholder="+591 70000000"
            >
              <template #prepend>
                <q-icon name="sym_r_call" size="16px" class="text-grey-5" />
              </template>
            </q-input>
          </div>

          <div>
            <label class="text-caption text-grey-4 q-mb-xs block">Tipo de Conexión</label>
            <q-select
              v-model="form.session_type"
              :options="sessionTypeOptions"
              emit-value
              map-options
              outlined
              dark
              dense
            />
          </div>

          <q-card-actions align="right" class="q-px-none q-pt-md">
            <q-btn flat label="Cancelar" color="grey-4" no-caps v-close-popup />
            <q-btn
              type="submit"
              unelevated
              label="Crear Canal"
              icon="sym_r_add"
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
import { reactive, watch } from 'vue'
import type { CreateWhatsAppAccountPayload, WhatsAppSessionType } from '../types/whatsapp.types'

const props = defineProps<{
  modelValue: boolean
  loading?: boolean
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', val: boolean): void
  (e: 'submit', payload: CreateWhatsAppAccountPayload): void
}>()

const form = reactive<CreateWhatsAppAccountPayload>({
  name: '',
  display_phone_number: '',
  session_type: 'baileys_qr',
})

const sessionTypeOptions: { label: string; value: WhatsAppSessionType }[] = [
  { label: 'WhatsApp Web / Baileys (Código QR)', value: 'baileys_qr' },
  { label: 'WhatsApp Cloud API (Meta Oficial)', value: 'meta_cloud' },
]

watch(
  () => props.modelValue,
  (open) => {
    if (open) {
      form.name = ''
      form.display_phone_number = ''
      form.session_type = 'baileys_qr'
    }
  },
)

function handleSubmit() {
  emit('submit', { ...form })
}
</script>

<style scoped lang="scss">
.whatsapp-form-card {
  background: var(--crm-bg-card);
  border: 1px solid var(--crm-color-border);
  border-radius: var(--crm-radius-card);
}
</style>
