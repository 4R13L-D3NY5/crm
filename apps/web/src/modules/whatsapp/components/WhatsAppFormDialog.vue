<template>
  <q-dialog
    :model-value="modelValue"
    persistent
    @update:model-value="emit('update:modelValue', $event)"
  >
    <q-card style="width: 580px; max-width: 95vw" class="channel-form-card">
      <q-card-section class="row items-center justify-between q-pb-none">
        <div>
          <div class="text-subtitle1 text-weight-bold text-white">Conectar Nuevo Canal</div>
          <div class="text-caption text-grey-4">Elige la plataforma que deseas integrar a la bandeja omnicanal</div>
        </div>
        <q-btn flat round dense icon="sym_r_close" color="grey-4" v-close-popup />
      </q-card-section>

      <q-card-section class="q-pt-md">
        <!-- Selector Visual de Plataforma -->
        <div class="platform-selector q-mb-md">
          <button
            type="button"
            class="platform-chip"
            :class="{ 'platform-chip--active': form.session_type === 'baileys_qr' }"
            @click="form.session_type = 'baileys_qr'"
          >
            <q-icon name="sym_r_qr_code" size="18px" color="teal-4" />
            <span>WhatsApp (QR)</span>
          </button>

          <button
            type="button"
            class="platform-chip"
            :class="{ 'platform-chip--active': form.session_type === 'meta_cloud' }"
            @click="form.session_type = 'meta_cloud'"
          >
            <q-icon name="sym_r_cloud" size="18px" color="teal-3" />
            <span>WhatsApp Cloud</span>
          </button>

          <button
            type="button"
            class="platform-chip"
            :class="{ 'platform-chip--active': form.session_type === 'facebook' }"
            @click="form.session_type = 'facebook'"
          >
            <q-icon name="sym_r_public" size="18px" color="blue-4" />
            <span>Facebook Fanpage</span>
          </button>

          <button
            type="button"
            class="platform-chip"
            :class="{ 'platform-chip--active': form.session_type === 'instagram' }"
            @click="form.session_type = 'instagram'"
          >
            <q-icon name="sym_r_photo_camera" size="18px" color="pink-4" />
            <span>Instagram Direct</span>
          </button>

          <button
            type="button"
            class="platform-chip"
            :class="{ 'platform-chip--active': form.session_type === 'tiktok' }"
            @click="form.session_type = 'tiktok'"
          >
            <q-icon name="sym_r_music_note" size="18px" color="cyan-3" />
            <span>TikTok Business</span>
          </button>
        </div>

        <q-form class="q-gutter-y-sm" @submit.prevent="handleSubmit">
          <!-- Campo Común: Nombre de la Conexión -->
          <div>
            <label class="text-caption text-grey-4 q-mb-xs block">Nombre del Canal / Identificador *</label>
            <q-input
              v-model="form.name"
              outlined
              dark
              dense
              :placeholder="namePlaceholder"
              :rules="[val => !!val || 'El nombre es obligatorio']"
            >
              <template #prepend>
                <q-icon name="sym_r_label" size="16px" class="text-grey-5" />
              </template>
            </q-input>
          </div>

          <!-- Campos para WhatsApp Web (QR) -->
          <template v-if="form.session_type === 'baileys_qr'">
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
            <div class="info-box q-pa-sm q-mt-xs">
              <q-icon name="sym_r_info" size="16px" color="teal-4" class="q-mr-xs" />
              <span class="text-caption text-grey-4">
                Al guardar, se generará inmediatamente el <strong>Código QR</strong> para escanear desde tu aplicación de WhatsApp en el celular.
              </span>
            </div>
          </template>

          <!-- Campos para WhatsApp Cloud API (Oficial Meta) -->
          <template v-else-if="form.session_type === 'meta_cloud'">
            <div class="row q-col-gutter-sm">
              <div class="col-12 col-sm-6">
                <label class="text-caption text-grey-4 q-mb-xs block">Phone Number ID *</label>
                <q-input
                  v-model="form.phone_number_id"
                  outlined
                  dark
                  dense
                  placeholder="Ej. 104829104829104"
                  :rules="[val => !!val || 'El Phone Number ID es requerido']"
                />
              </div>
              <div class="col-12 col-sm-6">
                <label class="text-caption text-grey-4 q-mb-xs block">WABA Account ID</label>
                <q-input
                  v-model="form.business_account_id"
                  outlined
                  dark
                  dense
                  placeholder="Ej. 291048291048291"
                />
              </div>
            </div>

            <div>
              <label class="text-caption text-grey-4 q-mb-xs block">Permanent Access Token (Meta) *</label>
              <q-input
                v-model="form.access_token"
                outlined
                dark
                dense
                type="password"
                placeholder="EAAGm0PX..."
                :rules="[val => !!val || 'El Access Token es requerido']"
              />
            </div>
          </template>

          <!-- Campos para Facebook Fanpage -->
          <template v-else-if="form.session_type === 'facebook'">
            <div class="row q-col-gutter-sm">
              <div class="col-12 col-sm-6">
                <label class="text-caption text-grey-4 q-mb-xs block">Facebook Page ID *</label>
                <q-input
                  v-model="form.phone_number_id"
                  outlined
                  dark
                  dense
                  placeholder="Ej. 109283746192837"
                  :rules="[val => !!val || 'El Page ID es requerido']"
                />
              </div>
              <div class="col-12 col-sm-6">
                <label class="text-caption text-grey-4 q-mb-xs block">Nombre o @Usuario de Página</label>
                <q-input
                  v-model="form.display_phone_number"
                  outlined
                  dark
                  dense
                  placeholder="@miempresa_oficial"
                />
              </div>
            </div>

            <div>
              <label class="text-caption text-grey-4 q-mb-xs block">Page Access Token *</label>
              <q-input
                v-model="form.access_token"
                outlined
                dark
                dense
                type="password"
                placeholder="EAA..."
                :rules="[val => !!val || 'El Page Token es requerido']"
              />
            </div>
          </template>

          <!-- Campos para Instagram Direct -->
          <template v-else-if="form.session_type === 'instagram'">
            <div class="row q-col-gutter-sm">
              <div class="col-12 col-sm-6">
                <label class="text-caption text-grey-4 q-mb-xs block">Instagram Professional Account ID *</label>
                <q-input
                  v-model="form.phone_number_id"
                  outlined
                  dark
                  dense
                  placeholder="Ej. 178414058291048"
                  :rules="[val => !!val || 'El Account ID es requerido']"
                />
              </div>
              <div class="col-12 col-sm-6">
                <label class="text-caption text-grey-4 q-mb-xs block">Usuario de Instagram</label>
                <q-input
                  v-model="form.display_phone_number"
                  outlined
                  dark
                  dense
                  placeholder="@empresa_ig"
                />
              </div>
            </div>

            <div>
              <label class="text-caption text-grey-4 q-mb-xs block">Meta User/Page Access Token *</label>
              <q-input
                v-model="form.access_token"
                outlined
                dark
                dense
                type="password"
                placeholder="EAA..."
                :rules="[val => !!val || 'El Token es requerido']"
              />
            </div>
          </template>

          <!-- Campos para TikTok Business -->
          <template v-else-if="form.session_type === 'tiktok'">
            <div class="row q-col-gutter-sm">
              <div class="col-12 col-sm-6">
                <label class="text-caption text-grey-4 q-mb-xs block">TikTok Client Key / App ID *</label>
                <q-input
                  v-model="form.business_account_id"
                  outlined
                  dark
                  dense
                  placeholder="Ej. aw123456789"
                  :rules="[val => !!val || 'El Client Key es requerido']"
                />
              </div>
              <div class="col-12 col-sm-6">
                <label class="text-caption text-grey-4 q-mb-xs block">Usuario de TikTok</label>
                <q-input
                  v-model="form.display_phone_number"
                  outlined
                  dark
                  dense
                  placeholder="@marca_tiktok"
                />
              </div>
            </div>

            <div>
              <label class="text-caption text-grey-4 q-mb-xs block">Access Token de TikTok *</label>
              <q-input
                v-model="form.access_token"
                outlined
                dark
                dense
                type="password"
                placeholder="act.123456..."
                :rules="[val => !!val || 'El Access Token es requerido']"
              />
            </div>
          </template>

          <q-card-actions align="right" class="q-px-none q-pt-md">
            <q-btn flat label="Cancelar" color="grey-4" no-caps v-close-popup />
            <q-btn
              type="submit"
              unelevated
              label="Conectar Canal"
              icon="sym_r_add_link"
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
import type { CreateWhatsAppAccountPayload } from '../types/whatsapp.types'

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
  session_type: 'baileys_qr',
  display_phone_number: '',
  phone_number_id: '',
  business_account_id: '',
  access_token: '',
})

