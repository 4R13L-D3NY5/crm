<template>
  <div class="whatsapp-settings-page">
    <!-- Header Minimalista de Conexiones -->
    <div class="row items-center justify-between q-mb-lg">
      <div>
        <div class="row items-center q-gutter-x-sm">
          <h1 class="text-h5 text-bold text-white q-my-none">Conexiones & Canales Multicanal</h1>
          <q-badge color="teal-9" text-color="teal-2" rounded class="q-px-sm">
            {{ accounts.length }} canales
          </q-badge>
        </div>
        <p class="text-caption text-grey-4 q-mt-xs q-mb-none">
          Vinculación de WhatsApp (Web QR y Cloud API), Facebook Fanpage, Instagram Direct y TikTok Business.
        </p>
      </div>

      <div class="row items-center q-gutter-sm">
        <q-btn
          unelevated
          no-caps
          label="+ Vincular Nuevo Canal"
          icon="sym_r_add"
          class="xf-btn-primary"
          @click="isCreateModalOpen = true"
        />
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="accountsQuery.isLoading.value" class="row justify-center q-py-xl">
      <q-spinner-dots size="40px" color="primary" />
    </div>

    <!-- Empty State -->
    <div
      v-else-if="accounts.length === 0"
      class="column items-center justify-center q-pa-xl text-center empty-channels-box"
    >
      <div class="empty-icon-wrap q-mb-md">
        <q-icon name="sym_r_hub" size="34px" color="teal-4" />
      </div>
      <div class="text-subtitle1 text-white text-bold">No tienes canales configurados aún</div>
      <p class="text-caption text-grey-4 q-mt-xs text-center" style="max-width: 440px">
        Conecta una línea de WhatsApp (QR o Cloud API), tu Fanpage de Facebook, cuenta de Instagram o TikTok para centralizar la atención en la bandeja.
      </p>
      <q-btn
        unelevated
        no-caps
        label="Vincular mi Primer Canal"
        icon="sym_r_add"
        class="xf-btn-primary q-mt-sm"
        @click="isCreateModalOpen = true"
      />
    </div>

    <!-- Grid de Canales -->
    <div v-else class="row q-col-gutter-md">
      <div
        v-for="acc in accounts"
        :key="acc.id"
        class="col-12 col-sm-6 col-md-4"
      >
        <WhatsAppChannelCard
          :account="acc"
          @connect="openConnectQr(acc)"
          @disconnect="handleDisconnect(acc.id)"
          @simulate="openSimulateModal(acc)"
          @delete="handleDelete(acc.id)"
        />
      </div>
    </div>

    <!-- Modales Dumb Desacoplados -->
    <WhatsAppQrModal
      v-model="isQrModalOpen"
      :account-id="activeAccount?.id"
      :default-phone="activeAccount?.display_phone_number"
      :qr-data="currentQrCode"
      :loading="qrLoading"
      :scan-loading="mutations.simulateScanMutation.isPending.value"
      @scan="handleSimulateScan"
      @refresh="loadQrCode"
    />

    <WhatsAppFormDialog
      v-model="isCreateModalOpen"
      :loading="mutations.createAccountMutation.isPending.value"
      @submit="handleCreateAccount"
    />

    <WhatsAppSimulateModal
      v-model="isSimulateModalOpen"
      :account="activeAccount"
      :loading="mutations.simulateIncomingMutation.isPending.value"
      @submit="handleSimulateIncoming"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, onUnmounted, ref, watch } from 'vue'
import { useAppNotify } from '@/shared/composables/useAppNotify'
import {
  useWhatsAppAccounts,
  useWhatsAppMutations,
} from '../composables/useWhatsApp'
import { getWhatsAppQr } from '../api/whatsapp.api'
import type {
  CreateWhatsAppAccountPayload,
  SimulateIncomingPayload,
  WhatsAppAccount,
} from '../types/whatsapp.types'

import WhatsAppChannelCard from '../components/WhatsAppChannelCard.vue'
import WhatsAppQrModal from '../components/WhatsAppQrModal.vue'
import WhatsAppFormDialog from '../components/WhatsAppFormDialog.vue'
import WhatsAppSimulateModal from '../components/WhatsAppSimulateModal.vue'

const notify = useAppNotify()
const accountsQuery = useWhatsAppAccounts()
const mutations = useWhatsAppMutations()

const accounts = computed(() => accountsQuery.data.value ?? [])

// Modales
const isCreateModalOpen = ref(false)
const isQrModalOpen = ref(false)
const isSimulateModalOpen = ref(false)

const activeAccount = ref<WhatsAppAccount | null>(null)
const qrLoading = ref(false)
const currentQrCode = ref<string | null>(null)
let qrPollTimer: any = null

