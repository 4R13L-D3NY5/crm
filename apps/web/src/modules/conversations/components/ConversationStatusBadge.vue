<template>
  <AppStatusBadge
    :status="status"
    :label="label"
  />
</template>

<script setup lang="ts">
import { computed } from 'vue'

import AppStatusBadge from '@/shared/components/AppStatusBadge.vue'

const props = defineProps<{
  status: 'open' | 'pending' | 'resolved'
}>()

const label = computed(() => {
  switch (props.status) {
    case 'open':
      return 'Abierta'
    case 'pending':
      return 'Pendiente'
    case 'resolved':
      return 'Resuelta'
    default:
      return props.status
  }
})
</script>
