<template>
  <div class="xf-connections-page">
    <!-- Header de Conexiones -->
    <div class="row items-center justify-between q-mb-lg">
      <div>
        <h1 class="text-h5 text-bold q-my-none text-white">Conexiones & Canales</h1>
        <p class="text-caption text-grey-4 q-mt-xs q-mb-none">
          Gestiona las líneas telefónicas, fanpages y canales de mensajería integrados.
        </p>
      </div>
      <q-btn
        label="+ Agregar Conexión"
        unelevated
        no-caps
        class="xf-btn-primary"
        @click="isAddModalOpen = true"
      />
    </div>

    <!-- Grid de Conexiones -->
    <div class="row q-col-gutter-md">
      <div
        v-for="conn in connectionsList"
        :key="conn.id"
        class="col-12 col-sm-6 col-md-4"
      >
        <q-card flat bordered class="xf-conn-card">
          <q-card-section class="q-pb-sm">
            <div class="row items-start justify-between">
              <!-- Icono del Canal + Información -->
              <div class="row items-center q-gutter-x-md">
                <div
                  class="xf-conn-icon"
                  :class="`xf-conn-icon--${conn.type}`"
                >
                  <q-icon v-if="conn.type === 'facebook'" name="sym_r_public" size="24px" />
                  <q-icon v-else-if="conn.type === 'instagram'" name="sym_r_photo_camera" size="22px" />
                  <q-icon v-else-if="conn.type === 'whatsapp'" name="sym_r_chat" size="22px" />
                  <q-icon v-else-if="conn.type === 'telegram'" name="sym_r_send" size="22px" />
                  <q-icon v-else name="sym_r_language" size="22px" />
                </div>

                <div class="xf-conn-info">
                  <div class="xf-conn-name ellipsis">{{ conn.name }}</div>
                  <div class="xf-conn-id ellipsis">{{ conn.number_or_id }}</div>
                </div>
              </div>

              <!-- Menú de opciones ⋮ -->
              <q-btn flat round dense icon="sym_r_more_vert" color="grey-5" size="sm">
                <q-menu dark dense class="xf-menu">
                  <q-list>
                    <q-item clickable v-close-popup @click="editConnection(conn)">
                      <q-item-section avatar><q-icon name="sym_r_tune" size="16px" /></q-item-section>
                      <q-item-section>Ajustes de Canal</q-item-section>
                    </q-item>
                    <q-item clickable v-close-popup @click="toggleStatus(conn)">
                      <q-item-section avatar><q-icon name="sym_r_sync" size="16px" /></q-item-section>
                      <q-item-section>{{ conn.status === 'connected' ? 'Desconectar' : 'Reconectar' }}</q-item-section>
                    </q-item>
                    <q-item clickable v-close-popup class="text-negative" @click="deleteConnection(conn.id)">
                      <q-item-section avatar><q-icon name="sym_r_delete" size="16px" color="negative" /></q-item-section>
                      <q-item-section>Eliminar Conexión</q-item-section>
                    </q-item>
                  </q-list>
                </q-menu>
              </q-btn>
            </div>

            <!-- Badge de Estado Luminoso -->
            <div class="q-mt-sm">
              <span
                v-if="conn.status === 'connected'"
                class="xf-status-pill xf-status-pill--connected"
              >
                <span class="xf-pulse-dot"></span>
                <span>Conectada & Operativa</span>
              </span>
              <span
                v-else
                class="xf-status-pill xf-status-pill--disconnected"
              >
                <q-icon name="sym_r_close" size="14px" />
                <span>Desconectada</span>
              </span>
            </div>
          </q-card-section>

          <!-- Botón Editar inferior -->
          <div
            class="xf-conn-footer"
            @click="editConnection(conn)"
          >
            <span>Configurar Operadores</span>
            <q-icon name="sym_r_chevron_right" size="18px" />
          </div>
        </q-card>
      </div>
    </div>

    <!-- Modal "Agregar Conexión" -->
    <q-dialog v-model="isAddModalOpen">
      <q-card style="width: 820px; max-width: 95vw" class="xf-modal-card">
        <q-card-section class="row items-center justify-between q-pb-md">
          <div class="row items-center q-gutter-x-md">
            <div class="xf-modal-badge-icon">
              <q-icon name="sym_r_hub" size="22px" color="white" />
            </div>
            <div>
              <div class="text-h6 text-bold text-white">Integrar Nuevo Canal</div>
              <div class="text-caption text-grey-4">Selecciona el canal de mensajería para conectar tu bandeja.</div>
            </div>
          </div>
          <q-btn flat round dense icon="sym_r_close" color="grey-4" v-close-popup />
        </q-card-section>

        <!-- Grid de Canales Disponibles -->
        <q-card-section class="q-pt-none">
          <div class="row q-col-gutter-md">
            <!-- 1. WhatsApp QR Code -->
            <div class="col-12 col-sm-6">
              <div class="xf-channel-option" @click="selectChannel('qr')">
                <div class="xf-channel-option__icon xf-channel-option__icon--whatsapp">
                  <q-icon name="sym_r_qr_code_2" size="24px" />
                </div>
                <div class="xf-channel-option__text">
                  <div class="xf-channel-option__title">WhatsApp Web (QR)</div>
                  <div class="xf-channel-option__desc">Emparejamiento instantáneo mediante escaneo de código QR.</div>
                </div>
              </div>
            </div>

            <!-- 2. WhatsApp API Cloud -->
            <div class="col-12 col-sm-6">
              <div class="xf-channel-option" @click="selectChannel('cloud_api')">
                <div class="xf-channel-option__icon xf-channel-option__icon--whatsapp-cloud">
                  <q-icon name="sym_r_cloud" size="24px" />
                </div>
                <div class="xf-channel-option__text">
                  <div class="xf-channel-option__title">WhatsApp Cloud API (Meta)</div>
                  <div class="xf-channel-option__desc">API Oficial empresarial de alta velocidad con plantillas aprobadas.</div>
                </div>
              </div>
            </div>

            <!-- 3. Facebook Messenger -->
            <div class="col-12 col-sm-6">
              <div class="xf-channel-option" @click="selectChannel('facebook')">
                <div class="xf-channel-option__icon xf-channel-option__icon--facebook">
                  <q-icon name="sym_r_public" size="24px" />
                </div>
                <div class="xf-channel-option__text">
                  <div class="xf-channel-option__title">Facebook Messenger & Fanpages</div>
                  <div class="xf-channel-option__desc">Atención de mensajes directos y respuestas a comentarios.</div>
                </div>
              </div>
            </div>

            <!-- 4. Instagram Direct -->
            <div class="col-12 col-sm-6">
              <div class="xf-channel-option" @click="selectChannel('instagram')">
                <div class="xf-channel-option__icon xf-channel-option__icon--instagram">
                  <q-icon name="sym_r_photo_camera" size="24px" />
                </div>
                <div class="xf-channel-option__text">
                  <div class="xf-channel-option__title">Instagram Direct & Comentarios</div>
                  <div class="xf-channel-option__desc">Conversaciones privadas con seguidores y prospectos de Reels.</div>
                </div>
              </div>
            </div>

            <!-- 5. TikTok Direct -->
            <div class="col-12 col-sm-6">
              <div class="xf-channel-option" @click="selectChannel('tiktok')">
                <div class="xf-channel-option__icon xf-channel-option__icon--tiktok">
                  <q-icon name="sym_r_music_note" size="24px" />
                </div>
                <div class="xf-channel-option__text">
                  <div class="xf-channel-option__title">TikTok Business Messages</div>
                  <div class="xf-channel-option__desc">Respuestas automáticas a comentarios de videos y mensajes directos.</div>
                </div>
              </div>
            </div>

            <!-- 6. Telegram Bot -->
            <div class="col-12 col-sm-6">
              <div class="xf-channel-option" @click="selectChannel('telegram')">
                <div class="xf-channel-option__icon xf-channel-option__icon--telegram">
                  <q-icon name="sym_r_send" size="24px" />
                </div>
                <div class="xf-channel-option__text">
                  <div class="xf-channel-option__title">Telegram Bot</div>
                  <div class="xf-channel-option__desc">Canal de Telegram con tokens de BotFather.</div>
                </div>
              </div>
            </div>
          </div>
        </q-card-section>
      </q-card>
    </q-dialog>

    <!-- Modal Visor de Código QR en Vivo (Baileys / WhatsApp Web) -->
    <q-dialog v-model="isQrModalOpen">
      <q-card style="width: 460px; max-width: 95vw" class="xf-modal-card text-center q-pa-lg">
        <div class="text-h6 text-bold text-white">Escanea el Código QR</div>
        <div class="text-caption text-grey-4 q-mt-xs">
          Abre WhatsApp en tu teléfono > Dispositivos vinculados > Vincular un dispositivo.
        </div>

        <!-- Contenedor del Código QR Animado -->
        <div class="xf-qr-container q-my-lg">
          <div v-if="qrLoading" class="row items-center justify-center" style="height: 220px">
            <q-spinner-dots size="48px" color="teal-4" />
          </div>
          <div v-else class="column items-center">
            <!-- Renderizado de QR Visual con marco -->
            <div class="xf-qr-frame">
              <svg width="190" height="190" viewBox="0 0 200 200" fill="none">
                <rect width="200" height="200" rx="12" fill="white" />
                <!-- Marcadores de esquina del QR -->
                <rect x="20" y="20" width="45" height="45" fill="#080c14" rx="6" />
                <rect x="28" y="28" width="29" height="29" fill="white" rx="4" />
                <rect x="34" y="34" width="17" height="17" fill="#10B981" rx="2" />

                <rect x="135" y="20" width="45" height="45" fill="#080c14" rx="6" />
                <rect x="143" y="28" width="29" height="29" fill="white" rx="4" />
                <rect x="149" y="34" width="17" height="17" fill="#10B981" rx="2" />

                <rect x="20" y="135" width="45" height="45" fill="#080c14" rx="6" />
                <rect x="28" y="143" width="29" height="29" fill="white" rx="4" />
                <rect x="34" y="149" width="17" height="17" fill="#10B981" rx="2" />

                <!-- Módulos de datos simulados -->
                <circle cx="85" cy="30" r="4" fill="#080c14" />
                <circle cx="105" cy="30" r="4" fill="#080c14" />
                <circle cx="95" cy="50" r="4" fill="#080c14" />
                <circle cx="115" cy="50" r="4" fill="#080c14" />
                <circle cx="85" cy="70" r="4" fill="#080c14" />
                <circle cx="105" cy="85" r="4" fill="#080c14" />
                <circle cx="140" cy="85" r="4" fill="#080c14" />
                <circle cx="160" cy="100" r="4" fill="#080c14" />
                <circle cx="40" cy="95" r="4" fill="#080c14" />
                <circle cx="60" cy="115" r="4" fill="#080c14" />
                <circle cx="85" cy="140" r="4" fill="#080c14" />
                <circle cx="105" cy="160" r="4" fill="#080c14" />
                <circle cx="145" cy="145" r="4" fill="#080c14" />
                <circle cx="165" cy="165" r="4" fill="#080c14" />
              </svg>
            </div>
            <div class="row items-center q-gutter-x-xs text-caption text-grey-4 q-mt-sm">
              <q-icon name="sym_r_timer" size="14px" color="teal-4" />
              <span>Expira en <strong>{{ qrTimeLeft }}s</strong></span>
            </div>
          </div>
        </div>

        <q-card-actions align="center" class="q-gutter-sm">
          <q-btn
            unelevated
            label="Simular Escaneo Exitoso"
            class="xf-btn-primary"
            no-caps
            @click="simulateScanSuccess"
          />
          <q-btn flat label="Cerrar" color="grey-4" no-caps v-close-popup />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { http } from '@/shared/api/http'
