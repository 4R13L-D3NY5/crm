<template>
  <q-card flat bordered class="whatsapp-channel-card">
    <q-card-section class="q-pb-sm">
      <div class="row items-start justify-between">
        <!-- Icono y Datos de Línea / Canal -->
        <div class="row items-center q-gutter-x-md">
          <div
            class="channel-icon"
            :class="[
              `channel-icon--${platformMeta.themeClass}`,
              `channel-icon--${account.status.toLowerCase()}`,
            ]"
          >
            <q-icon :name="platformMeta.icon" size="22px" :style="{ color: platformMeta.color }" />
          </div>

          <div>
            <div class="row items-center q-gutter-x-xs">
              <span class="channel-name ellipsis text-weight-bold text-white">
                {{ account.name }}
              </span>
              <span class="platform-badge" :style="{ borderColor: platformMeta.color + '44', color: platformMeta.color }">
                {{ platformMeta.label }}
              </span>
            </div>
            <div class="channel-number text-caption text-grey-4">
              {{ channelDisplayIdentifier }}
            </div>
          </div>
        </div>

        <!-- Menú de Opciones -->
        <q-btn flat round dense icon="sym_r_more_vert" color="grey-5" size="sm">
          <q-menu dark dense class="channel-menu">
            <q-list>
              <q-item
                clickable
                v-close-popup
                @click="showCredentialsDialog = true"
              >
                <q-item-section avatar><q-icon name="sym_r_key" size="16px" color="teal-4" /></q-item-section>
                <q-item-section>Ver Webhook & Tokens</q-item-section>
              </q-item>

              <q-item
                v-if="account.session_type === 'facebook'"
                clickable
                v-close-popup
                class="text-blue-4"
                @click="handleSyncFacebook"
              >
                <q-item-section avatar><q-icon name="sym_r_sync" size="16px" color="blue-4" /></q-item-section>
                <q-item-section>Sincronizar Mensajes</q-item-section>
              </q-item>

              <q-item
                v-if="account.session_type === 'instagram'"
                clickable
                v-close-popup
                class="text-pink-4"
                @click="handleSyncInstagram"
              >
                <q-item-section avatar><q-icon name="sym_r_sync" size="16px" color="pink-4" /></q-item-section>
                <q-item-section>Sincronizar DMs Instagram</q-item-section>
              </q-item>


              <q-item
                clickable
                v-close-popup
                @click="emit('simulate', account)"
              >
                <q-item-section avatar><q-icon name="sym_r_send" size="16px" color="primary" /></q-item-section>
                <q-item-section>Simular Mensaje Entrante</q-item-section>
              </q-item>

              <q-separator dark class="q-my-xs" />

              <q-item
                v-if="account.status === 'CONNECTED'"
                clickable
                v-close-popup
                class="text-amber-4"
                @click="emit('disconnect', account.id)"
              >
                <q-item-section avatar><q-icon name="sym_r_link_off" size="16px" /></q-item-section>
                <q-item-section>Desconectar</q-item-section>
              </q-item>
              <q-item
                v-else-if="account.session_type === 'baileys_qr'"
                clickable
                v-close-popup
                class="text-teal-4"
                @click="emit('connect', account)"
              >
                <q-item-section avatar><q-icon name="sym_r_qr_code" size="16px" /></q-item-section>
                <q-item-section>Conectar vía QR</q-item-section>
              </q-item>

              <q-separator dark class="q-my-xs" />
              <q-item
                clickable
                v-close-popup
                class="text-negative"
                @click="emit('delete', account.id)"
              >
                <q-item-section avatar><q-icon name="sym_r_delete" size="16px" color="negative" /></q-item-section>
                <q-item-section>Eliminar Canal</q-item-section>
              </q-item>
            </q-list>
          </q-menu>
        </q-btn>
      </div>

      <!-- Badge de Estado Minimalista -->
      <div class="row items-center justify-between q-mt-md">
        <div class="status-pill" :class="`status-pill--${account.status.toLowerCase()}`">
          <span class="status-dot"></span>
          <span>{{ statusLabel }}</span>
        </div>

        <div v-if="account.last_connected_at" class="text-caption text-grey-5" style="font-size: 0.7rem">
          {{ formatDate(account.last_connected_at) }}
        </div>
      </div>
    </q-card-section>

    <!-- Footer con Acciones Rápidas -->
    <q-separator dark class="q-mt-sm" style="border-color: var(--crm-color-border)" />
    <q-card-actions align="between" class="q-px-md q-py-sm">
      <div class="row items-center q-gutter-x-xs">
        <q-btn
          v-if="account.session_type === 'facebook'"
          flat
          dense
          size="sm"
          no-caps
          icon="sym_r_sync"
          label="Sincronizar"
          color="blue-4"
          :loading="isSyncing"
          @click="handleSyncFacebook"
        />
        <q-btn
          v-if="account.session_type === 'instagram'"
          flat
          dense
          size="sm"
          no-caps
          icon="sym_r_sync"
          label="Sincronizar"
          color="pink-4"
          :loading="isSyncing"
          @click="handleSyncInstagram"
        />
        <q-btn
          flat
          dense
          size="sm"
          no-caps
          icon="sym_r_send"
          label="Simular Entrada"
          color="teal-4"
          @click="emit('simulate', account)"
        />
        <q-btn
          flat
          dense
          size="sm"
          no-caps
          icon="sym_r_key"
          label="Webhook"
          color="grey-4"
          @click="showCredentialsDialog = true"
        />
      </div>

      <div>
        <q-btn
          v-if="account.status === 'CONNECTED'"
          flat
          dense
          size="sm"
          no-caps
          icon="sym_r_link_off"
          label="Desconectar"
          color="grey-5"
          @click="emit('disconnect', account.id)"
        />
        <q-btn
          v-else-if="account.session_type === 'baileys_qr'"
          unelevated
          dense
          size="sm"
          no-caps
          icon="sym_r_qr_code_scanner"
          label="Escanear QR"
          class="xf-btn-primary q-px-sm"
          @click="emit('connect', account)"
        />
        <q-btn
          v-else
          flat
          dense
          size="sm"
          no-caps
          icon="sym_r_check_circle"
          label="Configurado"
          color="teal-4"
          @click="showCredentialsDialog = true"
        />
      </div>
    </q-card-actions>

    <!-- Modal de Credenciales & Webhook del Canal -->
    <q-dialog v-model="showCredentialsDialog">
      <q-card style="width: 520px; max-width: 95vw" class="credentials-card">
        <q-card-section class="row items-center justify-between q-pb-none">
          <div class="row items-center q-gutter-x-sm">
            <q-avatar size="28px" :style="{ background: platformMeta.color + '22' }">
              <q-icon :name="platformMeta.icon" size="16px" :style="{ color: platformMeta.color }" />
            </q-avatar>
            <div>
              <div class="text-subtitle2 text-weight-bold text-white">
                Configuración Técnica: {{ account.name }}
              </div>
              <div class="text-caption text-grey-4">{{ platformMeta.label }}</div>
            </div>
          </div>
          <q-btn flat round dense icon="sym_r_close" color="grey-4" v-close-popup />
        </q-card-section>

        <q-card-section class="q-pt-md q-gutter-y-sm">
          <!-- Webhook Callback URL -->
          <div>
            <div class="row items-center justify-between q-mb-xs">
              <label class="text-caption text-grey-4">URL de Callback (Webhook)</label>
              <q-btn
                flat
                dense
                size="xs"
                no-caps
                color="teal-4"
                icon="sym_r_content_copy"
                label="Copiar"
                @click="copyToClipboard(webhookUrl)"
              />
            </div>
            <q-input
              :model-value="webhookUrl"
              readonly
              outlined
              dark
              dense
              class="xf-code-input"
            />
            <div class="text-caption text-grey-5 q-mt-xs" style="font-size: 0.72rem">
              Pega esta URL en el portal de desarrolladores para recibir mensajes en tiempo real.
            </div>
          </div>

          <!-- Token de Verificación (Verify Token) -->
          <div>
            <div class="row items-center justify-between q-mb-xs">
              <label class="text-caption text-grey-4">Token de Verificación (Verify Token)</label>
              <q-btn
                flat
                dense
                size="xs"
                no-caps
                color="teal-4"
                icon="sym_r_content_copy"
                label="Copiar"
                @click="copyToClipboard(account.verify_token || 'xpertiflow_secret_token')"
              />
            </div>
            <q-input
              :model-value="account.verify_token || 'xpertiflow_secret_token'"
              readonly
              outlined
              dark
              dense
              class="xf-code-input"
            />
          </div>

          <!-- Identificador de Canal (Phone Number ID o Page ID) -->
          <div v-if="account.phone_number_id">
            <div class="row items-center justify-between q-mb-xs">
              <label class="text-caption text-grey-4">Identificador (Page / Phone ID)</label>
              <q-btn
                flat
                dense
                size="xs"
                no-caps
                color="teal-4"
                icon="sym_r_content_copy"
                label="Copiar"
                @click="copyToClipboard(account.phone_number_id)"
              />
            </div>
            <q-input
              :model-value="account.phone_number_id"
              readonly
              outlined
              dark
              dense
              class="xf-code-input"
            />
          </div>

          <!-- Business ID -->
          <div v-if="account.business_account_id">
            <div class="row items-center justify-between q-mb-xs">
              <label class="text-caption text-grey-4">Business Account / App Key</label>
              <q-btn
                flat
                dense
                size="xs"
                no-caps
                color="teal-4"
                icon="sym_r_content_copy"
                label="Copiar"
                @click="copyToClipboard(account.business_account_id)"
              />
            </div>
            <q-input
              :model-value="account.business_account_id"
              readonly
              outlined
              dark
              dense
              class="xf-code-input"
            />
          </div>

          <!-- Tip de Configuración -->
          <div class="instruction-box q-pa-sm q-mt-sm">
            <q-icon name="sym_r_info" size="16px" color="teal-4" class="q-mr-xs" />
            <span class="text-caption text-grey-4">
              {{ platformMeta.instructions }}
            </span>
          </div>
        </q-card-section>

        <q-card-actions align="right" class="q-px-md q-py-sm">
          <q-btn flat label="Entendido" color="teal-4" no-caps v-close-popup />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-card>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useQuasar } from 'quasar'
