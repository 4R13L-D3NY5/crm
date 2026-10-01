<template>
  <div class="app-empty-state">
    <div class="app-empty-state__orb">
      <q-icon
        :name="icon"
        size="28px"
      />
    </div>
    <div class="app-empty-state__title">
      {{ title }}
    </div>
    <p
      v-if="description"
      class="app-empty-state__description"
    >
      {{ description }}
    </p>
    <div
      v-if="$slots.actions"
      class="app-empty-state__actions"
    >
      <slot name="actions" />
    </div>
  </div>
</template>

<script setup lang="ts">
withDefaults(
  defineProps<{
    title: string
    description?: string
    icon?: string
  }>(),
  {
    description: '',
    icon: 'sym_r_explore',
  },
)
</script>

<style scoped lang="scss">
.app-empty-state {
  display: grid;
  justify-items: start;
  gap: 14px;
  padding: 32px;
  border: 1px dashed var(--crm-color-border-hover);
  border-radius: var(--crm-radius-card);
  background: var(--crm-bg-card);
}

.app-empty-state__orb {
  display: grid;
  place-items: center;
  width: 48px;
  height: 48px;
  border: 1px solid var(--crm-color-border);
  border-radius: 10px;
  color: var(--crm-color-primary);
  background: var(--crm-color-primary-soft);
}


.app-empty-state__title {
  color: var(--crm-color-ink);
  font-size: 1.08rem;
  font-weight: 700;
}

.app-empty-state__description {
  max-width: 54ch;
  margin: 0;
  color: var(--crm-color-muted);
  line-height: 1.7;
}

.app-empty-state__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}
</style>
