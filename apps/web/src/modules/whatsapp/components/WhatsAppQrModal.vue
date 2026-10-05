<template>
  <q-dialog
    :model-value="modelValue"
    persistent
    @update:model-value="emit('update:modelValue', $event)"
  >
    <q-card style="width: 480px; max-width: 95vw" class="whatsapp-qr-card text-center q-pa-lg">
      <div class="row items-center justify-between q-mb-sm">
        <div class="text-subtitle1 text-weight-bold text-white">Vincular Dispositivo WhatsApp</div>
        <q-btn flat round dense icon="sym_r_close" color="grey-4" v-close-popup />
      </div>

      <!-- Selector de Método: QR vs Código de 8 Dígitos -->
      <q-tabs
        v-model="activeTab"
        dense
        no-caps
        active-color="teal-4"
        indicator-color="teal-4"
        class="text-grey-4 q-mb-md"
      >
        <q-tab name="qr" label="Escanear Código QR" icon="sym_r_qr_code_scanner" />
        <q-tab name="code" label="Código de 8 Dígitos" icon="sym_r_dialpad" />
      </q-tabs>

      <!-- TAB 1: CÓDIGO QR -->
      <div v-if="activeTab === 'qr'">
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
              <img
                v-if="isImageQr"
                :src="qrData!"
                alt="Código QR WhatsApp"
                style="width: 200px; height: 200px; object-fit: contain; display: block;"
              />
              <svg v-else width="180" height="180" viewBox="0 0 200 200" fill="none">
                <rect width="200" height="200" rx="8" fill="white" />
                <rect x="20" y="20" width="45" height="45" fill="#090d16" rx="4" />
                <rect x="28" y="28" width="29" height="29" fill="white" rx="2" />
                <rect x="34" y="34" width="17" height="17" fill="#10B981" rx="1" />
                <rect x="135" y="20" width="45" height="45" fill="#090d16" rx="4" />
                <rect x="143" y="28" width="29" height="29" fill="white" rx="2" />
                <rect x="149" y="34" width="17" height="17" fill="#10B981" rx="1" />
                <rect x="20" y="135" width="45" height="45" fill="#090d16" rx="4" />
                <rect x="28" y="143" width="29" height="29" fill="white" rx="2" />
                <rect x="34" y="149" width="17" height="17" fill="#10B981" rx="1" />
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
              <span>Actualización en <strong>{{ countdown }}s</strong></span>
            </div>
          </div>
        </div>
      </div>

      <!-- TAB 2: CÓDIGO DE 8 DÍGITOS (SIN CÁMARA) -->
      <div v-else class="q-py-sm">
        <p class="text-caption text-grey-4 text-left q-mb-sm">
          Si tu cámara o la red presentan fallas al escanear, usa la opción oficial <strong>«Vincular con el número de teléfono»</strong> que aparece abajo en WhatsApp:
        </p>

        <div class="row q-gutter-sm items-center q-mb-md">
          <q-input
            v-model="inputPhoneNumber"
            outlined
            dark
            dense
            placeholder="Ej: +59163921086"
            class="col"
            label="Número con código de país"
            :disable="isRequestingCode"
          >
            <template #prepend>
              <q-icon name="sym_r_phone" size="18px" color="teal-4" />
            </template>
          </q-input>

          <q-btn
            unelevated
            no-caps
            label="Obtener Código"
            icon="sym_r_key"
            color="teal-8"
            text-color="teal-1"
            :loading="isRequestingCode"
            @click="handleRequestPairingCode"
          />
        </div>

        <!-- Muestra del Código de 8 Dígitos -->
        <div v-if="pairingCodeResult" class="pairing-code-box q-pa-md q-mb-md">
          <div class="text-caption text-grey-4 q-mb-xs">Tu código de vinculación:</div>
          <div class="text-h4 text-bold text-teal-3 tracking-widest q-my-xs letter-spacing-lg">
            {{ formatPairingCode(pairingCodeResult) }}
          </div>
          <q-btn
            flat
            dense
            size="sm"
            icon="sym_r_content_copy"
            label="Copiar código"
            color="grey-4"
            class="q-mt-xs"
            @click="copyCode(pairingCodeResult)"
          />

          <div class="text-caption text-grey-4 text-left q-mt-md" style="font-size: 0.76rem; line-height: 1.4">
            1. En WhatsApp en tu celular ve a: <strong>Dispositivos vinculados</strong>.<br/>
            2. Toca <strong>Vincular un dispositivo</strong>.<br/>
            3. En la parte inferior presiona <strong>«Vincular con el número de teléfono»</strong>.<br/>
            4. Escribe el código de 8 letras/números mostrado arriba.
          </div>
        </div>
      </div>

      <!-- Botones de Acción -->
      <q-card-actions align="center" class="q-gutter-sm q-mt-sm">
        <q-btn
          flat
          dense
          no-caps
          label="Simular Escaneo (Modo Dev)"
          icon="sym_r_developer_mode"
          color="grey-4"
          size="sm"
          :loading="scanLoading"
          @click="emit('scan')"
        />
        <q-btn flat label="Cerrar" color="grey-4" no-caps v-close-popup />
      </q-card-actions>
    </q-card>
  </q-dialog>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { getWhatsAppPairingCode } from '../api/whatsapp.api'
