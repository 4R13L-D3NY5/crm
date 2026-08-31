<template>
  <div class="whaticket-docs-page">
    <div class="whaticket-docs-container">
      <!-- Header Swagger (Exacto a la Captura) -->
      <div class="q-mb-lg">
        <div class="row items-center q-gutter-x-sm">
          <h1 class="text-h4 text-bold text-white q-my-none">Whaticket - API</h1>
          <q-badge color="grey-7" class="text-bold">1.0.0</q-badge>
          <q-badge color="positive" class="text-bold">OAS 3.0</q-badge>
        </div>

        <p class="text-caption text-grey-4 q-mt-sm" style="max-width: 750px">
          This API provides an interface for sending messages through connections connected to Whaticket.
          All routes require a Bearer token. You can create one on Tokens page.
        </p>

        <div class="row items-center justify-between q-mt-md">
          <div class="row items-center q-gutter-x-sm">
            <span class="text-caption text-grey-4">Servers:</span>
            <q-select
              v-model="selectedServer"
              :options="['http://localhost:8010/api/v1', 'https://api.whaticket.com/api/v1']"
              dense
              outlined
              dark
              style="min-width: 240px"
            />
          </div>

          <q-btn
            outline
            color="positive"
            icon="sym_r_lock"
            label="Authorize"
            no-caps
            class="text-bold"
          />
        </div>
      </div>

      <!-- 1. Sección Contacts -->
      <div class="whaticket-swagger-section q-mb-md">
        <div class="row items-center justify-between whaticket-swagger-section-header">
          <span class="text-bold text-white text-h6">Contacts</span>
          <q-icon name="sym_r_expand_more" color="grey-4" />
        </div>

        <div class="q-gutter-y-xs q-mt-sm">
          <!-- GET /contacts -->
          <div class="whaticket-endpoint whaticket-endpoint--get">
            <span class="whaticket-method whaticket-method--get">GET</span>
            <span class="whaticket-path">/contacts</span>
            <span class="whaticket-desc text-grey-4">List contacts</span>
            <q-space />
            <q-icon name="sym_r_lock" size="16px" color="grey-4" />
          </div>

          <!-- POST /contacts -->
          <div class="whaticket-endpoint whaticket-endpoint--post">
            <span class="whaticket-method whaticket-method--post">POST</span>
            <span class="whaticket-path">/contacts</span>
            <span class="whaticket-desc text-grey-4">Creates a new contact</span>
            <q-space />
            <q-icon name="sym_r_lock" size="16px" color="grey-4" />
          </div>

          <!-- POST /contacts/create-or-update -->
          <div class="whaticket-endpoint whaticket-endpoint--post">
            <span class="whaticket-method whaticket-method--post">POST</span>
            <span class="whaticket-path">/contacts/create-or-update</span>
            <span class="whaticket-desc text-grey-4">Create or update a contact</span>
            <q-space />
            <q-icon name="sym_r_lock" size="16px" color="grey-4" />
          </div>
        </div>
      </div>

      <!-- 2. Sección WhatsApps -->
      <div class="whaticket-swagger-section q-mb-md">
        <div class="row items-center justify-between whaticket-swagger-section-header">
          <span class="text-bold text-white text-h6">WhatsApps</span>
          <q-icon name="sym_r_expand_more" color="grey-4" />
        </div>

        <div class="q-gutter-y-xs q-mt-sm">
          <!-- GET /whatsapps -->
          <div class="whaticket-endpoint whaticket-endpoint--get">
            <span class="whaticket-method whaticket-method--get">GET</span>
            <span class="whaticket-path">/whatsapps</span>
            <span class="whaticket-desc text-grey-4">List whatsapp connections</span>
            <q-space />
            <q-icon name="sym_r_lock" size="16px" color="grey-4" />
          </div>

          <!-- PATCH /whatsapps/start/{id} -->
          <div class="whaticket-endpoint whaticket-endpoint--patch">
            <span class="whaticket-method whaticket-method--patch">PATCH</span>
            <span class="whaticket-path">/whatsapps/start/{id}</span>
            <span class="whaticket-desc text-grey-4">Restarts a whatsapp session</span>
            <q-space />
            <q-icon name="sym_r_lock" size="16px" color="grey-4" />
          </div>
        </div>
      </div>

      <!-- 3. Sección Messages -->
      <div class="whaticket-swagger-section">
        <div class="row items-center justify-between whaticket-swagger-section-header">
          <span class="text-bold text-white text-h6">Messages</span>
          <q-icon name="sym_r_expand_more" color="grey-4" />
        </div>

        <div class="q-gutter-y-xs q-mt-sm">
          <!-- POST /messages/send -->
          <div class="whaticket-endpoint whaticket-endpoint--post">
            <span class="whaticket-method whaticket-method--post">POST</span>
            <span class="whaticket-path">/messages/send</span>
            <span class="whaticket-desc text-grey-4">Send a text or media message</span>
            <q-space />
            <q-icon name="sym_r_lock" size="16px" color="grey-4" />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'

const selectedServer = ref('http://localhost:8010/api/v1')
</script>

<style scoped lang="scss">
.whaticket-docs-page {
  padding: 32px;
  background-color: var(--crm-bg-app);
  min-height: calc(100vh - 52px);
}

.whaticket-docs-container {
  max-width: 960px;
  margin: 0 auto;
  background-color: #111b21;
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 14px;
  padding: 28px;
}

.whaticket-swagger-section-header {
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
  padding-bottom: 8px;
}

.whaticket-endpoint {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 8px 12px;
  border-radius: 8px;
  border: 1px solid rgba(255, 255, 255, 0.06);
  background-color: #182229;
  cursor: pointer;
  transition: all 0.15s ease;

  &:hover {
    background-color: #202c33;
  }

  &--get { border-color: rgba(56, 189, 248, 0.3); }
  &--post { border-color: rgba(34, 197, 94, 0.3); }
  &--patch { border-color: rgba(20, 184, 166, 0.3); }
}

.whaticket-method {
  font-size: 0.72rem;
  font-weight: 800;
  padding: 3px 8px;
  border-radius: 4px;
  color: #ffffff;

  &--get { background-color: #0284c7; }
  &--post { background-color: #16a34a; }
  &--patch { background-color: #0d9488; }
}

.whaticket-path {
  font-family: monospace;
  font-size: 0.88rem;
  font-weight: 700;
  color: #ffffff;
}

.whaticket-desc {
  font-size: 0.82rem;
}
</style>