import { useQueryClient } from '@tanstack/vue-query'
import { syncFacebookMessages, syncInstagramMessages } from '../api/whatsapp.api'
import type { WhatsAppAccount } from '../types/whatsapp.types'

const props = defineProps<{
  account: WhatsAppAccount
}>()

const emit = defineEmits<{
  (e: 'connect', account: WhatsAppAccount): void
  (e: 'disconnect', id: string): void
  (e: 'simulate', account: WhatsAppAccount): void
  (e: 'delete', id: string): void
}>()

const $q = useQuasar()
const showCredentialsDialog = ref(false)

const platformMeta = computed(() => {
  switch (props.account.session_type) {
    case 'meta_cloud':
      return {
        label: 'Meta Cloud API',
        icon: 'sym_r_cloud',
        color: '#10b981',
        themeClass: 'whatsapp',
        instructions: 'En Meta Developers > WhatsApp > Configuración: ingresa la URL de Callback, el Token de Verificación y suscríbete al campo "messages".',
      }
    case 'facebook':
      return {
        label: 'Facebook Fanpage',
        icon: 'sym_r_public',
        color: '#1877f2',
        themeClass: 'facebook',
        instructions: 'En Meta Developers > Webhooks > Page: suscríbete a los campos "messages", "messaging_postbacks" y "feed" para capturar comentarios.',
      }
    case 'instagram':
      return {
        label: 'Instagram Direct',
        icon: 'sym_r_photo_camera',
        color: '#e1306c',
        themeClass: 'instagram',
        instructions: 'En Meta Developers > Webhooks > Instagram: suscríbete a "messages", "comments" y "mentions". Asegúrate que la cuenta IG esté vinculada a tu Fanpage.',
      }
    case 'tiktok':
      return {
        label: 'TikTok Business',
        icon: 'sym_r_music_note',
        color: '#25f4ee',
        themeClass: 'tiktok',
        instructions: 'En TikTok for Business Developers > Webhook Subscription: registra el endpoint de webhook y suscríbete a eventos de interacción y mensajería.',
      }
    case 'baileys_qr':
    default:
      return {
        label: 'WhatsApp (QR)',
        icon: 'sym_r_qr_code',
        color: '#10b981',
        themeClass: 'whatsapp',
        instructions: 'Vinculación directa mediante socket Baileys. Escanea el código QR desde Dispositivos Vinculados en tu celular.',
      }
  }
})

