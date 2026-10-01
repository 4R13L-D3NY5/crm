<template>
  <q-table
    :rows="rows"
    :columns="columns"
    :loading="loading"
    :pagination="pagination"
    flat
    bordered
    row-key="id"
    class="app-table"
    v-bind="$attrs"
  >
    <template
      v-for="(_, slotName) in $slots"
      #[slotName]="slotProps"
    >
      <slot
        :name="slotName"
        v-bind="slotProps"
      />
    </template>
  </q-table>
</template>

<script setup lang="ts">
import type { QTableProps } from 'quasar'

defineOptions({
  inheritAttrs: false,
})

defineProps<{
  rows: readonly unknown[]
  columns: QTableProps['columns']
  loading?: boolean
  pagination?: QTableProps['pagination']
}>()
</script>

<style scoped lang="scss">
.app-table {
  border: 1px solid var(--crm-color-border);
  border-radius: var(--crm-radius-card);
  background: var(--crm-bg-card);
  box-shadow: var(--crm-shadow-card);
}

</style>
