<template>
  <q-card flat bordered class="contact-table-card">
    <q-markup-table flat dark class="contact-table">
      <thead>
        <tr>
          <th class="text-left">CONTACTO</th>
          <th class="text-left">TELÉFONO / WHATSAPP</th>
          <th class="text-left">CORREO</th>
          <th class="text-left">ETIQUETAS</th>
          <th class="text-left">ESTADO</th>
          <th class="text-left">REGISTRO</th>
          <th class="text-center">ACCIONES</th>
        </tr>
      </thead>
      <tbody>
        <tr v-if="loading">
          <td colspan="7" class="text-center q-pa-lg text-grey-4">
            <q-spinner-dots size="32px" color="primary" />
            <div class="q-mt-sm">Cargando directorio de contactos...</div>
          </td>
        </tr>
        <tr v-else-if="contacts.length === 0">
          <td colspan="7" class="text-center q-pa-xl text-grey-4">
            <q-icon name="sym_r_person_off" size="48px" class="q-mb-sm text-grey-6" />
            <div class="text-subtitle1 text-white text-bold">No se encontraron contactos</div>
            <div class="text-caption text-grey-5">Ajusta los filtros o crea un nuevo contacto para comenzar.</div>
          </td>
        </tr>
        <tr
          v-for="contact in contacts"
          v-else
          :key="contact.id"
          class="contact-table__row cursor-pointer"
          @click="emit('view', contact)"
        >
          <!-- Nombre + Avatar -->
          <td class="text-left">
            <div class="row items-center q-gutter-x-sm">
              <q-avatar
                size="34px"
                :style="{ backgroundColor: getAvatarColor(contactName(contact)) }"
                text-color="white"
                class="text-bold text-caption shadow-1"
              >
                {{ contactInitials(contact) }}
              </q-avatar>
              <div>
                <div class="text-bold text-white hover-underline">{{ contactName(contact) }}</div>
                <div class="text-caption text-grey-5">
                  {{ contact.company?.name || (contact.companies && contact.companies[0]?.name) || 'Particular' }}
                </div>
              </div>
            </div>
          </td>

          <!-- Teléfono / WhatsApp -->
          <td class="text-left" @click.stop>
            <div v-if="contact.phone" class="row items-center q-gutter-x-xs">
              <q-btn
                flat
                dense
                round
                size="xs"
                icon="sym_r_chat"
                color="positive"
                :to="`/app/conversations?contactId=${contact.id}`"
              >
                <q-tooltip>Iniciar chat por WhatsApp</q-tooltip>
              </q-btn>
              <span class="text-grey-3 font-mono text-caption">{{ contact.phone }}</span>
            </div>
            <span v-else class="text-caption text-grey-6 italic">Sin teléfono</span>
          </td>

          <!-- Correo -->
          <td class="text-left text-grey-4 text-caption">
            {{ contact.email || '-' }}
          </td>

          <!-- Etiquetas -->
          <td class="text-left">
            <div class="row q-gutter-xs items-center">
              <span
                v-for="tag in contact.tags"
                :key="typeof tag === 'string' ? tag : tag.id"
                class="contact-tag-badge"
                :style="{ backgroundColor: getTagColor(tag) }"
              >
                {{ typeof tag === 'string' ? tag : tag.name }}
              </span>
              <span v-if="!contact.tags || contact.tags.length === 0" class="text-caption text-grey-6 italic">
                Sin tags
              </span>
            </div>
          </td>

          <!-- Estado -->
          <td class="text-left">
            <q-badge
              :color="statusColor(contact.status)"
              rounded
              class="q-px-sm q-py-xs text-capitalize"
            >
              {{ contact.status }}
            </q-badge>
          </td>

          <!-- Registro -->
          <td class="text-left text-grey-5 text-caption">
            {{ formatDate(contact.created_at) }}
          </td>

          <!-- Acciones -->
          <td class="text-center" @click.stop>
            <div class="row justify-center q-gutter-xs">
              <q-btn
                flat
                round
                dense
                size="sm"
                icon="sym_r_visibility"
                color="teal-4"
                @click="emit('view', contact)"
              >
                <q-tooltip>Ver perfil 360°</q-tooltip>
              </q-btn>
              <q-btn
                flat
                round
                dense
                size="sm"
                icon="sym_r_edit"
                color="grey-4"
                @click="emit('edit', contact)"
              >
                <q-tooltip>Editar contacto</q-tooltip>
              </q-btn>
              <q-btn
                flat
                round
                dense
                size="sm"
                icon="sym_r_delete"
                color="negative"
                @click="emit('delete', contact)"
              >
                <q-tooltip>Eliminar contacto</q-tooltip>
              </q-btn>
            </div>
          </td>
        </tr>
      </tbody>
    </q-markup-table>
  </q-card>
</template>

<script setup lang="ts">
import type { Contact, ContactTag } from '../types/contact.types'

defineProps<{
  contacts: Contact[]
  loading?: boolean
}>()

const emit = defineEmits<{
  view: [contact: Contact]
  edit: [contact: Contact]
  delete: [contact: Contact]
}>()

function contactName(contact: Contact): string {
  if (contact.name) return contact.name
  const full = `${contact.first_name || ''} ${contact.last_name || ''}`.trim()
  return full || 'Sin nombre'
}

function contactInitials(contact: Contact): string {
  const name = contactName(contact)
  return name.charAt(0).toUpperCase()
}

function statusColor(status: string): string {
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

function getTagColor(tag: ContactTag | string): string {
  if (typeof tag === 'object' && tag.color_hex) {
    return tag.color_hex
  }
  const name = typeof tag === 'string' ? tag : tag.name
  if (name.includes('Cochabamba')) return '#00a884'
  if (name.includes('EA 1-2026') || name.includes('Admisiones')) return '#8b5cf6'
  if (name.includes('02-2026') || name.includes('Santa Cruz')) return '#0284c7'
  return '#1e293b'
}

function formatDate(dateStr: string | null): string {
  if (!dateStr) return '-'
  const d = new Date(dateStr)
  if (isNaN(d.getTime())) return dateStr
  return d.toLocaleDateString('es-ES', { day: '2-digit', month: 'short', year: 'numeric' })
}
</script>

<style scoped lang="scss">
.contact-table-card {
  background: var(--crm-bg-card, #111827);
  border-radius: var(--crm-radius-card, 14px);
  overflow: hidden;
}

.contact-table {
  background: transparent;

  thead tr th {
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    color: var(--crm-color-muted, #94a3b8);
    border-bottom: 1px solid var(--crm-color-border, rgba(255, 255, 255, 0.07));
    padding: 14px 16px;
  }

  tbody tr {
    transition: background 0.15s ease;
    border-bottom: 1px solid var(--crm-color-border, rgba(255, 255, 255, 0.05));

    &:hover {
      background: rgba(255, 255, 255, 0.03);
    }
  }

  tbody td {
    padding: 12px 16px;
  }
}

.contact-tag-badge {
  display: inline-flex;
  align-items: center;
  font-size: 0.72rem;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: 999px;
  color: #ffffff;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
}

.hover-underline:hover {
  text-decoration: underline;
}
</style>