function stopQrPolling() {
  if (qrPollTimer) {
    clearInterval(qrPollTimer)
    qrPollTimer = null
  }
}

function startQrPolling() {
  stopQrPolling()
  qrPollTimer = setInterval(async () => {
    if (!isQrModalOpen.value || !activeAccount.value) {
      stopQrPolling()
      return
    }
    try {
      const res = await getWhatsAppQr(activeAccount.value.id)
      currentQrCode.value = res.qrcode_raw || null
      if (res.status === 'CONNECTED') {
        stopQrPolling()
        isQrModalOpen.value = false
        notify.success({ message: '¡Dispositivo WhatsApp vinculado y conectado exitosamente!' })
        await accountsQuery.refetch()
      }
    } catch {
      // ignorar error transitorio de polling
    }
  }, 3500)
}

watch(isQrModalOpen, (open) => {
  if (!open) {
    stopQrPolling()
  }
})

onUnmounted(() => {
  stopQrPolling()
})

async function handleCreateAccount(payload: CreateWhatsAppAccountPayload) {
  try {
    const newAcc = await mutations.createAccountMutation.mutateAsync(payload)
    notify.success({ message: `Línea "${newAcc.name}" registrada con éxito.` })
    isCreateModalOpen.value = false
    // Abrir de inmediato el QR si es tipo QR
    if (newAcc.session_type === 'baileys_qr') {
      openConnectQr(newAcc)
    }
  } catch {
    notify.error({ message: 'No se pudo crear la línea de WhatsApp.' })
  }
}

async function openConnectQr(acc: WhatsAppAccount) {
  activeAccount.value = acc
  isQrModalOpen.value = true
  await loadQrCode()
  startQrPolling()
}

async function loadQrCode() {
  if (!activeAccount.value) return
  qrLoading.value = true
  try {
    const res = await getWhatsAppQr(activeAccount.value.id)
    currentQrCode.value = res.qrcode_raw || null
    if (res.status === 'CONNECTED') {
      stopQrPolling()
      isQrModalOpen.value = false
      notify.success({ message: '¡Dispositivo WhatsApp ya está conectado!' })
    }
    await accountsQuery.refetch()
  } catch {
    notify.error({ message: 'Error al generar el código QR.' })
  } finally {
    qrLoading.value = false
  }
}

async function handleSimulateScan() {
  if (!activeAccount.value) return
  try {
    await mutations.simulateScanMutation.mutateAsync({
      id: activeAccount.value.id,
      phone: activeAccount.value.display_phone_number || '+59144252525',
    })
    notify.success({ message: '¡Dispositivo vinculado y conectado exitosamente!' })
    isQrModalOpen.value = false
  } catch {
    notify.error({ message: 'Error durante la simulación de escaneo.' })
  }
}

async function handleDisconnect(id: string) {
  try {
    await mutations.disconnectMutation.mutateAsync(id)
    notify.info({ message: 'Línea desconectada.' })
  } catch {
    notify.error({ message: 'Error al desconectar.' })
  }
}

async function handleDelete(id: string) {
  try {
    await mutations.deleteAccountMutation.mutateAsync(id)
    notify.success({ message: 'Línea de WhatsApp eliminada.' })
  } catch {
    notify.error({ message: 'Error al eliminar la línea.' })
  }
}

function openSimulateModal(acc: WhatsAppAccount) {
  activeAccount.value = acc
  isSimulateModalOpen.value = true
}

async function handleSimulateIncoming(payload: SimulateIncomingPayload) {
  if (!activeAccount.value) return
  try {
    await mutations.simulateIncomingMutation.mutateAsync({
      id: activeAccount.value.id,
      payload,
    })
    notify.success({ message: `Mensaje simulado recibido de ${payload.from_name || payload.from_phone}` })
    isSimulateModalOpen.value = false
  } catch {
    notify.error({ message: 'Error al inyectar mensaje simulado.' })
  }
}
</script>

<style scoped lang="scss">
.whatsapp-settings-page {
  padding: 28px 36px;
  background-color: var(--crm-bg-app);
  min-height: calc(100vh - 56px);
}

.empty-channels-box {
  background: var(--crm-bg-card);
  border: 1px dashed var(--crm-color-border-hover);
  border-radius: var(--crm-radius-card);
  max-width: 600px;
  margin: 40px auto 0;
}

.empty-icon-wrap {
  width: 56px;
  height: 56px;
  border-radius: 12px;
  background: var(--crm-color-primary-soft);
  display: flex;
  align-items: center;
  justify-content: center;
  border: 1px solid rgba(16, 185, 129, 0.2);
}
</style>