const channelDisplayIdentifier = computed(() => {
  if (props.account.display_phone_number) {
    return props.account.display_phone_number
  }
  if (props.account.phone_number_id) {
    return `ID: ${props.account.phone_number_id}`
  }
  return props.account.session_type === 'baileys_qr' ? 'Escanea para vincular' : 'Sin identificador'
})

const webhookUrl = computed(() => {
  if (props.account.session_type === 'meta_cloud') {
    return props.account.webhook_url || `${window.location.origin}/api/whatsapp/webhook`
  }
  return props.account.social_webhook_url || `${window.location.origin}/api/social/comments/webhook`
})

const statusLabel = computed(() => {
  switch (props.account.status) {
    case 'CONNECTED':
      return 'Canal Conectado'
    case 'CONNECTING':
      return 'Sincronizando...'
    case 'DISCONNECTED':
    default:
      return 'Desconectado'
  }
})

function copyToClipboard(text: string) {
  if (!text) return
  navigator.clipboard.writeText(text).then(() => {
    $q.notify({
      type: 'positive',
      message: 'Copiado al portapapeles',
      position: 'bottom-right',
      timeout: 1800,
    })
  })
}

function formatDate(dateStr: string) {
  try {
    const d = new Date(dateStr)
    return `Activo: ${d.toLocaleDateString('es-ES', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })}`
  } catch {
    return ''
  }
}

