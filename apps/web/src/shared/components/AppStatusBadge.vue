<template>
  <q-badge
    rounded
    :color="palette.color"
    :text-color="palette.textColor"
    class="app-status-badge"
  >
    {{ label }}
  </q-badge>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  status: string
  label?: string
}>()

const palette = computed(() => {
  switch (props.status) {
    case 'active':
      return { color: 'positive', textColor: 'white' }
    case 'lead':
      return { color: 'secondary', textColor: 'white' }
    case 'inactive':
      return { color: 'grey-7', textColor: 'white' }
    case 'open':
      return { color: 'primary', textColor: 'white' }
    case 'pending':
      return { color: 'warning', textColor: 'white' }
    case 'resolved':
      return { color: 'positive', textColor: 'white' }
    case 'won':
      return { color: 'positive', textColor: 'white' }
    case 'lost':
      return { color: 'negative', textColor: 'white' }
    default:
      return { color: 'primary', textColor: 'white' }
  }
})

const label = computed(() => props.label ?? props.status)
</script>

<style scoped lang="scss">
.app-status-badge {
  padding: 4px 10px;
  border-radius: var(--crm-radius-pill);
  font-size: 0.72rem;
  font-weight: 600;
  letter-spacing: 0.02em;
  text-transform: capitalize;
}

</style>
