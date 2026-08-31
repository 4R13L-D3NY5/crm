<template>
  <div class="app-page">
    <div class="app-page__header">
      <div>
        <h1 class="app-page__title">
          {{ title }}
        </h1>
        <p
          v-if="description"
          class="app-page__description"
        >
          {{ description }}
        </p>
      </div>

      <div
        v-if="$slots.actions"
        class="app-page__actions"
      >
        <slot name="actions" />
      </div>
    </div>

    <div class="app-page__body">
      <slot />
    </div>
  </div>
</template>

<script setup lang="ts">
withDefaults(
  defineProps<{
    title: string
    description?: string
    eyebrow?: string
  }>(),
  {
    description: '',
    eyebrow: '',
  },
)
</script>

<style scoped lang="scss">
.app-page {
  display: flex;
  flex-direction: column;
  gap: 20px;
  padding: 24px 32px;
  max-width: 1600px;
  margin: 0 auto;
  width: 100%;
}

.app-page__header {
  display: flex;
  justify-content: space-between;
  gap: 16px;
  align-items: flex-start;
}

.app-page__title {
  margin: 0;
  color: var(--crm-color-ink);
  font-size: 1.5rem;
  font-weight: 700;
  letter-spacing: -0.02em;
}

.app-page__description {
  margin: 4px 0 0;
  color: var(--crm-color-muted);
  font-size: 0.88rem;
  line-height: 1.5;
}

.app-page__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

@media (max-width: 768px) {
  .app-page {
    padding: 16px;
  }

  .app-page__header {
    flex-direction: column;
  }
}
</style>
