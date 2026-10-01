<template>
  <div class="col-12 col-sm-6 col-md-4 col-lg-3">
    <q-card flat bordered class="contact-grid-card cursor-pointer" @click="emit('view', contact)">
      <q-card-section class="q-pb-sm">
        <div class="row items-center justify-between no-wrap">
          <div class="row items-center q-gutter-x-sm no-wrap">
            <q-avatar
              size="40px"
              :style="{ backgroundColor: getAvatarColor(displayName) }"
              text-color="white"
              class="text-bold text-caption"
            >
              {{ displayName.charAt(0).toUpperCase() }}
            </q-avatar>
            <div class="ellipsis">
              <div class="text-bold text-white ellipsis">{{ displayName }}</div>
              <div class="text-caption text-grey-5 ellipsis">
                {{ contact.company?.name || 'Cliente Particular' }}
              </div>
            </div>
          </div>

          <q-badge :color="statusColor" rounded class="q-px-xs text-caption">
            {{ contact.status }}
          </q-badge>
        </div>
      </q-card-section>

      <q-separator dark class="q-my-xs" />

      <q-card-section class="q-py-xs q-gutter-y-xs text-caption">
        <div v-if="contact.phone" class="row items-center q-gutter-x-xs font-mono text-grey-3">
          <q-icon name="sym_r_chat" size="14px" color="positive" />
          <span>{{ contact.phone }}</span>
        </div>
        <div v-if="contact.email" class="row items-center q-gutter-x-xs text-grey-4 ellipsis">
          <q-icon name="sym_r_mail" size="14px" color="teal-4" />
          <span class="ellipsis">{{ contact.email }}</span>
        </div>

        <div class="row q-gutter-xs q-mt-xs">
          <span
            v-for="tag in contact.tags?.slice(0, 3)"
            :key="typeof tag === 'string' ? tag : tag.id"
            class="contact-grid-tag"
          >
            {{ typeof tag === 'string' ? tag : tag.name }}
          </span>
          <span v-if="(contact.tags?.length || 0) > 3" class="text-caption text-grey-5">
            +{{ (contact.tags?.length || 0) - 3 }}
          </span>
        </div>
      </q-card-section>

      <q-separator dark class="q-my-xs" />

      <q-card-actions align="between" class="q-px-sm q-py-xs" @click.stop>
        <q-btn
          v-if="contact.phone"
          flat
          dense
          no-caps
          size="sm"
          color="positive"
          icon="sym_r_chat"
          label="Chat"
          :to="`/app/conversations?contactId=${contact.id}`"
        />
        <div class="row q-gutter-xs">
          <q-btn flat round dense size="sm" icon="sym_r_edit" color="grey-4" @click="emit('edit', contact)">
            <q-tooltip>Editar</q-tooltip>
          </q-btn>
          <q-btn flat round dense size="sm" icon="sym_r_delete" color="negative" @click="emit('delete', contact)">
            <q-tooltip>Eliminar</q-tooltip>
          </q-btn>
        </div>
      </q-card-actions>
    </q-card>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { Contact } from '../types/contact.types'

const props = defineProps<{
  contact: Contact
}>()

const emit = defineEmits<{
  view: [contact: Contact]
  edit: [contact: Contact]
  delete: [contact: Contact]
}>()

const displayName = computed(() => {
  if (props.contact.name) return props.contact.name
  return `${props.contact.first_name || ''} ${props.contact.last_name || ''}`.trim() || 'Sin Nombre'
})

const statusColor = computed(() => {
  switch (props.contact.status) {
    case 'active':
      return 'positive'
    case 'lead':
      return 'warning'
    case 'inactive':
      return 'grey-7'
    default:
      return 'primary'
  }
})

function getAvatarColor(name: string): string {
  const colors = ['#10b981', '#0284c7', '#d97706', '#059669', '#8b5cf6', '#ec4899', '#6366f1', '#06b6d4']
  let hash = 0
  for (let i = 0; i < name.length; i++) hash = name.charCodeAt(i) + ((hash << 5) - hash)
  return colors[Math.abs(hash) % colors.length]
}
</script>

<style scoped lang="scss">
.contact-grid-card {
  background: var(--crm-bg-card, #111827);
  border: 1px solid var(--crm-color-border, rgba(255, 255, 255, 0.08));
  border-radius: 12px;
  transition: transform 0.15s ease, border-color 0.15s ease;

  &:hover {
    transform: translateY(-2px);
    border-color: rgba(16, 185, 129, 0.4);
  }
}

.contact-grid-tag {
  display: inline-flex;
  font-size: 0.68rem;
  font-weight: 600;
  padding: 1px 6px;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.08);
  color: #e2e8f0;
}
</style>
