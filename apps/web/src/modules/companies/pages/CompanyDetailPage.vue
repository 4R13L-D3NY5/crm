<template>
  <AppPage
    eyebrow="Relacionamiento"
    :title="companyQuery.data.value?.name ?? 'Empresa'"
    description="Vista de detalle de la empresa con contexto comercial, contactos, deals y conversaciones."
  >
    <template #actions>
      <q-chip
        v-if="!canManageCompanies"
        color="primary"
        outline
      >
        Solo lectura
      </q-chip>
      <q-btn
        flat
        label="Volver"
        @click="router.push('/app/companies')"
      />
    </template>

    <AppLoadingState
      v-if="companyQuery.isLoading.value"
      title="Cargando empresa"
      description="Estamos reuniendo contexto comercial, contactos, deals y conversaciones asociadas."
    />

    <AppEmptyState
      v-else-if="!companyQuery.data.value"
      title="No encontramos esta empresa"
      description="Puede que haya sido eliminada o que no pertenezca a la organizacion activa."
      icon="sym_r_domain_disabled"
    />

    <div
      v-else
      class="company-detail"
    >
      <div class="company-detail__main">
        <q-card
          flat
          bordered
          class="company-detail__hero"
        >
          <q-card-section class="company-detail__hero-grid">
            <div>
              <div class="company-detail__label">
                Estado
              </div>
              <AppStatusBadge :status="companyQuery.data.value.status" />
            </div>
            <div>
              <div class="company-detail__label">
                Industria
              </div>
              <div class="company-detail__value">
                {{ companyQuery.data.value.industry ?? 'Sin industria' }}
              </div>
            </div>
            <div>
              <div class="company-detail__label">
                Correo
              </div>
              <div class="company-detail__value">
                {{ companyQuery.data.value.email ?? 'Sin correo' }}
              </div>
            </div>
            <div>
              <div class="company-detail__label">
                Telefono
              </div>
              <div class="company-detail__value">
                {{ companyQuery.data.value.phone ?? 'Sin telefono' }}
              </div>
            </div>
          </q-card-section>
        </q-card>

        <q-card
          flat
          bordered
          class="company-detail__section"
        >
          <q-card-section>
            <div class="company-detail__section-title">
              Contactos asociados
            </div>
            <div
              v-if="relatedContacts.length"
              class="company-detail__stack"
            >
              <router-link
                v-for="contact in relatedContacts"
                :key="contact.id"
                :to="{ name: 'contacts.detail', params: { id: contact.id } }"
                class="company-detail__row-card crm-detail-link crm-detail-link--soft"
              >
                <div class="company-detail__row-top">
                  <div>
                    <div class="company-detail__row-title">
                      {{ contact.name }}
                    </div>
                    <div class="company-detail__meta">
                      {{ contact.email || 'Sin correo' }}
                    </div>
                    <div class="company-detail__meta">
                      {{ contact.phone || 'Sin telefono' }}
                    </div>
                  </div>
                  <AppStatusBadge :status="contact.status ?? 'active'" />
                </div>
              </router-link>
            </div>
            <div
              v-else
              class="company-detail__muted"
            >
              Esta empresa aun no tiene contactos asociados.
            </div>
          </q-card-section>
        </q-card>

        <q-card
          flat
          bordered
          class="company-detail__section"
        >
          <q-card-section>
            <div class="company-detail__section-title">
              Deals relacionados
            </div>
            <div
              v-if="relatedDeals.length"
              class="company-detail__stack"
            >
              <div
                v-for="deal in relatedDeals"
                :key="deal.id"
                class="company-detail__row-card"
              >
                <div class="company-detail__row-top">
                  <div>
                    <div class="company-detail__row-title">
                      {{ deal.name }}
                    </div>
                    <div class="company-detail__meta">
                      {{ deal.contact?.name || 'Sin contacto asociado' }}
                    </div>
                  </div>
                  <AppStatusBadge :status="deal.status" />
                </div>
                <div class="company-detail__row-bottom">
                  <strong>{{ formatCurrency(deal.amount) }}</strong>
                  <span class="company-detail__meta">
                    Cierre: {{ formatShortDate(deal.expected_close_date) }}
                  </span>
                </div>
              </div>
            </div>
            <div
              v-else
              class="company-detail__muted"
            >
              Esta empresa aun no tiene oportunidades comerciales registradas.
            </div>
          </q-card-section>
        </q-card>

        <q-card
          flat
          bordered
          class="company-detail__section"
        >
          <q-card-section>
            <div class="company-detail__section-title">
              Conversaciones relacionadas
            </div>
            <div
              v-if="relatedConversations.length"
              class="company-detail__stack"
            >
              <div
                v-for="conversation in relatedConversations"
                :key="conversation.id"
                class="company-detail__row-card"
              >
                <div class="company-detail__row-top">
                  <div>
                    <div class="company-detail__row-title">
                      {{ conversation.subject || 'Conversacion sin asunto' }}
                    </div>
                    <div class="company-detail__meta">
                      {{ channelLabel(conversation.channel) }} | {{ conversation.assignee?.name || 'Sin asignar' }}
                    </div>
                  </div>
                  <AppStatusBadge :status="conversation.status" />
                </div>
                <div class="company-detail__meta">
                  Ultima actividad: {{ formatDate(conversation.last_message_at) }}
                </div>
              </div>
            </div>
            <div
              v-else
              class="company-detail__muted"
            >
              Esta empresa aun no tiene conversaciones relacionadas.
            </div>
          </q-card-section>
        </q-card>
      </div>

      <div class="company-detail__sidebar">
        <q-card
          flat
          bordered
          class="company-detail__section"
        >
          <q-card-section>
            <div class="company-detail__section-title">
              Resumen rapido
            </div>
            <div class="company-detail__summary-list">
              <div class="company-detail__summary-row">
                <span>Contactos</span>
                <strong>{{ relatedContacts.length }}</strong>
              </div>
              <div class="company-detail__summary-row">
                <span>Conversaciones</span>
                <strong>{{ relatedConversations.length }}</strong>
              </div>
              <div class="company-detail__summary-row">
                <span>Deals</span>
                <strong>{{ relatedDeals.length }}</strong>
              </div>
            </div>
          </q-card-section>
        </q-card>

        <q-card
          flat
          bordered
          class="company-detail__section"
        >
          <q-card-section>
            <div class="company-detail__section-title">
              Sitio web
            </div>
            <div class="company-detail__value">
              {{ companyQuery.data.value.website ?? 'Sin sitio web' }}
            </div>
          </q-card-section>
        </q-card>

        <q-card
          flat
          bordered
          class="company-detail__section"
        >
          <q-card-section>
            <div class="company-detail__section-title">
              Notas
            </div>
            <p class="company-detail__notes">
              {{ companyQuery.data.value.notes ?? 'Sin notas registradas.' }}
            </p>
          </q-card-section>
        </q-card>

        <q-card
          flat
          bordered
          class="company-detail__section"
        >
          <q-card-section>
            <div class="company-detail__section-title">
              Timeline
            </div>
            <div class="company-detail__timeline">
              <div>
                <div class="company-detail__label">
                  Creado
                </div>
                <div class="company-detail__value">
                  {{ formatDate(companyQuery.data.value.created_at) }}
                </div>
              </div>
              <div>
                <div class="company-detail__label">
                  Actualizado
                </div>
                <div class="company-detail__value">
                  {{ formatDate(companyQuery.data.value.updated_at) }}
                </div>
              </div>
            </div>
          </q-card-section>
        </q-card>
      </div>
    </div>
  </AppPage>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'

