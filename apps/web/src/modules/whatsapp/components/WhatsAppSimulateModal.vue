<template>
  <q-dialog
    :model-value="modelValue"
    persistent
    @update:model-value="emit('update:modelValue', $event)"
  >
    <q-card style="width: 480px; max-width: 95vw" class="whatsapp-simulate-card">
      <q-card-section class="row items-center justify-between q-pb-none">
        <div class="row items-center q-gutter-x-sm">
          <q-avatar size="28px" :style="{ background: platformMeta.color + '22' }">
            <q-icon :name="platformMeta.icon" size="16px" :style="{ color: platformMeta.color }" />
          </q-avatar>
          <div>
            <div class="text-subtitle1 text-weight-bold text-white">Simular Mensaje Entrante</div>
            <div class="text-caption text-grey-4">
              Canal: <span :style="{ color: platformMeta.color, fontWeight: 600 }">{{ account?.name || platformMeta.label }}</span>
            </div>
          </div>
        </div>
        <q-btn flat round dense icon="sym_r_close" color="grey-4" v-close-popup />
      </q-card-section>

      <q-card-section class="q-pt-md">
        <q-form class="q-gutter-y-sm" @submit.prevent="handleSubmit">
          <div class="row q-col-gutter-sm">
            <div class="col-12 col-md-6">
              <label class="text-caption text-grey-4 q-mb-xs block">{{ platformMeta.idLabel }} *</label>
              <q-input
                v-model="form.from_phone"
                outlined
                dark
                dense
                :placeholder="platformMeta.idPlaceholder"
                :rules="[val => !!val || 'El identificador o teléfono es obligatorio']"
              >
                <template #prepend>
                  <q-icon :name="platformMeta.idIcon" size="16px" class="text-grey-5" />
                </template>
              </q-input>
            </div>

            <div class="col-12 col-md-6">
              <label class="text-caption text-grey-4 q-mb-xs block">Nombre del Remitente</label>
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
            <label class="text-caption text-grey-4 q-mb-xs block">Mensaje / Consulta del Cliente *</label>
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

          <div class="simulate-hint q-pa-sm q-mt-xs">
            <q-icon name="sym_r_bolt" size="16px" color="amber-4" class="q-mr-xs" />
            <span class="text-caption text-grey-4">
              Este mensaje llegará de inmediato a la <strong>Bandeja Multicanal</strong> clasificado bajo el canal de <strong>{{ platformMeta.label }}</strong>.
            </span>
          </div>

          <q-card-actions align="right" class="q-px-none q-pt-md">
            <q-btn flat label="Cancelar" color="grey-4" no-caps v-close-popup />
            <q-btn
              type="submit"
              unelevated
              label="Enviar Mensaje Simulado"
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
import { computed, reactive, watch } from 'vue'
import type { SimulateIncomingPayload, WhatsAppAccount } from '../types/whatsapp.types'

const props = defineProps<{
  modelValue: boolean
  loading?: boolean
  account?: WhatsAppAccount | null
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

const platformMeta = computed(() => {
  switch (props.account?.session_type) {
    case 'facebook':
      return {
        label: 'Facebook Fanpage',
        icon: 'sym_r_public',
        color: '#1877f2',
        idLabel: 'ID / Perfil Facebook',
        idPlaceholder: 'fb_user_88301',
        idIcon: 'sym_r_badge',
      }
    case 'instagram':
      return {
        label: 'Instagram Direct',
        icon: 'sym_r_photo_camera',
        color: '#e1306c',
        idLabel: 'Usuario Instagram',
        idPlaceholder: '@carlos_mendoza',
        idIcon: 'sym_r_alternate_email',
      }
    case 'tiktok':
      return {
        label: 'TikTok Business',
        icon: 'sym_r_music_note',
        color: '#25f4ee',
        idLabel: 'Usuario TikTok',
        idPlaceholder: '@carlos_tiktok',
        idIcon: 'sym_r_alternate_email',
      }
    case 'meta_cloud':
      return {
        label: 'WhatsApp Cloud API',
        icon: 'sym_r_cloud',
        color: '#10b981',
        idLabel: 'Teléfono Cliente',
        idPlaceholder: '+59170123456',
        idIcon: 'sym_r_call',
      }
    case 'baileys_qr':
    default:
      return {
        label: 'WhatsApp',
        icon: 'sym_r_chat',
        color: '#10b981',
        idLabel: 'Teléfono Cliente',
        idPlaceholder: '+59170123456',
        idIcon: 'sym_r_call',
      }
  }
})

watch(
  () => props.modelValue,
  (open) => {
    if (open) {
      if (props.account?.session_type === 'facebook') {
        form.from_phone = 'fb_usr_77192'
        form.from_name = 'Elena Ramos'
        form.message = '¡Hola! Vi su publicación sobre el plan anual y quiero informes.'
      } else if (props.account?.session_type === 'instagram') {
        form.from_phone = '@sofia_direct'
        form.from_name = 'Sofia Miranda'
        form.message = 'Hola, ¿qué costo tiene la suscripción?'
      } else if (props.account?.session_type === 'tiktok') {
        form.from_phone = '@usuario_tiktok'
        form.from_name = 'Rodrigo Paz'
        form.message = 'Vi su video en TikTok, ¿pueden enviarme una cotización?'
      } else {
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

.simulate-hint {
  background: rgba(245, 158, 11, 0.06);
  border: 1px solid rgba(245, 158, 11, 0.2);
  border-radius: 6px;
  display: flex;
  align-items: center;
}
</style>
