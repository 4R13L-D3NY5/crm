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
  border-radius: 28px;
  background:
    radial-gradient(circle at top right, rgba(73, 194, 255, 0.08), transparent 24%),
    linear-gradient(180deg, rgba(13, 26, 42, 0.96) 0%, rgba(9, 20, 33, 0.94) 100%);
  box-shadow: var(--crm-shadow-soft);
  backdrop-filter: blur(14px);
}
</style>
