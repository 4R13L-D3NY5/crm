<template>
  <q-dialog
    :model-value="modelValue"
    position="right"
    maximized
    transition-show="slide-left"
    transition-hide="slide-right"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <q-card style="width: 480px; max-width: 90vw" class="contact-drawer-card column">
      <!-- Drawer Header -->
      <q-card-section class="contact-drawer-header row items-center justify-between q-pa-md">
        <div class="row items-center q-gutter-x-sm">
          <q-avatar
            size="42px"
            :style="{ backgroundColor: getAvatarColor(contactFullName) }"
            text-color="white"
            class="text-bold text-subtitle1"
          >
            {{ contactInitials }}
          </q-avatar>
          <div>
            <div class="text-subtitle1 text-bold text-white leading-tight">
              {{ contactFullName }}
            </div>
            <div class="text-caption text-grey-4">
              {{ contact?.email || 'Sin correo registrado' }}
            </div>
          </div>
        </div>

        <q-btn flat round dense icon="sym_r_close" color="grey-4" v-close-popup />
      </q-card-section>

      <q-separator dark />

      <!-- Quick Action Buttons -->
      <div class="row q-pa-sm q-gutter-xs bg-dark-page">
        <q-btn
          v-if="contact?.phone"
          flat
          dense
          no-caps
          color="positive"
          icon="sym_r_chat"
          label="Chatear en WhatsApp"
          class="col"
          :to="`/app/conversations?contactId=${contact?.id}`"
        />
        <q-btn
          flat
          dense
          no-caps
          color="teal-4"
          icon="sym_r_edit"
          label="Editar"
          class="col"
          @click="onEditClick"
        />
      </div>

      <q-separator dark />

      <!-- Tabs Navigation -->
      <q-tabs
        v-model="activeTab"
        dense
        dark
        align="left"
        class="text-grey-4"
        active-color="primary"
        indicator-color="primary"
      >
        <q-tab name="info" label="Datos 360°" icon="sym_r_person" />
        <q-tab name="conversations" label="Historial Inbox" icon="sym_r_forum" />
        <q-tab name="deals" label="Oportunidades" icon="sym_r_monetization_on" />
      </q-tabs>

      <q-separator dark />

      <!-- Tab Content Area -->
      <q-tab-panels v-model="activeTab" animated dark class="col contact-drawer-panels">
        <!-- Panel 1: Datos Generales -->
        <q-tab-panel name="info" class="q-pa-md q-gutter-y-md">
          <div class="info-block">
            <span class="info-label">Teléfono Móvil</span>
            <div class="info-value font-mono text-positive">
              {{ contact?.phone || 'No registrado' }}
            </div>
          </div>

          <div class="info-block">
            <span class="info-label">Empresa Asociada</span>
            <div class="info-value">
              {{ contact?.company?.name || (contact?.companies && contact.companies[0]?.name) || 'Cliente Particular' }}
            </div>
          </div>

          <div class="info-block">
            <span class="info-label">Estado de Relación</span>
            <div class="q-mt-xs">
              <q-badge :color="statusColor(contact?.status)" rounded class="q-px-sm">
                {{ contact?.status || 'active' }}
              </q-badge>
            </div>
          </div>

          <div class="info-block">
            <span class="info-label">Etiquetas</span>
            <div class="row q-gutter-xs q-mt-xs">
              <span
                v-for="tag in contact?.tags"
                :key="typeof tag === 'string' ? tag : tag.id"
                class="tag-badge"
              >
                {{ typeof tag === 'string' ? tag : tag.name }}
              </span>
              <span v-if="!contact?.tags?.length" class="text-caption text-grey-6 italic">
                Sin etiquetas asignadas
              </span>
            </div>
          </div>

          <div class="info-block">
            <span class="info-label">Notas Internas</span>
            <div class="info-notes text-caption text-grey-3 q-mt-xs">
              {{ contact?.notes || 'No se han agregado notas para este contacto.' }}
            </div>
          </div>
        </q-tab-panel>

        <!-- Panel 2: Conversaciones Recientes -->
        <q-tab-panel name="conversations" class="q-pa-md">
          <div v-if="!contact?.conversations || contact.conversations.length === 0" class="text-center q-pa-lg text-grey-5">
            <q-icon name="sym_r_speaker_notes_off" size="36px" class="q-mb-xs" />
            <div>No hay conversaciones previas registradas.</div>
          </div>
          <q-list v-else separator dark>
            <q-item
              v-for="conv in contact.conversations"
              :key="conv.id"
              clickable
              class="q-px-none"
              :to="`/app/conversations?contactId=${contact.id}`"
            >
              <q-item-section avatar>
                <q-icon name="sym_r_chat" color="positive" size="20px" />
              </q-item-section>
              <q-item-section>
                <q-item-label class="text-white text-caption text-bold">
                  {{ conv.subject || 'Conversación WhatsApp' }}
                </q-item-label>
                <q-item-label caption class="text-grey-5">
                  Estado: {{ conv.status }} • {{ conv.channel }}
                </q-item-label>
              </q-item-section>
              <q-item-section side>
                <q-badge outline color="teal-4">Ver</q-badge>
              </q-item-section>
            </q-item>
          </q-list>
        </q-tab-panel>

        <!-- Panel 3: Deals / Oportunidades -->
        <q-tab-panel name="deals" class="q-pa-md">
          <div v-if="!contact?.deals || contact.deals.length === 0" class="text-center q-pa-lg text-grey-5">
            <q-icon name="sym_r_inventory_2" size="36px" class="q-mb-xs" />
            <div>Sin oportunidades comerciales vinculadas.</div>
          </div>
          <q-list v-else separator dark>
            <q-item
              v-for="deal in contact.deals"
              :key="deal.id"
              class="q-px-none"
            >
              <q-item-section avatar>
                <q-icon name="sym_r_attach_money" color="amber-4" size="20px" />
              </q-item-section>
              <q-item-section>
                <q-item-label class="text-white text-caption text-bold">{{ deal.name }}</q-item-label>
                <q-item-label caption class="text-grey-5">
                  Monto: ${{ Number(deal.amount || 0).toLocaleString() }} • {{ deal.status }}
                </q-item-label>
              </q-item-section>
            </q-item>
          </q-list>
        </q-tab-panel>
      </q-tab-panels>
    </q-card>
  </q-dialog>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import type { Contact } from '../types/contact.types'