import { useAppNotify } from '@/shared/composables/useAppNotify'

const notify = useAppNotify()

interface ConnectionItem {
  id: string
  name: string
  number_or_id: string
  type: 'whatsapp' | 'facebook' | 'instagram' | 'telegram' | 'tiktok'
  status: 'connected' | 'disconnected'
}

const isAddModalOpen = ref(false)

const connectionsList = ref<ConnectionItem[]>([
  {
    id: '1',
    name: 'WhatsApp Oficial UNITEPC',
    number_or_id: '+591 4 4252525',
    type: 'whatsapp',
    status: 'connected',
  },
  {
    id: '2',
    name: 'Facebook Fanpage UNITEPC Bolivia',
    number_or_id: 'ID: 109283746192837',
    type: 'facebook',
    status: 'connected',
  },
  {
    id: '3',
    name: 'Instagram Direct @unitepc_oficial',
    number_or_id: '@unitepc_oficial',
    type: 'instagram',
    status: 'connected',
  },
])

onMounted(async () => {
  try {
    const res = await http.get('/whatsapp-accounts')
    if (res.data?.data && res.data.data.length > 0) {
      connectionsList.value = res.data.data.map((w: any) => ({
        id: w.id,
        name: w.name,
        number_or_id: w.phone_number || w.account_id || 'ID Activa',
        type: 'whatsapp',
        status: w.status === 'connected' ? 'connected' : 'disconnected',
      }))
    }
  } catch {
    // Mantener datos locales
  }
})

