<template>
  <q-dialog
    :model-value="modelValue"
    persistent
    @update:model-value="emit('update:modelValue', $event)"
  >
    <q-card style="width: 480px; max-width: 95vw" class="whatsapp-simulate-card">
      <q-card-section class="row items-center justify-between q-pb-none">
        <div>
          <div class="text-subtitle1 text-weight-bold text-white">Simular Mensaje Entrante</div>
          <div class="text-caption text-grey-4">
            Inyecta un mensaje en tiempo real simulando a un cliente en WhatsApp
          </div>
        </div>
        <q-btn flat round dense icon="sym_r_close" color="grey-4" v-close-popup />
      </q-card-section>

      <q-card-section class="q-pt-md">
        <q-form class="q-gutter-y-sm" @submit.prevent="handleSubmit">
          <div class="row q-col-gutter-sm">
            <div class="col-12 col-md-6">
              <label class="text-caption text-grey-4 q-mb-xs block">Teléfono Cliente *</label>
              <q-input
                v-model="form.from_phone"
                outlined
                dark
                dense
                placeholder="+59170123456"
                :rules="[val => !!val || 'El teléfono es obligatorio']"
              >
                <template #prepend>
                  <q-icon name="sym_r_call" size="16px" class="text-grey-5" />
                </template>
              </q-input>
            </div>

            <div class="col-12 col-md-6">
              <label class="text-caption text-grey-4 q-mb-xs block">Nombre Cliente</label>
              <q-input
                v-model="form.from_name"
                outlined
                dark
                dense
                placeholder="Carlos Mendoza"
              >
                <template #prepend>
                  <q-icon name="sym_r_person" size="16px" class="text-grey-5" />
                </template>
              </q-input>
            </div>
          </div>

          <div>
            <label class="text-caption text-grey-4 q-mb-xs block">Mensaje de WhatsApp *</label>
            <q-input
              v-model="form.message"
              outlined
              dark
              dense
              type="textarea"
              rows="3"
              placeholder="Hola, me gustaría recibir más detalles sobre la propuesta comercial."
              :rules="[val => !!val || 'El mensaje es obligatorio']"
            />
          </div>

          <q-card-actions align="right" class="q-px-none q-pt-md">
            <q-btn flat label="Cancelar" color="grey-4" no-caps v-close-popup />
            <q-btn
              type="submit"
              unelevated
              label="Simular Llegada de Mensaje"
              icon="sym_r_send"
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
import type { SimulateIncomingPayload } from '../types/whatsapp.types'

const props = defineProps<{
  modelValue: boolean
  loading?: boolean
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', val: boolean): void
  (e: 'submit', payload: SimulateIncomingPayload): void
}>()

const form = reactive<SimulateIncomingPayload>({
  from_phone: '+59170123456',
  from_name: 'Carlos Mendoza',
  message: 'Hola, buenas tardes. Quisiera cotizar el servicio para mi empresa.',
})

watch(
  () => props.modelValue,
  (open) => {
    if (open) {
      if (!form.from_phone) {
        form.from_phone = '+59170123456'
        form.from_name = 'Carlos Mendoza'
        form.message = 'Hola, buenas tardes. Quisiera cotizar el servicio para mi empresa.'
      }
    }
  },
)

function handleSubmit() {
  emit('submit', { ...form })
}
</script>

<style scoped lang="scss">
.whatsapp-simulate-card {
  background: var(--crm-bg-card);
  border: 1px solid var(--crm-color-border);
  border-radius: var(--crm-radius-card);
}
</style>
