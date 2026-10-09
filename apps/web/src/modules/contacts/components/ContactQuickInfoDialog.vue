<template>
  <q-dialog
    :model-value="modelValue"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <q-card dark class="contact-quick-info-card shadow-24" style="min-width: 440px; max-width: 520px; border-radius: 14px;">
      <!-- Encabezado con Canal de Origen -->
      <q-card-section class="q-pb-none">
        <div class="row items-center justify-between no-wrap">
          <div class="row items-center q-gutter-x-sm">
            <SocialChannelBadge
              :channel="contact?.origin_channel"
              :account-name="contact?.channel_account?.name"
              size="md"
            />
            <q-badge color="teal-9" text-color="teal-2" class="text-caption">
              {{ contact?.status?.toUpperCase() }}
            </q-badge>
          </div>

          <q-btn
            flat
            round
            dense
            icon="sym_r_close"
            color="grey-4"
            v-close-popup
          />
        </div>

        <div class="text-h6 text-weight-bold text-white q-mt-sm">
          Información del Contacto & Procedencia
        </div>
        <div class="text-caption text-grey-4">
          Resumen rápido del contacto y canal de origen estilo Whaticket.
        </div>
      </q-card-section>

      <q-separator dark class="q-my-md" />

      <!-- Cuerpo Principal -->
      <q-card-section class="q-py-none q-gutter-y-md">
        <!-- 1. Bloque de Red Social y Procedencia -->
        <div class="contact-info-block origin-block">
          <div class="text-caption text-weight-bold text-grey-3 q-mb-xs flex items-center q-gutter-x-xs">
            <q-icon name="sym_r_hub" size="16px" color="teal-4" />
            <span>CANAL DE PROCEDENCIA</span>
          </div>

          <div class="row items-center justify-between q-mt-xs">
            <div>
              <div class="text-body2 text-weight-medium text-white">
                {{ getChannelTitle(contact?.origin_channel) }}
              </div>
              <div class="text-caption text-grey-4 q-mt-2">
                <span v-if="contact?.channel_account?.name">
                  Conexión: <strong>{{ contact.channel_account.name }}</strong>
                </span>
                <span v-else-if="contact?.channel_account?.display_phone_number">
                  Línea: <strong>{{ contact.channel_account.display_phone_number }}</strong>
                </span>
                <span v-else>
                  Contacto registrado vía red social / importación
                </span>
              </div>
            </div>

            <SocialChannelBadge
              :channel="contact?.origin_channel"
              :show-label="false"
              size="md"
            />
          </div>
        </div>

        <!-- 2. Datos Personales -->
        <div class="contact-info-block">
          <div class="text-caption text-weight-bold text-grey-3 q-mb-sm flex items-center q-gutter-x-xs">
            <q-icon name="sym_r_person" size="16px" color="teal-4" />
            <span>DATOS DE CONTACTO</span>
          </div>

          <div class="row items-center q-gutter-x-md q-mb-sm">
            <q-avatar size="44px" color="teal-9" text-color="teal-2" class="text-weight-bold">
              {{ contactInitials }}
            </q-avatar>

            <div class="col ellipsis">
              <div class="text-subtitle2 text-weight-bold text-white ellipsis">
                {{ contactName }}
              </div>
              <div v-if="contact?.company?.name" class="text-caption text-grey-4 ellipsis">
                🏢 {{ contact.company.name }}
              </div>
            </div>
          </div>

          <div class="q-gutter-y-xs text-caption">
            <div v-if="contact?.phone" class="row items-center justify-between bg-dark-subtle q-px-sm q-py-xs rounded-borders">
              <span class="text-grey-4">Teléfono / WhatsApp:</span>
              <div class="row items-center q-gutter-x-xs">
                <span class="font-mono text-weight-medium text-grey-2">{{ contact.phone }}</span>
                <q-btn
                  flat
                  round
                  dense
                  size="xs"
                  icon="sym_r_content_copy"
                  color="grey-4"
                  @click="copyText(contact.phone)"
                >
                  <q-tooltip>Copiar número</q-tooltip>
                </q-btn>
              </div>
            </div>

            <div v-if="contact?.email" class="row items-center justify-between bg-dark-subtle q-px-sm q-py-xs rounded-borders">
              <span class="text-grey-4">Correo:</span>
              <span class="text-grey-2">{{ contact.email }}</span>
            </div>
          </div>
        </div>

        <!-- 3. Clasificación (Estado, Categorías & Tags) -->
        <div class="contact-info-block">
          <div class="text-caption text-weight-bold text-grey-3 q-mb-xs flex items-center q-gutter-x-xs">
            <q-icon name="sym_r_tune" size="16px" color="teal-4" />
            <span>ESTADO DEL LEAD & CATEGORIZACIÓN</span>
          </div>

          <div class="row q-gutter-xs items-center q-mt-xs">
            <!-- Estado personalizado -->
            <div v-if="contact?.custom_status">
              <q-badge
                :style="{
                  backgroundColor: contact.custom_status.color + '22',
                  color: contact.custom_status.color,
                  border: '1px solid ' + contact.custom_status.color,
                }"
                class="q-px-sm q-py-xs text-weight-medium"
              >
                <q-icon :name="contact.custom_status.icon || 'sym_r_flag'" size="12px" class="q-mr-xs" />
                {{ contact.custom_status.name }}
              </q-badge>
            </div>

            <q-badge v-else color="teal-9" text-color="teal-2" class="q-px-sm q-py-xs text-capitalize">
              {{ contact?.status || 'Activo' }}
            </q-badge>
          </div>

          <!-- Categorías -->
          <div v-if="contact?.categories?.length" class="row q-gutter-xs q-mt-sm">
            <q-chip
              v-for="cat in contact.categories"
              :key="cat.id"
              dense
              dark
              size="xs"
              :style="{
                backgroundColor: (cat.color || '#06b6d4') + '22',
                borderColor: cat.color || '#06b6d4',
                border: '1px solid',
              }"
            >
              <q-icon :name="cat.icon || 'sym_r_category'" size="11px" class="q-mr-xs" :style="{ color: cat.color || '#06b6d4' }" />
              {{ cat.code ? `[${cat.code}] ${cat.name}` : cat.name }}
            </q-chip>
          </div>

          <!-- Etiquetas -->
          <div v-if="contact?.tags?.length" class="row q-gutter-xs q-mt-xs">
            <q-badge
              v-for="t in contact.tags"
              :key="t.id"
              color="grey-9"
              text-color="grey-3"
              class="text-caption"
            >
              #{{ t.name }}
            </q-badge>
          </div>
        </div>

        <!-- 4. Notas (si existen) -->
        <div v-if="contact?.notes" class="contact-info-block">
          <div class="text-caption text-weight-bold text-grey-4 q-mb-xs">NOTAS INTERNAS:</div>
          <div class="text-caption text-grey-3 italic bg-dark-subtle q-pa-sm rounded-borders">
            {{ contact.notes }}
          </div>
        </div>
      </q-card-section>

      <q-separator dark class="q-my-md" />

      <!-- Acciones del Modal -->
      <q-card-actions align="between" class="q-px-md q-pb-md">
        <q-btn
          flat
          dense
          no-caps
          color="grey-4"
          label="Cerrar"
          v-close-popup
        />

        <div class="row q-gutter-sm">
          <q-btn
            outline
            dense
            no-caps
            color="grey-3"
            icon="sym_r_edit"
            label="Editar"
            class="q-px-sm"
            @click="handleEdit"
          />

          <q-btn
            unelevated
            dense
            no-caps
            color="positive"
            icon="sym_r_chat"
            label="Iniciar Chat"
            class="q-px-md"
            @click="handleChat"
          />
        </div>
      </q-card-actions>
    </q-card>
  </q-dialog>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { useQuasar } from 'quasar'
