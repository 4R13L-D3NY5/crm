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
  padding: 28px;
  border: 1px dashed rgba(122, 226, 231, 0.22);
  border-radius: 28px;
  background:
    radial-gradient(circle at top right, rgba(73, 194, 255, 0.08), transparent 30%),
    rgba(9, 21, 34, 0.74);
}

.app-empty-state__orb {
  display: grid;
  place-items: center;
  width: 56px;
  height: 56px;
  border: 1px solid rgba(122, 226, 231, 0.18);
  border-radius: 18px;
  color: var(--crm-color-primary);
  background: rgba(7, 18, 30, 0.82);
  box-shadow: inset 0 1px 0 rgba(122, 226, 231, 0.08);
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