import { useAuthStore } from '@/modules/auth/stores/auth.store'
import AppEmptyState from '@/shared/components/AppEmptyState.vue'
import AppLoadingState from '@/shared/components/AppLoadingState.vue'
import AppPage from '@/shared/components/AppPage.vue'
import AppStatusBadge from '@/shared/components/AppStatusBadge.vue'

import { useCompany } from '../composables/useCompanies'

const props = defineProps<{
  id: string
}>()

const authStore = useAuthStore()
const router = useRouter()
const companyQuery = useCompany(() => props.id)
const canManageCompanies = computed(() => authStore.hasPermission('companies.manage'))
const relatedContacts = computed(() => companyQuery.data.value?.contacts ?? [])
const relatedDeals = computed(() => companyQuery.data.value?.deals ?? [])
const relatedConversations = computed(() => companyQuery.data.value?.conversations ?? [])

function formatDate(value?: string | null) {
  if (!value) {
    return 'Sin fecha'
  }

  return new Intl.DateTimeFormat('es-BO', {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(new Date(value))
}

function formatShortDate(value?: string | null) {
  if (!value) {
    return 'Sin fecha'
  }

  return new Intl.DateTimeFormat('es-BO', {
    dateStyle: 'medium',
  }).format(new Date(value))
}

function formatCurrency(value: number) {
  return new Intl.NumberFormat('es-BO', {
    style: 'currency',
    currency: 'BOB',
    maximumFractionDigits: 2,
  }).format(value)
}

function channelLabel(channel: 'manual' | 'whatsapp' | 'email') {
  const labels = {
    manual: 'Manual',
    whatsapp: 'WhatsApp',
    email: 'Email',
  }

  return labels[channel] ?? channel
}
</script>

<style scoped lang="scss">
.company-detail {
  display: grid;
  grid-template-columns: minmax(0, 2fr) minmax(320px, 1fr);
  gap: 20px;
}

.company-detail__main,
.company-detail__sidebar {
  display: grid;
  gap: 20px;
}

.company-detail__hero,
.company-detail__section {
  border-radius: var(--crm-radius-card);
  background: var(--crm-bg-card);
  border: 1px solid var(--crm-color-border);
}


.company-detail__hero-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 20px;
}

.company-detail__label {
  margin-bottom: 8px;
  color: var(--crm-color-muted);
  font-size: 0.78rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.company-detail__section-title {
  color: var(--crm-color-ink);
  font-size: 1rem;
  font-weight: 700;
}

.company-detail__value {
  color: var(--crm-color-ink);
  font-weight: 600;
}

.company-detail__meta,
.company-detail__muted,
.company-detail__notes {
  color: var(--crm-color-muted);
  line-height: 1.6;
}

.company-detail__stack {
  display: grid;
  gap: 12px;
  margin-top: 16px;
}

.company-detail__row-card {
  display: grid;
  gap: 8px;
  padding: 14px 16px;
  border: 1px solid var(--crm-color-border);
  border-radius: var(--crm-radius-control);
  background: var(--crm-bg-card-hover);
}


.company-detail__row-top,
.company-detail__row-bottom,
.company-detail__summary-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.company-detail__row-title {
  color: var(--crm-color-ink);
  font-weight: 700;
}

.company-detail__row-card :deep(.app-status-badge) {
  justify-self: end;
}

.company-detail__summary-list,
.company-detail__timeline {
  display: grid;
  gap: 14px;
  margin-top: 16px;
}

.company-detail__summary-row strong {
  color: var(--crm-color-ink);
}

@media (max-width: 960px) {
  .company-detail {
    grid-template-columns: 1fr;
  }

  .company-detail__hero-grid {
    grid-template-columns: 1fr;
  }

  .company-detail__row-top,
  .company-detail__row-bottom,
  .company-detail__summary-row {
    align-items: start;
    flex-direction: column;
  }
}
</style>