const isQrModalOpen = ref(false)
const qrLoading = ref(false)
const qrTimeLeft = ref(60)
const selectedAccountId = ref<string>('1')

function selectChannel(channelKey: string) {
  isAddModalOpen.value = false
  if (channelKey === 'qr') {
    openQrModal('1')
  } else {
    notify.info({ message: `Configurando integración para ${channelKey.toUpperCase()}...` })
  }
}

function openQrModal(accountId: string) {
  selectedAccountId.value = accountId
  isQrModalOpen.value = true
  qrLoading.value = true
  qrTimeLeft.value = 60

  setTimeout(() => {
    qrLoading.value = false
  }, 600)
}

async function simulateScanSuccess() {
  try {
    await http.post(`/whatsapp/accounts/${selectedAccountId.value}/simulate-scan`, {
      phone_number: '+591 79707297',
    })
    const target = connectionsList.value.find((c) => c.id === selectedAccountId.value)
    if (target) {
      target.status = 'connected'
      target.number_or_id = '+591 79707297'
    }
    notify.success({ message: '¡WhatsApp vinculado exitosamente!' })
  } catch {
    const target = connectionsList.value.find((c) => c.id === selectedAccountId.value)
    if (target) {
      target.status = 'connected'
      target.number_or_id = '+591 79707297'
    }
    notify.success({ message: '¡WhatsApp vinculado exitosamente!' })
  }
  isQrModalOpen.value = false
}

