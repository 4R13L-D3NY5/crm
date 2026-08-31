<template>
  <div class="app-loading-state">
    <q-spinner
      color="primary"
      size="34px"
    />
    <div class="app-loading-state__title">
      {{ title }}
    </div>
    <p
      v-if="description"
      class="app-loading-state__description"
    >
      {{ description }}
    </p>
  </div>
</template>

<script setup lang="ts">
withDefaults(
  defineProps<{
    title?: string
    description?: string
  }>(),
  {
    title: 'Cargando contenido',
    description: '',
  },
)
</script>

<style scoped lang="scss">
.app-loading-state {
  display: grid;
  justify-items: start;
  gap: 12px;
  padding: 28px;
  border: 1px solid rgba(122, 226, 231, 0.14);
  border-radius: 28px;
  background:
    radial-gradient(circle at top right, rgba(73, 194, 255, 0.08), transparent 30%),
    rgba(9, 21, 34, 0.74);
}

.app-loading-state__title {
  color: var(--crm-color-ink);
  font-size: 1rem;
  font-weight: 700;
}

.app-loading-state__description {
  margin: 0;
  color: var(--crm-color-muted);
  line-height: 1.65;
}
</style>