const props = defineProps<{
  modelValue: boolean
  contact: Contact | null
}>()

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  edit: [contact: Contact]
}>()

const activeTab = ref('info')

const contactFullName = computed(() => {
  if (!props.contact) return ''
  if (props.contact.name) return props.contact.name
  const name = `${props.contact.first_name || ''} ${props.contact.last_name || ''}`.trim()
  return name || 'Sin nombre'
})

const contactInitials = computed(() => {
  return contactFullName.value.charAt(0).toUpperCase() || 'C'
})

function onEditClick() {
  if (props.contact) {
    emit('edit', props.contact)
    emit('update:modelValue', false)
  }
}

function statusColor(status?: string): string {
  switch (status) {
    case 'active':
      return 'positive'
    case 'lead':
      return 'warning'
    case 'inactive':
      return 'grey-7'
    default:
      return 'primary'
  }
}

function getAvatarColor(name: string): string {
  const colors = ['#10b981', '#0284c7', '#d97706', '#059669', '#8b5cf6', '#ec4899', '#6366f1', '#06b6d4']
  let hash = 0
  for (let i = 0; i < name.length; i++) hash = name.charCodeAt(i) + ((hash << 5) - hash)
  return colors[Math.abs(hash) % colors.length]
}
</script>

<style scoped lang="scss">
.contact-drawer-card {
  background: var(--crm-bg-card, #111827);
  height: 100vh;
}

.contact-drawer-header {
  background: rgba(255, 255, 255, 0.02);
}

.contact-drawer-panels {
  background: transparent;
}

.info-block {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.info-label {
  font-size: 0.72rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--crm-color-muted, #94a3b8);
}

.info-value {
  font-size: 0.95rem;
  color: #ffffff;
  font-weight: 500;
}

.info-notes {
  background: rgba(255, 255, 255, 0.03);
  border: 1px solid rgba(255, 255, 255, 0.06);
  border-radius: 8px;
  padding: 10px 12px;
  line-height: 1.5;
}

.tag-badge {
  display: inline-flex;
  font-size: 0.72rem;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: 999px;
  background: rgba(16, 185, 129, 0.2);
  color: #10b981;
  border: 1px solid rgba(16, 185, 129, 0.4);
}
</style>