import SocialChannelBadge from '@/shared/components/SocialChannelBadge.vue'
import type { Contact } from '../types/contact.types'

const props = defineProps<{
  modelValue: boolean
  contact: Contact | null
}>()

const emit = defineEmits<{
  'update:modelValue': [val: boolean]
  edit: [contact: Contact]
}>()

const router = useRouter()
const $q = useQuasar()

const contactName = computed(() => {
  if (!props.contact) return 'Contacto'
  if (props.contact.name) return props.contact.name
  return `${props.contact.first_name || ''} ${props.contact.last_name || ''}`.trim() || 'Sin Nombre'
})

const contactInitials = computed(() => {
  const name = contactName.value
  const parts = name.split(' ')
  if (parts.length >= 2) {
    return (parts[0][0] + parts[1][0]).toUpperCase()
  }
  return name.slice(0, 2).toUpperCase()
})

function getChannelTitle(channel?: string): string {
  switch (channel) {
    case 'whatsapp':
      return 'WhatsApp (Oficial / Baileys)'
    case 'instagram':
      return 'Instagram Direct (Meta DM)'
    case 'facebook':
      return 'Facebook Messenger'
    case 'tiktok':
      return 'TikTok Business Chat'
    case 'email':
      return 'Correo Electrónico'
    default:
      return 'Bandeja Multicanal'
  }
}

function copyText(val: string) {
  navigator.clipboard.writeText(val)
  $q.notify({
    message: 'Copiado al portapapeles',
    type: 'positive',
    timeout: 1500,
  })
}

function handleEdit() {
  if (props.contact) {
    emit('edit', props.contact)
  }
  emit('update:modelValue', false)
}

function handleChat() {
  if (props.contact) {
    router.push(`/app/conversations?contactId=${props.contact.id}`)
  }
  emit('update:modelValue', false)
}
</script>

<style scoped lang="scss">
.contact-quick-info-card {
  background: var(--crm-bg-sidebar, #111827);
  border: 1px solid var(--crm-color-border, #1f2937);
}

.contact-info-block {
  background: rgba(255, 255, 255, 0.03);
  border: 1px solid rgba(255, 255, 255, 0.06);
  border-radius: 8px;
  padding: 10px 12px;
}

.origin-block {
  background: linear-gradient(135deg, rgba(16, 185, 129, 0.08) 0%, rgba(6, 182, 212, 0.04) 100%);
  border: 1px solid rgba(16, 185, 129, 0.2);
}

.bg-dark-subtle {
  background: rgba(0, 0, 0, 0.25);
}
</style>
