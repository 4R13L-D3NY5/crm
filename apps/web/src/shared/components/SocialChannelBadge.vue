<template>
  <div
    class="social-channel-badge inline-flex items-center no-wrap"
    :class="[`social-channel-badge--${resolvedChannel}`, `social-channel-badge--${size}`]"
  >
    <!-- Contenedor del Icono con Color Oficial -->
    <div class="social-channel-badge__icon-wrap">
      <!-- WhatsApp SVG -->
      <svg
        v-if="resolvedChannel === 'whatsapp'"
        viewBox="0 0 24 24"
        class="social-svg-icon"
      >
        <path
          fill="currentColor"
          d="M19.05 4.91A9.816 9.816 0 0 0 12.04 2c-5.46 0-9.91 4.45-9.91 9.91 0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01zm-7.01 15.24c-1.48 0-2.93-.4-4.2-1.15l-.3-.18-3.12.82.83-3.04-.2-.31a8.212 8.212 0 0 1-1.26-4.38c0-4.54 3.7-8.24 8.24-8.24 2.2 0 4.27.86 5.82 2.42a8.18 8.18 0 0 1 2.41 5.83c.02 4.54-3.68 8.23-8.22 8.23zm4.52-6.16c-.25-.12-1.47-.72-1.69-.81-.23-.08-.39-.12-.56.12-.17.25-.64.81-.79.97-.14.17-.29.19-.54.06-.25-.12-1.05-.39-1.99-1.23-.74-.66-1.23-1.47-1.38-1.72-.14-.25-.02-.38.11-.51.11-.11.25-.29.37-.43.12-.14.17-.25.25-.41.08-.17.04-.31-.02-.43s-.56-1.34-.76-1.84c-.2-.48-.41-.42-.56-.43h-.48c-.17 0-.44.06-.66.31-.22.25-.86.84-.86 2.05 0 1.21.88 2.38 1 2.55.13.17 1.74 2.66 4.21 3.73.59.25 1.05.41 1.41.52.59.19 1.13.16 1.56.1.48-.07 1.47-.6 1.67-1.18.21-.58.21-1.07.15-1.18-.07-.11-.23-.18-.47-.3z"
        />
      </svg>

      <!-- Instagram SVG -->
      <svg
        v-else-if="resolvedChannel === 'instagram'"
        viewBox="0 0 24 24"
        class="social-svg-icon"
      >
        <path
          fill="currentColor"
          d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"
        />
      </svg>

      <!-- Facebook Messenger SVG -->
      <svg
        v-else-if="resolvedChannel === 'facebook'"
        viewBox="0 0 24 24"
        class="social-svg-icon"
      >
        <path
          fill="currentColor"
          d="M12 2C6.477 2 2 6.145 2 11.258c0 2.914 1.455 5.518 3.735 7.205V22l3.39-1.861c.91.252 1.877.388 2.875.388 5.523 0 10-4.145 10-9.258C22 6.145 17.523 2 12 2zm1.07 12.443l-2.73-2.91-5.32 2.91 5.85-6.208 2.8 2.91 5.25-2.91-5.85 6.208z"
        />
      </svg>

      <!-- TikTok SVG -->
      <svg
        v-else-if="resolvedChannel === 'tiktok'"
        viewBox="0 0 24 24"
        class="social-svg-icon"
      >
        <path
          fill="currentColor"
          d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-5.2 1.74 2.89 2.89 0 0 1 2.31-4.64c.29 0 .58.04.85.12V9.4a6.33 6.33 0 0 0-.85-.06A6.34 6.34 0 0 0 3.14 15.7a6.34 6.34 0 0 0 9.77 5.34V13.2a8.16 8.16 0 0 0 5.14 1.83V11.6a4.85 4.85 0 0 1-1.54-.36 4.79 4.79 0 0 1-2.92-4.55z"
        />
      </svg>

      <!-- Email / Default -->
      <q-icon
        v-else-if="resolvedChannel === 'email'"
        name="sym_r_mail"
        class="social-symbol-icon"
      />

      <q-icon
        v-else
        name="sym_r_forum"
        class="social-symbol-icon"
      />
    </div>

    <!-- Etiqueta de Texto -->
    <span v-if="showLabel" class="social-channel-badge__label">
      {{ channelLabel }}
    </span>

    <!-- Nombre de la Conexión o Número Opcional -->
    <span v-if="connectionDetail" class="social-channel-badge__detail">
      • {{ connectionDetail }}
    </span>

    <!-- Tooltip con Información de Procedencia -->
    <q-tooltip v-if="showTooltip" anchor="top middle" self="bottom middle">
      <div><strong>Canal:</strong> {{ channelFullTitle }}</div>
      <div v-if="connectionDetail"><strong>Línea / Cuenta:</strong> {{ connectionDetail }}</div>
    </q-tooltip>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(
  defineProps<{
    channel?: string | null
    accountName?: string | null
    phoneNumber?: string | null
    size?: 'xs' | 'sm' | 'md'
    showLabel?: boolean
    showTooltip?: boolean
  }>(),
  {
    channel: 'whatsapp',
    accountName: null,
    phoneNumber: null,
    size: 'sm',
    showLabel: true,
    showTooltip: true,
  }
)

