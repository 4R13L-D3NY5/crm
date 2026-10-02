<template>
  <q-dialog
    :model-value="modelValue"
    persistent
    @update:model-value="emit('update:modelValue', $event)"
  >
    <q-card style="width: 440px; max-width: 95vw" class="whatsapp-qr-card text-center q-pa-lg">
      <div class="row items-center justify-between q-mb-md">
        <div class="text-subtitle1 text-weight-bold text-white">Vincular Dispositivo</div>
        <q-btn flat round dense icon="sym_r_close" color="grey-4" v-close-popup />
      </div>

      <p class="text-caption text-grey-4 q-mb-md">
        Abre WhatsApp en tu teléfono > <strong>Dispositivos vinculados</strong> > <strong>Vincular un dispositivo</strong> y apunta tu cámara al código:
      </p>

      <!-- Contenedor del Código QR -->
      <div class="qr-box q-my-md">
        <div v-if="loading" class="row items-center justify-center" style="height: 200px">
          <q-spinner-dots size="40px" color="teal-4" />
        </div>
        <div v-else class="column items-center">
          <div class="qr-canvas-wrap">
            <svg width="180" height="180" viewBox="0 0 200 200" fill="none">
              <rect width="200" height="200" rx="8" fill="white" />
              <!-- Esquinas -->
              <rect x="20" y="20" width="45" height="45" fill="#090d16" rx="4" />
              <rect x="28" y="28" width="29" height="29" fill="white" rx="2" />
              <rect x="34" y="34" width="17" height="17" fill="#10B981" rx="1" />

              <rect x="135" y="20" width="45" height="45" fill="#090d16" rx="4" />
              <rect x="143" y="28" width="29" height="29" fill="white" rx="2" />
              <rect x="149" y="34" width="17" height="17" fill="#10B981" rx="1" />

              <rect x="20" y="135" width="45" height="45" fill="#090d16" rx="4" />
              <rect x="28" y="143" width="29" height="29" fill="white" rx="2" />
              <rect x="34" y="149" width="17" height="17" fill="#10B981" rx="1" />

              <!-- Puntos de datos dinámicos simulados -->
              <rect x="85" y="25" width="10" height="10" fill="#090d16" />
              <rect x="105" y="35" width="10" height="10" fill="#090d16" />
              <rect x="75" y="55" width="10" height="10" fill="#090d16" />
              <rect x="95" y="70" width="12" height="12" fill="#090d16" />
              <rect x="115" y="55" width="10" height="10" fill="#090d16" />
              <rect x="85" y="95" width="15" height="15" fill="#10B981" />
              <rect x="135" y="95" width="10" height="10" fill="#090d16" />
              <rect x="155" y="115" width="10" height="10" fill="#090d16" />
              <rect x="75" y="115" width="10" height="10" fill="#090d16" />
              <rect x="95" y="135" width="10" height="10" fill="#090d16" />
              <rect x="115" y="155" width="10" height="10" fill="#090d16" />
              <rect x="145" y="145" width="15" height="15" fill="#090d16" />
            </svg>
          </div>

          <div class="row items-center q-gutter-x-xs text-caption text-grey-4 q-mt-sm">
            <q-icon name="sym_r_timer" size="14px" color="teal-4" />
            <span>Expira en <strong>{{ countdown }}s</strong></span>
          </div>
        </div>
      </div>

      <!-- Botones de Acción -->
      <q-card-actions align="center" class="q-gutter-sm q-mt-sm">
        <q-btn
          unelevated
          no-caps
          label="Simular Escaneo Exitoso"
          icon="sym_r_qr_code_scanner"
          class="xf-btn-primary"
          :loading="scanLoading"
          @click="emit('scan')"
        />
        <q-btn flat label="Cerrar" color="grey-4" no-caps v-close-popup />
      </q-card-actions>
    </q-card>
  </q-dialog>
</template>

<script setup lang="ts">
import { onMounted, onUnmounted, ref, watch } from 'vue'

const props = defineProps<{
  modelValue: boolean
  loading?: boolean
  scanLoading?: boolean
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', val: boolean): void
  (e: 'scan'): void
  (e: 'refresh'): void
}>()

const countdown = ref(60)
let timer: any = null

watch(
  () => props.modelValue,
  (open) => {
    if (open) {
      countdown.value = 60
      clearInterval(timer)
      timer = setInterval(() => {
        if (countdown.value > 1) {
          countdown.value--
        } else {
          countdown.value = 60
          emit('refresh')
        }
      }, 1000)
    } else {
      clearInterval(timer)
    }
  },
)

onMounted(() => {
  if (props.modelValue) {
    countdown.value = 60
  }
})

onUnmounted(() => {
  clearInterval(timer)
})
</script>

<style scoped lang="scss">
.whatsapp-qr-card {
  background: var(--crm-bg-card);
  border: 1px solid var(--crm-color-border);
  border-radius: var(--crm-radius-card);
}

.qr-box {
  padding: 16px;
  background: rgba(255, 255, 255, 0.02);
  border: 1px solid var(--crm-color-border);
  border-radius: 12px;
}

.qr-canvas-wrap {
  padding: 10px;
  background: #ffffff;
  border-radius: 8px;
  box-shadow: 0 4px 14px rgba(0, 0, 0, 0.4);
}
</style>