import { useAppNotify } from '@/shared/composables/useAppNotify'

const props = defineProps<{
  modelValue: boolean
  accountId?: string
  qrData?: string | null
  loading?: boolean
  scanLoading?: boolean
}>()

const notify = useAppNotify()

const activeTab = ref<'qr' | 'code'>('qr')
const inputPhoneNumber = ref('')
const isRequestingCode = ref(false)
const pairingCodeResult = ref<string | null>(null)

const isImageQr = computed(() => {
  return typeof props.qrData === 'string' && (props.qrData.startsWith('data:image/') || props.qrData.startsWith('http'))
})

const emit = defineEmits<{
  (e: 'update:modelValue', val: boolean): void
  (e: 'scan'): void
  (e: 'refresh'): void
}>()

const countdown = ref(60)
let timer: any = null

async function handleRequestPairingCode() {
  if (!props.accountId) {
    notify.error({ message: 'No hay cuenta seleccionada para generar código.' })
    return
  }
  if (!inputPhoneNumber.value || inputPhoneNumber.value.replace(/[^0-9]/g, '').length < 8) {
    notify.error({ message: 'Por favor ingresa un número de teléfono válido con código de país (ej: +59163921086).' })
    return
  }

  isRequestingCode.value = true
  try {
    const code = await getWhatsAppPairingCode(props.accountId, inputPhoneNumber.value)
    pairingCodeResult.value = code
    notify.success({ message: '¡Código de 8 dígitos generado! Ingrésalo en tu celular.' })
  } catch (err: any) {
    notify.error({ message: err?.response?.data?.error || 'No se pudo generar el código. Verifica que el número sea correcto.' })
  } finally {
    isRequestingCode.value = false
  }
}

function formatPairingCode(code: string): string {
  const clean = code.replace(/[^a-zA-Z0-9]/g, '')
  if (clean.length === 8) {
    return `${clean.slice(0, 4)} - ${clean.slice(4)}`
  }
  return code
}

function copyCode(code: string) {
  const clean = code.replace(/[^a-zA-Z0-9]/g, '')
  navigator.clipboard.writeText(clean)
  notify.success({ message: `Código "${clean}" copiado al portapapeles.` })
}

watch(
  () => props.modelValue,
  (open) => {
    if (open) {
      countdown.value = 60
      pairingCodeResult.value = null
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

.pairing-code-box {
  background: rgba(16, 185, 129, 0.06);
  border: 1px solid rgba(16, 185, 129, 0.25);
  border-radius: 10px;
}

.letter-spacing-lg {
  letter-spacing: 0.18em;
}
</style>