const resolvedChannel = computed(() => {
  const ch = (props.channel || 'whatsapp').toLowerCase()
  if (ch.includes('wa') || ch.includes('what')) return 'whatsapp'
  if (ch.includes('insta')) return 'instagram'
  if (ch.includes('face') || ch.includes('mess')) return 'facebook'
  if (ch.includes('tik')) return 'tiktok'
  if (ch.includes('mail') || ch.includes('correo')) return 'email'
  return 'manual'
})

const channelLabel = computed(() => {
  switch (resolvedChannel.value) {
    case 'whatsapp':
      return 'WhatsApp'
    case 'instagram':
      return 'Instagram'
    case 'facebook':
      return 'Messenger'
    case 'tiktok':
      return 'TikTok'
    case 'email':
      return 'Correo'
    default:
      return 'Manual'
  }
})

const channelFullTitle = computed(() => {
  switch (resolvedChannel.value) {
    case 'whatsapp':
      return 'WhatsApp (Oficial / Baileys QR)'
    case 'instagram':
      return 'Instagram Direct (Meta DM)'
    case 'facebook':
      return 'Facebook Messenger (Meta DM)'
    case 'tiktok':
      return 'TikTok Business'
    case 'email':
      return 'Correo Electrónico'
    default:
      return 'Bandeja Web / Contacto Manual'
  }
})

const connectionDetail = computed(() => {
  if (props.accountName) return props.accountName
  if (props.phoneNumber) return props.phoneNumber
  return null
})
</script>

<style scoped lang="scss">
.social-channel-badge {
  display: inline-flex;
  align-items: center;
  border-radius: 999px;
  font-weight: 600;
  line-height: 1;
  transition: all var(--crm-transition-fast);
  user-select: none;

  &__icon-wrap {
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .social-svg-icon {
    width: 13px;
    height: 13px;
  }

  .social-symbol-icon {
    font-size: 13px;
  }

  &__label {
    letter-spacing: 0.2px;
  }

  &__detail {
    opacity: 0.8;
    font-size: 0.9em;
    font-weight: 400;
  }

  // TAMAÑOS
  &--xs {
    padding: 2px 5px;
    font-size: 0.65rem;
    gap: 4px;

    .social-svg-icon,
    .social-symbol-icon {
      width: 11px;
      height: 11px;
      font-size: 11px;
    }
  }

  &--sm {
    padding: 2px 7px;
    font-size: 0.72rem;
    gap: 5px;

    .social-svg-icon,
    .social-symbol-icon {
      width: 13px;
      height: 13px;
      font-size: 13px;
    }
  }

  &--md {
    padding: 4px 10px;
    font-size: 0.8rem;
    gap: 6px;

    .social-svg-icon,
    .social-symbol-icon {
      width: 15px;
      height: 15px;
      font-size: 15px;
    }
  }

  // COLORES POR RED SOCIAL
  &--whatsapp {
    background: rgba(37, 211, 102, 0.12);
    color: #25d366;
    border: 1px solid rgba(37, 211, 102, 0.28);

    &:hover {
      background: rgba(37, 211, 102, 0.2);
    }
  }

  &--instagram {
    background: rgba(225, 48, 108, 0.12);
    color: #e1306c;
    border: 1px solid rgba(225, 48, 108, 0.28);

    &:hover {
      background: rgba(225, 48, 108, 0.2);
    }
  }

  &--facebook {
    background: rgba(24, 119, 242, 0.12);
    color: #1877f2;
    border: 1px solid rgba(24, 119, 242, 0.28);

    &:hover {
      background: rgba(24, 119, 242, 0.2);
    }
  }

  &--tiktok {
    background: rgba(254, 44, 85, 0.12);
    color: #25f4ee;
    border: 1px solid rgba(254, 44, 85, 0.28);

    &:hover {
      background: rgba(254, 44, 85, 0.2);
    }
  }

  &--email {
    background: rgba(99, 102, 241, 0.12);
    color: #818cf8;
    border: 1px solid rgba(99, 102, 241, 0.28);

    &:hover {
      background: rgba(99, 102, 241, 0.2);
    }
  }

  &--manual {
    background: rgba(148, 163, 184, 0.12);
    color: #94a3b8;
    border: 1px solid rgba(148, 163, 184, 0.25);

    &:hover {
      background: rgba(148, 163, 184, 0.2);
    }
  }
}
</style>