function editConnection(conn: ConnectionItem) {
  if (conn.type === 'whatsapp' && conn.status === 'disconnected') {
    openQrModal(conn.id)
  } else {
    notify.info({ message: `Ajustes y asignación de operadores para: ${conn.name}` })
  }
}

function toggleStatus(conn: ConnectionItem) {
  conn.status = conn.status === 'connected' ? 'disconnected' : 'connected'
  notify.success({ message: `Estado de ${conn.name} actualizado.` })
}

function deleteConnection(id: string) {
  connectionsList.value = connectionsList.value.filter((c) => c.id !== id)
  notify.warning({ message: 'Conexión eliminada.' })
}
</script>

<style scoped lang="scss">
.xf-connections-page {
  padding: 28px 36px;
  background-color: var(--crm-bg-app);
  min-height: calc(100vh - 56px);
}

.xf-conn-card {
  background-color: var(--crm-bg-card) !important;
  border: 1px solid var(--crm-color-border) !important;
  border-radius: 14px !important;
  overflow: hidden;
  transition: all var(--crm-transition);

  &:hover {
    border-color: var(--crm-color-border-hover) !important;
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.35) !important;
  }
}

.xf-conn-icon {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #ffffff;

  &--whatsapp {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    box-shadow: 0 2px 10px rgba(16, 185, 129, 0.3);
  }

  &--facebook {
    background: linear-gradient(135deg, #1877f2 0%, #0d5cb6 100%);
    box-shadow: 0 2px 10px rgba(24, 119, 242, 0.3);
  }

  &--instagram {
    background: linear-gradient(135deg, #e1306c 0%, #fd1d1d 50%, #f56040 100%);
    box-shadow: 0 2px 10px rgba(225, 48, 108, 0.3);
  }

  &--telegram {
    background: linear-gradient(135deg, #229ed9 0%, #1579a8 100%);
    box-shadow: 0 2px 10px rgba(34, 158, 217, 0.3);
  }

  &--tiktok {
    background: linear-gradient(135deg, #000000 0%, #25f4ee 50%, #fe2c55 100%);
    box-shadow: 0 2px 10px rgba(254, 44, 85, 0.3);
  }
}

.xf-conn-info {
  flex: 1;
  min-width: 0;
}

.xf-conn-name {
  font-size: 0.95rem;
  font-weight: 700;
  color: var(--crm-color-ink);
}

.xf-conn-id {
  font-size: 0.78rem;
  color: var(--crm-color-muted);
  font-family: var(--crm-font-mono);
}

.xf-status-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 0.72rem;
  font-weight: 700;
  padding: 3px 10px;
  border-radius: 12px;

  &--connected {
    background: rgba(16, 185, 129, 0.15);
    color: #10b981;
    border: 1px solid rgba(16, 185, 129, 0.3);
  }

  &--disconnected {
    background: rgba(239, 68, 68, 0.15);
    color: #ef4444;
    border: 1px solid rgba(239, 68, 68, 0.3);
  }
}

.xf-pulse-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background-color: #10b981;
  box-shadow: 0 0 8px rgba(16, 185, 129, 0.8);
}

.xf-conn-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 16px;
  background: rgba(255, 255, 255, 0.02);
  border-top: 1px solid var(--crm-color-border);
  color: var(--crm-color-muted);
  font-size: 0.82rem;
  font-weight: 600;
  cursor: pointer;
  transition: all var(--crm-transition);

  &:hover {
    background: var(--crm-bg-card-hover);
    color: var(--crm-color-primary);
  }
}

.xf-modal-card {
  background-color: #0f172a !important;
  border: 1px solid var(--crm-color-border) !important;
  border-radius: 18px !important;
  padding: 12px;
}

.xf-modal-badge-icon {
  width: 40px;
  height: 40px;
  border-radius: 10px;
  background: linear-gradient(135deg, #10b981 0%, #06b6d4 100%);
  display: flex;
  align-items: center;
  justify-content: center;
}

.xf-channel-option {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 14px;
  background: var(--crm-bg-card);
  border: 1px solid var(--crm-color-border);
  border-radius: 12px;
  cursor: pointer;
  transition: all var(--crm-transition);

  &:hover {
    background: var(--crm-bg-card-hover);
    border-color: rgba(16, 185, 129, 0.4);
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
  }

  &__icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #ffffff;
    flex-shrink: 0;

    &--whatsapp { background: linear-gradient(135deg, #10b981 0%, #059669 100%); }
    &--whatsapp-cloud { background: linear-gradient(135deg, #06b6d4 0%, #0284c7 100%); }
    &--facebook { background: linear-gradient(135deg, #1877f2 0%, #0d5cb6 100%); }
    &--instagram { background: linear-gradient(135deg, #e1306c 0%, #fd1d1d 50%, #f56040 100%); }
    &--tiktok { background: linear-gradient(135deg, #000000 0%, #25f4ee 50%, #fe2c55 100%); }
    &--telegram { background: linear-gradient(135deg, #229ed9 0%, #1579a8 100%); }
  }

  &__text {
    flex: 1;
  }

  &__title {
    font-size: 0.92rem;
    font-weight: 700;
    color: var(--crm-color-ink);
  }

  &__desc {
    font-size: 0.76rem;
    color: var(--crm-color-muted);
    margin-top: 2px;
  }
}

.xf-menu {
  background-color: var(--crm-bg-card) !important;
  border: 1px solid var(--crm-color-border) !important;
  border-radius: 10px !important;
}

.xf-qr-frame {
  padding: 12px;
  background: #ffffff;
  border-radius: 16px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4), 0 0 25px rgba(16, 185, 129, 0.25);
  display: inline-flex;
}
</style>