const namePlaceholder = computed(() => {
  switch (form.session_type) {
    case 'meta_cloud':
      return 'Ej. WhatsApp API Oficial de Ventas'
    case 'facebook':
      return 'Ej. Facebook Fanpage Oficial'
    case 'instagram':
      return 'Ej. Instagram Atención al Cliente'
    case 'tiktok':
      return 'Ej. TikTok Cuenta Corporativa'
    case 'baileys_qr':
    default:
      return 'Ej. WhatsApp Línea Principal'
  }
})

watch(
  () => props.modelValue,
  (open) => {
    if (open) {
      form.name = ''
      form.display_phone_number = ''
      form.phone_number_id = ''
      form.business_account_id = ''
      form.access_token = ''
    }
  },
)

function handleSubmit() {
  emit('submit', { ...form })
}
</script>

<style scoped lang="scss">
.channel-form-card {
  background: var(--crm-bg-card);
  border: 1px solid var(--crm-color-border);
  border-radius: var(--crm-radius-card);
}

.platform-selector {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.platform-chip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 10px;
  background: rgba(255, 255, 255, 0.03);
  border: 1px solid var(--crm-color-border);
  border-radius: 8px;
  color: var(--crm-color-muted);
  font-size: 0.78rem;
  font-weight: 500;
  cursor: pointer;
  transition: all var(--crm-transition-fast);

  &:hover {
    background: rgba(255, 255, 255, 0.06);
    color: var(--crm-color-ink);
  }

  &--active {
    background: var(--crm-color-primary-soft);
    border-color: rgba(16, 185, 129, 0.35);
    color: var(--crm-color-primary);
    font-weight: 600;
  }
}

.info-box {
  background: rgba(16, 185, 129, 0.06);
  border: 1px solid rgba(16, 185, 129, 0.15);
  border-radius: 8px;
}
</style>
