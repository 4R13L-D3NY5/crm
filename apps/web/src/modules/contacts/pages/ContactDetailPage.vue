<template>
  <AppPage
    eyebrow="Relacionamiento"
    :title="contactQuery.data.value?.name ?? 'Contacto'"
    description="Vista de detalle del contacto con contexto y notas."
  >
    <template #actions>
      <q-chip
        v-if="!canManageContacts"
        color="primary"
        outline
      >
        Solo lectura
      </q-chip>
      <q-btn
        flat
        label="Volver"
        @click="router.push('/app/contacts')"
      />
    </template>

    <AppLoadingState
      v-if="contactQuery.isLoading.value"
      title="Cargando contacto"
      description="Estamos preparando el contexto, etiquetas y timeline del contacto."
    />

    <AppEmptyState
      v-else-if="!contactQuery.data.value"
      title="No encontramos este contacto"
      description="Puede que haya sido eliminado o que no pertenezca a la organizacion activa."
      icon="sym_r_person_off"
    />

    <div
      v-else
      class="contact-detail"
    >
      <div class="contact-detail__main">
        <q-card
          flat
          bordered
          class="contact-detail__hero"
        >
          <q-card-section class="contact-detail__hero-grid">
            <div>
              <div class="contact-detail__label">
                Estado
              </div>
              <AppStatusBadge :status="contactQuery.data.value?.status ?? 'active'" />
            </div>
            <div>
              <div class="contact-detail__label">
                Correo
              </div>
              <div class="contact-detail__value">
                {{ contactQuery.data.value?.email ?? 'Sin correo' }}
              </div>
            </div>
            <div>
              <div class="contact-detail__label">
                Telefono
              </div>
              <div class="contact-detail__value">
                {{ contactQuery.data.value?.phone ?? 'Sin telefono' }}
              </div>
            </div>
            <div>
              <div class="contact-detail__label">
                Empresa principal
              </div>
              <div class="contact-detail__value">
                {{ primaryCompany?.name ?? 'Sin empresa asociada' }}
              </div>
              <div
                v-if="primaryCompany?.industry"
                class="contact-detail__meta"
              >
                {{ primaryCompany.industry }}
              </div>
            </div>
          </q-card-section>
        </q-card>

        <q-card
          flat
          bordered
          class="contact-detail__section"
        >
          <q-card-section>
            <div class="contact-detail__section-title">
              Etiquetas y contexto comercial
            </div>
            <div class="contact-detail__tags">
              <q-chip
                v-for="tag in contactQuery.data.value?.tags ?? []"
                :key="tag.id"
                dense
                outline
              >
                {{ tag.name }}
              </q-chip>
              <span
                v-if="!(contactQuery.data.value?.tags?.length ?? 0)"
                class="contact-detail__muted"
              >
                Sin etiquetas registradas.
              </span>
            </div>
          </q-card-section>
        </q-card>

        <q-card
          flat
          bordered
          class="contact-detail__section"
        >
          <q-card-section>
            <div class="contact-detail__section-title">
              Conversaciones relacionadas
            </div>
            <div
              v-if="relatedConversations.length"
              class="contact-detail__stack"
            >
              <div
                v-for="conversation in relatedConversations"
                :key="conversation.id"
                class="contact-detail__row-card"
              >
                <div class="contact-detail__row-top">
                  <div>
                    <div class="contact-detail__row-title">
                      {{ conversation.subject || 'Conversacion sin asunto' }}
                    </div>
                    <div class="contact-detail__meta">
                      {{ channelLabel(conversation.channel) }} · {{ conversation.assignee?.name || 'Sin asignar' }}
                    </div>
                  </div>
                  <AppStatusBadge :status="conversation.status" />
                </div>
                <div class="contact-detail__meta">
                  Ultima actividad: {{ formatDate(conversation.last_message_at) }}
                </div>
              </div>
            </div>
            <div
              v-else
              class="contact-detail__muted"
            >
              Este contacto aun no tiene conversaciones relacionadas.
            </div>
          </q-card-section>
        </q-card>

        <q-card
          flat
          bordered
          class="contact-detail__section"
        >
          <q-card-section>
            <div class="contact-detail__section-title">
              Deals relacionados
            </div>
            <div
              v-if="relatedDeals.length"
              class="contact-detail__stack"
            >
              <div
                v-for="deal in relatedDeals"
                :key="deal.id"
                class="contact-detail__row-card"
              >
                <div class="contact-detail__row-top">
                  <div>
                    <div class="contact-detail__row-title">
                      {{ deal.name }}
                    </div>
                    <div class="contact-detail__meta">
                      {{ deal.company?.name || 'Sin empresa asociada' }}
                    </div>
                  </div>
                  <AppStatusBadge :status="deal.status" />
                </div>
                <div class="contact-detail__row-bottom">
                  <strong>{{ formatCurrency(deal.amount) }}</strong>
                  <span class="contact-detail__meta">
                    Cierre: {{ formatShortDate(deal.expected_close_date) }}
                  </span>
                </div>
              </div>
            </div>
            <div
              v-else
              class="contact-detail__muted"
            >
              Este contacto aun no tiene oportunidades comerciales registradas.
            </div>
          </q-card-section>
        </q-card>
      </div>

      <div class="contact-detail__sidebar">
        <q-card
          flat
          bordered
          class="contact-detail__section"
        >
          <q-card-section>
            <div class="contact-detail__section-title">
              Resumen rapido
            </div>
            <div class="contact-detail__summary-list">
              <div class="contact-detail__summary-row">
                <span>Empresas asociadas</span>
                <strong>{{ relatedCompanies.length }}</strong>
              </div>
              <div class="contact-detail__summary-row">
                <span>Conversaciones</span>
                <strong>{{ relatedConversations.length }}</strong>
              </div>
              <div class="contact-detail__summary-row">
                <span>Deals</span>
                <strong>{{ relatedDeals.length }}</strong>
              </div>
            </div>
          </q-card-section>
        </q-card>

        <q-card
          flat
          bordered
          class="contact-detail__section"
        >
          <q-card-section>
            <div class="contact-detail__section-title">
              Empresas asociadas
            </div>
            <div
              v-if="relatedCompanies.length"
              class="contact-detail__stack"
            >
              <router-link
                v-for="company in relatedCompanies"
                :key="company.id"
                :to="{ name: 'companies.detail', params: { id: company.id } }"
                class="contact-detail__company-link crm-detail-link crm-detail-link--soft"
              >
                <div>
                  <div class="contact-detail__row-title">
                    {{ company.name }}
                  </div>
                  <div class="contact-detail__meta">
                    {{ company.industry || 'Sin industria' }}
                  </div>
                </div>
              </router-link>
            </div>
            <div
              v-else
              class="contact-detail__muted"
            >
              Este contacto no tiene empresas asociadas.
            </div>
          </q-card-section>
        </q-card>

        <q-card
          flat
          bordered
          class="contact-detail__section"
        >
          <q-card-section>
            <div class="contact-detail__section-title">
              Notas
            </div>
            <p class="contact-detail__notes">
              {{ contactQuery.data.value?.notes ?? 'Sin notas registradas.' }}
            </p>
          </q-card-section>
        </q-card>

        <q-card
          flat
          bordered
          class="contact-detail__section"
        >
          <q-card-section>
            <div class="contact-detail__section-title">
              Timeline
            </div>
            <div class="contact-detail__timeline">
              <div>
                <div class="contact-detail__label">
                  Creado
                </div>
                <div class="contact-detail__value">
                  {{ formatDate(contactQuery.data.value?.created_at) }}
                </div>
              </div>
              <div>
                <div class="contact-detail__label">
                  Actualizado
                </div>
                <div class="contact-detail__value">
                  {{ formatDate(contactQuery.data.value?.updated_at) }}
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