const queryClient = useQueryClient()
const isSyncing = ref(false)

async function handleSyncFacebook() {
  isSyncing.value = true
  try {
    const res = await syncFacebookMessages(props.account.id)
    await queryClient.invalidateQueries({ queryKey: ['conversations'] })
    await queryClient.invalidateQueries({ queryKey: ['whatsapp-accounts'] })
    $q.notify({
      type: 'positive',
      message: res.message || 'Mensajes sincronizados correctamente con Facebook.',
      position: 'bottom-right',
      timeout: 3000,
    })
  } catch (err: any) {
    $q.notify({
      type: 'negative',
      message: err?.response?.data?.message || 'Error al sincronizar con Facebook',
      position: 'bottom-right',
      timeout: 4000,
    })
  } finally {
    isSyncing.value = false
  }
}

async function handleSyncInstagram() {
  isSyncing.value = true
  try {
    const res = await syncInstagramMessages(props.account.id)
    await queryClient.invalidateQueries({ queryKey: ['conversations'] })
    await queryClient.invalidateQueries({ queryKey: ['whatsapp-accounts'] })
    $q.notify({
      type: 'positive',
      message: res.message || 'Mensajes sincronizados correctamente con Instagram Direct.',
      position: 'bottom-right',
      timeout: 3000,
    })
  } catch (err: any) {
    $q.notify({
      type: 'negative',
      message: err?.response?.data?.message || 'Error al sincronizar con Instagram',
      position: 'bottom-right',
      timeout: 4000,
    })
  } finally {
    isSyncing.value = false
  }
}
</script>

<style scoped lang="scss">
.whatsapp-channel-card {
  background: var(--crm-bg-card);
  border: 1px solid var(--crm-color-border);
  border-radius: var(--crm-radius-card);
  transition: all var(--crm-transition-fast);

  &:hover {
    border-color: var(--crm-color-border-hover);
    transform: translateY(-1px);
  }
}

.channel-icon {
  width: 42px;
  height: 42px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(255, 255, 255, 0.04);
  border: 1px solid var(--crm-color-border);

  &--connected {
    background: rgba(16, 185, 129, 0.08);
    border-color: rgba(16, 185, 129, 0.25);
  }

  &--connecting {
    background: rgba(245, 158, 11, 0.1);
    border-color: rgba(245, 158, 11, 0.25);
  }
}

.platform-badge {
  font-size: 0.68rem;
  font-weight: 600;
  padding: 1px 6px;
  border-radius: 4px;
  border: 1px solid;
  letter-spacing: 0.02em;
}

.channel-name {
  font-size: 0.92rem;
  letter-spacing: -0.01em;
}

.channel-number {
  font-family: var(--crm-font-mono, monospace);
  font-size: 0.75rem;
}

.status-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 3px 8px;
  border-radius: var(--crm-radius-pill);
  font-size: 0.72rem;
  font-weight: 600;
  border: 1px solid transparent;

  &--connected {
    background: var(--crm-color-primary-soft);
    color: var(--crm-color-primary);
    border-color: rgba(16, 185, 129, 0.2);

    .status-dot {
      background-color: var(--crm-color-primary);
    }
  }

  &--connecting {
    background: rgba(245, 158, 11, 0.1);
    color: #f59e0b;
    border-color: rgba(245, 158, 11, 0.2);

    .status-dot {
      background-color: #f59e0b;
    }
  }

  &--disconnected {
    background: rgba(255, 255, 255, 0.04);
    color: var(--crm-color-muted);
    border-color: var(--crm-color-border);

    .status-dot {
      background-color: var(--crm-color-dim);
    }
  }
}

.status-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
}

.channel-menu {
  background: var(--crm-bg-card) !important;
  border: 1px solid var(--crm-color-border) !important;
  border-radius: 8px !important;
}

.credentials-card {
  background: var(--crm-bg-card);
  border: 1px solid var(--crm-color-border);
  border-radius: var(--crm-radius-card);
}

.instruction-box {
  background: rgba(255, 255, 255, 0.02);
  border: 1px solid var(--crm-color-border);
  border-radius: 6px;
  display: flex;
  align-items: flex-start;
}
</style>
