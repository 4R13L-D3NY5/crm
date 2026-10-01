<template>
  <q-layout view="hHh lpR fFf" class="public-layout">
    <q-page-container>
      <q-page class="public-layout__page">
        <div class="public-layout__container">
          <router-view v-slot="{ Component }">
            <transition name="xf-fade" mode="out-in">
              <component :is="Component" />
            </transition>
          </router-view>
        </div>
      </q-page>
    </q-page-container>
  </q-layout>
</template>

<style scoped lang="scss">
.public-layout {
  background-color: var(--crm-bg-app);
  background-image:
    radial-gradient(ellipse 60% 50% at 50% 25%, rgba(16, 185, 129, 0.07) 0%, transparent 60%),
    radial-gradient(ellipse 40% 40% at 50% 75%, rgba(99, 102, 241, 0.04) 0%, transparent 50%);
  min-height: 100vh;
}

.public-layout__page {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 32px 16px;
}

.public-layout__container {
  width: 100%;
  max-width: 420px;
}
</style>