import { useContact } from '../composables/useContacts'

const props = defineProps<{
  id: string
}>()

const authStore = useAuthStore()
const router = useRouter()
const contactQuery = useContact(() => props.id)
const canManageContacts = computed(() => authStore.hasPermission('contacts.manage'))
const relatedCompanies = computed(() => contactQuery.data.value?.companies ?? [])
const primaryCompany = computed(() => relatedCompanies.value[0] ?? null)
const relatedConversations = computed(() => contactQuery.data.value?.conversations ?? [])
const relatedDeals = computed(() => contactQuery.data.value?.deals ?? [])

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
.contact-detail {
  display: grid;
  grid-template-columns: minmax(0, 2fr) minmax(320px, 1fr);
  gap: 20px;
}

.contact-detail__main,
.contact-detail__sidebar {
  display: grid;
  gap: 20px;
}

.contact-detail__hero,
.contact-detail__section {
  border-radius: 24px;
  background:
    radial-gradient(circle at top right, rgba(73, 194, 255, 0.08), transparent 24%),
    linear-gradient(180deg, rgba(13, 26, 42, 0.94) 0%, rgba(9, 20, 33, 0.92) 100%);
}

.contact-detail__hero-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 20px;
}

.contact-detail__label {
  margin-bottom: 8px;
  color: var(--crm-color-muted);
  font-size: 0.78rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.contact-detail__section-title {
  color: var(--crm-color-ink);
  font-size: 1rem;
  font-weight: 700;
}

.contact-detail__value {
  color: var(--crm-color-ink);
  font-weight: 600;
}

.contact-detail__meta,
.contact-detail__muted,
.contact-detail__notes {
  color: var(--crm-color-muted);
  line-height: 1.6;
}

.contact-detail__tags {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.contact-detail__stack {
  display: grid;
  gap: 12px;
  margin-top: 16px;
}

.contact-detail__row-card,
.contact-detail__company-link {
  display: grid;
  gap: 8px;
  padding: 16px;
  border: 1px solid rgba(118, 198, 255, 0.1);
  border-radius: 18px;
  background: rgba(12, 24, 39, 0.72);
}

.contact-detail__company-link {
  text-decoration: none;
}

.contact-detail__row-top,
.contact-detail__row-bottom,
.contact-detail__summary-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.contact-detail__row-title {
  color: var(--crm-color-ink);
  font-weight: 700;
}

.contact-detail__summary-list,
.contact-detail__timeline {
  display: grid;
  gap: 14px;
  margin-top: 16px;
}

.contact-detail__summary-row strong {
  color: var(--crm-color-ink);
}

@media (max-width: 960px) {
  .contact-detail {
    grid-template-columns: 1fr;
  }

  .contact-detail__hero-grid {
    grid-template-columns: 1fr;
  }

  .contact-detail__row-top,
  .contact-detail__row-bottom,
  .contact-detail__summary-row {
    align-items: start;
    flex-direction: column;
  }
}
</style>
