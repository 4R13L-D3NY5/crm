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
  padding: 7px 12px;
  border: 1px solid rgba(255, 255, 255, 0.16);
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.1);
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}
</style>
