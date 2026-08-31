<template>
  <q-layout
    view="hHh lpR fFf"
    class="public-layout"
  >
    <q-page-container>
      <q-page class="public-layout__page">
        <div class="public-layout__hero">
          <div class="public-layout__hero-inner">
            <div class="public-layout__brand">
              CRM Platform
            </div>
            <h1>Conversaciones, ventas y contexto con una presencia sobria.</h1>
            <p>
              Una operacion comercial unificada, con inbox, deals y automatizaciones
              en una experiencia clara, ligera y preparada para crecer.
            </p>
            <div class="public-layout__hero-pills">
              <q-chip
                outline
                icon="sym_r_forum"
                class="public-layout__pill"
              >
                Inbox compartido
              </q-chip>
              <q-chip
                outline
                icon="sym_r_stacks"
                class="public-layout__pill"
              >
                Pipeline comercial
              </q-chip>
              <q-chip
                outline
                icon="sym_r_auto_awesome"
                class="public-layout__pill"
              >
                Automatizaciones
              </q-chip>
            </div>
          </div>
        </div>

        <div class="public-layout__card">
          <router-view v-slot="{ Component }">
            <transition
              name="crm-page"
              mode="out-in"
            >
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
  background:
    radial-gradient(circle at top left, rgba(53, 194, 255, 0.24), transparent 28%),
    radial-gradient(circle at 80% 10%, rgba(138, 143, 255, 0.16), transparent 18%),
    linear-gradient(135deg, rgba(255, 255, 255, 0.02), rgba(255, 255, 255, 0));
}

.public-layout__page {
  min-height: 100vh;
  display: grid;
  grid-template-columns: minmax(0, 1.2fr) minmax(320px, 520px);
  gap: 44px;
  align-items: center;
  padding: 48px;
}

.public-layout__hero {
  color: var(--crm-color-ink);
}

.public-layout__hero-inner {
  max-width: 700px;
}

.public-layout__brand {
  display: inline-flex;
  align-items: center;
  margin-bottom: 22px;
  padding: 10px 14px;
  border: 1px solid rgba(136, 240, 255, 0.16);
  border-radius: 999px;
  color: var(--crm-color-primary);
  font-size: 0.78rem;
  font-weight: 800;
  letter-spacing: 0.16em;
  text-transform: uppercase;
  background: rgba(10, 22, 35, 0.52);
  backdrop-filter: blur(10px);
  box-shadow: inset 0 1px 0 rgba(136, 240, 255, 0.08);
}

.public-layout__hero h1 {
  margin: 0 0 20px;
  font-family: var(--crm-font-display);
  font-size: clamp(3rem, 6vw, 5.2rem);
  line-height: 0.92;
  letter-spacing: -0.04em;
}

.public-layout__hero p {
  margin: 0;
  max-width: 52ch;
  color: var(--crm-color-muted);
  font-size: 1.08rem;
  line-height: 1.75;
}

.public-layout__hero-pills {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  margin-top: 28px;
}

.public-layout__pill {
  color: var(--crm-color-ink);
  background: rgba(10, 22, 35, 0.58);
  border-color: rgba(122, 226, 231, 0.16);
  box-shadow: inset 0 1px 0 rgba(136, 240, 255, 0.08);
}

.public-layout__card {
  padding: 34px;
  border: 1px solid rgba(136, 240, 255, 0.12);
  border-radius: 32px;
  background: rgba(7, 18, 30, 0.72);
  box-shadow: var(--crm-shadow-card);
  backdrop-filter: blur(18px);
}

@media (max-width: 960px) {
  .public-layout__page {
    grid-template-columns: 1fr;
    padding: 24px;
  }

  .public-layout__card {
    padding: 24px;
  }
}
</style>
