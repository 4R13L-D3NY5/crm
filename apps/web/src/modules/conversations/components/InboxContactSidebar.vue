<template>
  <aside class="inbox-contact-sidebar">
    <div class="inbox-contact-sidebar__header q-pa-md row items-center justify-between">
      <span class="text-subtitle2 text-weight-bold text-white">Detalle del Cliente</span>
      <q-btn flat round dense icon="sym_r_close" color="grey-4" size="sm" @click="emit('close')" />
    </div>

    <div class="inbox-contact-sidebar__content q-pa-md scroll">
      <!-- Tarjeta Principal del Perfil -->
      <div class="column items-center text-center q-mb-md">
        <q-avatar size="56px" class="contact-profile-avatar q-mb-sm">
          {{ getInitials(conversation.contact?.name || conversation.subject || 'W') }}
        </q-avatar>

        <div class="text-subtitle1 text-weight-bold text-white">
          {{ conversation.contact?.name || conversation.subject || 'Cliente' }}
        </div>

        <div v-if="conversation.contact?.phone" class="text-caption text-grey-4 font-mono q-mt-xs">
          {{ conversation.contact.phone }}
        </div>

        <div class="row items-center q-gutter-x-sm q-mt-sm">
          <q-btn
            v-if="conversation.contact?.phone"
            unelevated
            dense
            size="sm"
            color="positive"
            icon="sym_r_chat"
            label="WhatsApp Web"
            no-caps
            class="q-px-sm"
            :href="`https://wa.me/${cleanPhone(conversation.contact.phone)}`"
            target="_blank"
          />
        </div>
      </div>

      <q-separator dark class="q-my-md" style="border-color: var(--crm-color-border)" />

      <!-- Metadatos de la Conversación -->
      <div class="q-gutter-y-sm">
        <div class="info-row">
          <span class="info-label">Canal / Red:</span>
          <SocialChannelBadge
            :channel="conversation.channel"
            :account-name="conversation.channel_account?.name"
            :phone-number="conversation.channel_account?.display_phone_number"
            size="xs"
          />
        </div>

        <div v-if="conversation.channel_account?.display_phone_number || conversation.channel_account?.name" class="info-row">
          <span class="info-label">Línea Receptora:</span>
          <div class="info-val text-right">
            <span class="text-teal-3 text-weight-bold font-mono">
              {{ conversation.channel_account.display_phone_number || conversation.channel_account.name }}
            </span>
            <span v-if="conversation.channel_account.display_phone_number && conversation.channel_account.name" class="text-caption text-grey-4 block" style="font-size: 0.72rem">
              {{ conversation.channel_account.name }}
            </span>
          </div>
        </div>
        <div v-else-if="conversation.channel === 'whatsapp'" class="info-row">
          <span class="info-label">Línea Receptora:</span>
          <span class="info-val text-teal-3 text-weight-medium">Línea Principal WhatsApp</span>
        </div>

        <div class="info-row">
          <span class="info-label">Estado del Ticket:</span>
          <q-badge :color="statusBadgeColor" class="q-px-xs">
            {{ statusBadgeLabel }}
          </q-badge>
        </div>

        <div class="info-row">
          <span class="info-label">Estado del Lead:</span>
          <q-chip
            clickable
            dense
            dark
            :style="{ backgroundColor: currentCustomStatus?.color ? currentCustomStatus.color + '22' : 'rgba(16,185,129,0.15)', borderColor: currentCustomStatus?.color || '#10b981', border: '1px solid' }"
            @click="openChangeStatusDialog"
          >
            <span class="xf-dot-indicator q-mr-xs" :style="{ backgroundColor: currentCustomStatus?.color || '#10b981' }"></span>
            <span class="text-caption text-white text-weight-bold">
              {{ currentCustomStatus?.name || 'Sin estado' }}
            </span>
            <q-icon name="sym_r_arrow_drop_down" size="14px" class="q-ml-xs text-grey-4" />
          </q-chip>
        </div>

        <div class="info-row">
          <span class="info-label">Agente Asignado:</span>
          <span class="info-val">{{ conversation.assignee?.name || conversation.assignment?.name || 'Sin asignar' }}</span>
        </div>

        <div v-if="conversation.company" class="info-row">
          <span class="info-label">Empresa B2B:</span>
          <span class="info-val text-primary">{{ conversation.company.name }}</span>
        </div>
      </div>

      <!-- Extracción Inteligente de Datos a la Ficha del Contacto (Fase 4) -->
      <div v-if="aiExtractedData?.has_new_data && !isExtractedDataDismissed" class="q-my-md">
        <q-card flat bordered class="q-pa-sm rounded-borders" style="background: rgba(13, 148, 136, 0.09); border: 1px solid rgba(13, 148, 136, 0.38);">
          <div class="row items-center justify-between q-mb-xs">
            <span class="text-caption text-weight-bold text-teal-3 row items-center q-gutter-x-xs">
              <q-icon name="sym_r_auto_awesome" size="16px" color="teal-3" />
              <span>Datos Detectados en el Chat</span>
            </span>
            <q-badge color="teal-9" text-color="teal-1" size="xs">IA Ficha</q-badge>
          </div>

          <div class="q-gutter-y-xs text-caption text-grey-2 q-my-xs font-sans" style="font-size: 0.78rem">
            <div v-if="aiExtractedData.first_name" class="row items-center q-gutter-x-xs">
              <q-icon name="sym_r_person" size="14px" color="teal-4" />
              <span>Nombre: <strong class="text-white">{{ aiExtractedData.first_name }}</strong></span>
            </div>
            <div v-if="aiExtractedData.ci" class="row items-center q-gutter-x-xs">
              <q-icon name="sym_r_badge" size="14px" color="amber-4" />
              <span>CI: <strong class="text-amber-3 font-mono">{{ aiExtractedData.ci }}</strong></span>
            </div>
            <div v-if="aiExtractedData.email" class="row items-center q-gutter-x-xs">
              <q-icon name="sym_r_mail" size="14px" color="blue-4" />
              <span>Email: <strong class="text-blue-3 font-mono">{{ aiExtractedData.email }}</strong></span>
            </div>
            <div v-if="aiExtractedData.city" class="row items-center q-gutter-x-xs">
              <q-icon name="sym_r_location_on" size="14px" color="purple-3" />
              <span>Ciudad: <strong class="text-white">{{ aiExtractedData.city }}</strong></span>
            </div>
            <div v-if="aiExtractedData.alternative_phone" class="row items-center q-gutter-x-xs">
              <q-icon name="sym_r_call" size="14px" color="green-4" />
              <span>Tel. Alt: <strong class="text-white font-mono">{{ aiExtractedData.alternative_phone }}</strong></span>
            </div>
          </div>

          <div class="row items-center q-gutter-x-xs q-mt-xs">
            <q-btn
              unelevated
              dense
              no-caps
              size="xs"
              color="teal-7"
              icon="sym_r_save"
              label="Actualizar Ficha"
              class="col"
              :loading="isSavingExtractedData"
              @click="handleApplyExtractedData"
            />
            <q-btn flat round dense size="xs" color="grey-4" icon="sym_r_close" @click="isExtractedDataDismissed = true" />
          </div>
        </q-card>
      </div>

      <q-separator dark class="q-my-md" style="border-color: var(--crm-color-border)" />

      <!-- Categorías & Subcategorías (Clasificación del Lead) -->
      <div class="q-mb-md">
        <div class="row items-center justify-between q-mb-xs">
          <span class="text-caption text-weight-bold text-white">Interés & Clasificación</span>
          <q-btn
            flat
            dense
            round
            size="xs"
            color="teal-4"
            icon="sym_r_add"
            @click="openAssignDialog"
          >
            <q-tooltip>Asignar categoría o subcategoría</q-tooltip>
          </q-btn>
        </div>

        <div v-if="loadingCategories" class="q-py-xs text-center">
          <q-spinner size="18px" color="primary" />
        </div>

        <div v-else-if="assignedCategories.length === 0" class="text-caption text-grey-5 italic q-py-xs">
          Sin categorías asignadas.
        </div>

        <div v-else class="row q-gutter-xs q-mt-xs">
          <q-chip
            v-for="cat in assignedCategories"
            :key="cat.id"
            dense
            removable
            dark
            :style="{ backgroundColor: cat.color ? cat.color + '22' : 'rgba(16,185,129,0.15)', borderColor: cat.color || '#10b981' }"
            style="border: 1px solid;"
            @remove="removeCategory(cat.id)"
          >
            <q-avatar size="16px" :style="{ backgroundColor: cat.color || '#10b981' }" text-color="white">
              <q-icon :name="cat.icon || 'sym_r_sell'" size="10px" />
            </q-avatar>
            <q-badge v-if="cat.code" outline color="teal-3" class="font-mono text-weight-bold q-ml-xs q-px-xs" style="font-size: 0.65rem">
              {{ cat.code }}
            </q-badge>
            <span class="text-caption text-white text-weight-medium q-ml-xs">
              {{ cat.name }}
            </span>
            <q-tooltip class="bg-dark text-white shadow-3 font-mono text-caption">
              {{ cat.display_path || (cat.parent_name ? `${cat.parent_name} -> ` : '') + cat.name }}
            </q-tooltip>
          </q-chip>
        </div>
      </div>

      <!-- Etiquetas (Tags) del Contacto -->
      <div class="q-mb-md">
        <div class="row items-center justify-between q-mb-xs">
          <span class="text-caption text-weight-bold text-white">Etiquetas (Tags)</span>
          <q-btn
            flat
            dense
            round
            size="xs"
            color="teal-4"
            icon="sym_r_add"
            @click="isTagDialogOpen = true"
          >
            <q-tooltip>Asignar etiqueta al contacto</q-tooltip>
          </q-btn>
        </div>

        <div v-if="assignedTags.length === 0" class="text-caption text-grey-5 italic q-py-xs">
          Sin etiquetas asignadas.
        </div>

        <div v-else class="row q-gutter-xs q-mt-xs">
          <q-chip
            v-for="t in assignedTags"
            :key="t.id || t.name"
            dense
            removable
            dark
            :style="{ backgroundColor: (t.color || t.color_hex || '#0d9488') + '22', borderColor: t.color || t.color_hex || '#0d9488' }"
            style="border: 1px solid;"
            @remove="removeTagFromContact(t.id || t.name)"
          >
            <span class="text-caption text-white text-weight-medium">
              #{{ t.name }}
            </span>
          </q-chip>
        </div>
      </div>

      <!-- Diálogo Asignar Etiqueta al Contacto -->
      <q-dialog v-model="isTagDialogOpen">
        <q-card style="width: 380px; max-width: 95vw; background: var(--crm-bg-card); border: 1px solid var(--crm-color-border);" class="q-pa-md" dark>
          <q-card-section>
            <div class="text-subtitle1 text-bold text-white">Asignar Etiqueta al Contacto</div>
            <div class="text-caption text-grey-4">Selecciona una etiqueta existente o escribe una nueva.</div>
          </q-card-section>

          <q-card-section class="q-gutter-y-sm">
            <q-select
              v-model="selectedTagToAssign"
              :options="availableTagOptions"
              use-input
              new-value-mode="add-unique"
              outlined
              dark
              dense
              label="Escribe o selecciona etiqueta"
            >
              <template #prepend>
                <q-icon name="sym_r_label" size="18px" class="text-teal-4" />
              </template>
            </q-select>
          </q-card-section>

          <q-card-actions align="right">
            <q-btn flat label="Cancelar" color="grey-4" no-caps v-close-popup />
            <q-btn
              unelevated
              label="Asignar"
              color="primary"
              no-caps
              :loading="assigningTag"
              :disable="!selectedTagToAssign"
              @click="addTagToContact"
            />
          </q-card-actions>
        </q-card>
      </q-dialog>

      <!-- Diálogo Asignar Categoría al Chat -->
      <q-dialog v-model="isAssignDialogOpen">
        <q-card style="width: 540px; max-width: 95vw; background: var(--crm-bg-card); border: 1px solid var(--crm-color-border);" class="q-pa-md" dark>
          <q-card-section>
            <div class="text-subtitle1 text-bold text-white">Asignar Clasificación al Chat</div>
            <div class="text-caption text-grey-4">Busca o selecciona la carrera, área o programa de interés del contacto.</div>
          </q-card-section>

          <q-card-section class="q-gutter-y-sm">
            <q-select
              v-model="selectedCategoryIdToAssign"
              :options="filteredCategoryOptions"
              emit-value
              map-options
              outlined
              dark
              dense
              use-input
              input-debounce="0"
              clearable
              label="Escribe para buscar categoría..."
              class="xf-category-select"
              @filter="filterCategoryOptions"
            >
              <template #prepend>
                <q-icon name="sym_r_search" size="18px" class="text-teal-4" />
              </template>

              <!-- Vista seleccionada -->
              <template #selected-item="scope">
                <div class="row items-center no-wrap ellipsis text-caption text-white q-py-xs">
                  <span
                    class="filter-dot q-mr-xs"
                    :style="{ backgroundColor: scope.opt.color || '#10b981' }"
                  ></span>
                  <span class="text-weight-bold text-teal-3 ellipsis">{{ scope.opt.label }}</span>
                </div>
              </template>

              <!-- Opciones en dropdown -->
              <template #option="{ itemProps, opt }">
                <q-item v-bind="itemProps" dense dark class="q-py-sm cursor-pointer category-select-item">
                  <q-item-section avatar style="min-width: 28px" class="q-pr-xs">
                    <q-avatar size="24px" :style="{ backgroundColor: opt.color ? opt.color + '22' : 'rgba(16,185,129,0.15)', border: `1px solid ${opt.color || '#10b981'}` }">
                      <q-icon :name="opt.icon || 'sym_r_sell'" size="13px" :style="{ color: opt.color || '#10b981' }" />
                    </q-avatar>
                  </q-item-section>
                  <q-item-section>
                    <q-item-label class="text-body2 text-white text-weight-medium">
                      {{ opt.name }}
                    </q-item-label>
                    <q-item-label caption class="text-teal-3 text-caption font-mono">
                      {{ opt.display_path }}
                    </q-item-label>
                  </q-item-section>
                </q-item>
              </template>

              <template #no-option>
                <q-item dark>
                  <q-item-section class="text-grey-5 text-caption text-center q-pa-sm">
                    No se encontraron categorías coincidentes.
                  </q-item-section>
                </q-item>
              </template>
            </q-select>
          </q-card-section>

          <q-card-actions align="right">
            <q-btn flat label="Cancelar" color="grey-4" no-caps v-close-popup />
            <q-btn
              unelevated
              label="Asignar al Chat"
              color="primary"
              no-caps
              :loading="assigning"
              :disable="!selectedCategoryIdToAssign"
              @click="assignCategory"
            />
          </q-card-actions>
        </q-card>
      </q-dialog>

      <!-- Diálogo Cambiar Estado del Lead -->
      <q-dialog v-model="isChangeStatusDialogOpen">
        <q-card style="width: 480px; max-width: 95vw; background: var(--crm-bg-card); border: 1px solid var(--crm-color-border);" class="q-pa-md" dark>
          <q-card-section>
            <div class="text-subtitle1 text-bold text-white">Actualizar Estado del Lead</div>
            <div class="text-caption text-grey-4">
              Estado actual: <span class="text-weight-bold text-white">{{ currentCustomStatus?.name || 'Sin estado' }}</span>
            </div>
          </q-card-section>

          <q-card-section class="q-gutter-y-sm">
            <div class="text-caption text-grey-4 q-mb-xs">Selecciona el nuevo estado:</div>

            <div class="column q-gutter-y-xs">
              <div
                v-for="st in allCustomStatuses"
                :key="st.id"
                class="row items-center justify-between q-pa-sm rounded-borders cursor-pointer transition-fast"
                :class="{
                  'bg-primary-soft': selectedTargetStatusId === st.id,
                }"
                :style="{
                  border: selectedTargetStatusId === st.id ? '1px solid ' + st.color : '1px solid rgba(255,255,255,0.06)',
                  opacity: (!isTransitionAllowed(st) && st.id !== currentCustomStatus?.id) ? 0.45 : 1,
                  background: selectedTargetStatusId === st.id ? 'rgba(16,185,129,0.12)' : 'rgba(255,255,255,0.02)',
                }"
                @click="selectTargetStatus(st)"
              >
                <div class="row items-center q-gutter-x-sm">
                  <span class="xf-dot-indicator" :style="{ backgroundColor: st.color }"></span>
                  <q-icon :name="st.icon || 'sym_r_flag'" size="16px" :style="{ color: st.color }" />
                  <span class="text-white text-weight-medium">{{ st.name }}</span>
                  <q-badge v-if="st.id === currentCustomStatus?.id" color="grey-8" text-color="white" size="xs">
                    Actual
                  </q-badge>
                </div>

                <div>
                  <span v-if="!isTransitionAllowed(st) && st.id !== currentCustomStatus?.id" class="text-caption text-negative">
                    <q-icon name="sym_r_lock" size="14px" /> Requiere flujo previo
                  </span>
                  <q-icon v-else-if="selectedTargetStatusId === st.id" name="sym_r_check" size="18px" color="teal-4" />
                </div>
              </div>
            </div>

            <q-input
              v-model="changeStatusNote"
              label="Nota interna (opcional)"
              placeholder="ej: Contacto confirmó interés en el producto/servicio..."
              outlined
              dark
              dense
              class="q-mt-md"
            />
          </q-card-section>

          <q-card-actions align="right">
            <q-btn flat label="Cancelar" color="grey-4" no-caps v-close-popup />
            <q-btn
              unelevated
              label="Confirmar Cambio"
              color="primary"
              no-caps
              :loading="savingCustomStatus"
              :disable="!selectedTargetStatusId || selectedTargetStatusId === currentCustomStatus?.id"
              @click="submitChangeStatus"
            />
          </q-card-actions>
        </q-card>
      </q-dialog>

      <!-- Resumen Inteligente de la Conversación con MiniMax -->
      <div class="q-mb-md">
        <div class="row items-center justify-between q-mb-xs">
          <span class="text-caption text-weight-bold text-white row items-center q-gutter-x-xs">
            <q-icon name="sym_r_auto_awesome" size="15px" color="amber-4" />
            <span>Resumen del Caso (MiniMax)</span>
          </span>
          <q-btn
            v-if="aiSummaryData"
            flat
            dense
            round
            size="xs"
            color="amber-4"
            icon="sym_r_refresh"
            :loading="isLoadingAiSummary"
            @click="loadAiSummary"
          >
            <q-tooltip>Regenerar resumen de la conversación con MiniMax</q-tooltip>
          </q-btn>
        </div>

        <div v-if="aiSummaryData" class="q-pa-sm rounded-borders" style="background: rgba(245, 158, 11, 0.05); border: 1px solid rgba(245, 158, 11, 0.25)">
          <p class="text-caption text-grey-2 q-mb-xs" style="line-height: 1.35">{{ aiSummaryData.summary }}</p>
          <ul class="q-pl-md q-my-none text-caption text-grey-4" style="font-size: 0.72rem">
            <li v-for="(point, idx) in aiSummaryData.key_points" :key="idx">{{ point }}</li>
          </ul>
        </div>
        <q-btn
          v-else
          outline
          dense
          no-caps
          size="sm"
          color="amber-4"
          icon="sym_r_summarize"
          label="Resumir Conversación con IA"
          class="full-width q-py-xs"
          :loading="isLoadingAiSummary"
          @click="loadAiSummary"
        />
      </div>

      <q-separator dark class="q-my-md" style="border-color: var(--crm-color-border)" />

      <!-- Pipeline Comercial & Deals (Fase 3) -->
      <div class="q-mb-md">
        <div class="row items-center justify-between q-mb-xs">
          <span class="text-caption text-weight-bold text-white">Pipeline Comercial</span>
          <q-btn
            flat
            dense
            round
            size="xs"
            color="teal-4"
            icon="sym_r_open_in_new"
            to="/app/deals"
          >
            <q-tooltip>Abrir Tablero Kanban</q-tooltip>
          </q-btn>
        </div>
        <q-btn
          outline
          dense
          no-caps
          color="teal-4"
          icon="sym_r_view_kanban"
          label="Ver en Tablero Kanban"
          class="full-width q-py-xs"
          to="/app/deals"
        />
      </div>

      <q-separator dark class="q-my-md" style="border-color: var(--crm-color-border)" />

      <!-- Acciones de Gestión -->
      <div class="q-gutter-y-xs">
        <q-btn
          v-if="conversation.status !== 'closed'"
          outline
          dense
          no-caps
          color="primary"
          icon="sym_r_swap_horiz"
          label="Transferir a otro agente"
          class="full-width q-py-xs"
          @click="emit('transfer')"
        />

        <q-btn
          v-if="conversation.status !== 'closed'"
          flat
          dense
          no-caps
          color="negative"
          icon="sym_r_done_all"
          label="Finalizar conversación"
          class="full-width q-py-xs"
          @click="emit('resolve')"
        />

        <q-btn
          v-if="conversation.status === 'closed'"
          unelevated
          dense
          no-caps
          color="positive"
          icon="sym_r_history"
          label="Reactivar conversación"
          class="full-width q-py-xs"
          @click="emit('reopen')"
        />
      </div>
    </div>
  </aside>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useQueryClient } from '@tanstack/vue-query'
import SocialChannelBadge from '@/shared/components/SocialChannelBadge.vue'
import { http } from '@/shared/api/http'
import {
  getHentleAiSummary,
  getConversationAiAnalysis,
  applyAiContactData,
  type AiExtractedContactData,
} from '../api/conversations.api'
import { useAppNotify } from '@/shared/composables/useAppNotify'
import { formatCategoryDisplayPath, matchesCategorySearch } from '@/modules/parameters/utils/category-formatter'
import type { Conversation } from '../types/conversation.types'

const props = defineProps<{
  conversation: Conversation
  isMobile?: boolean
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'transfer'): void
  (e: 'resolve'): void
  (e: 'reopen'): void
}>()

const notify = useAppNotify()
const queryClient = useQueryClient()
const aiSummaryData = ref<{ summary: string; key_points: string[] } | null>(null)
const isLoadingAiSummary = ref(false)

// Estados para Datos Extraídos por IA a la Ficha (Fase 4)
const aiExtractedData = ref<AiExtractedContactData | null>(null)
const isExtractedDataDismissed = ref(false)
const isSavingExtractedData = ref(false)

async function loadAiContactExtraction() {
  if (!props.conversation?.id) return
  try {
    const analysis = await getConversationAiAnalysis(props.conversation.id)
    aiExtractedData.value = analysis.extracted_contact_data
    isExtractedDataDismissed.value = false
  } catch (err) {
    console.debug('Error fetching contact extraction:', err)
  }
}

async function handleApplyExtractedData() {
  if (!props.conversation?.id || !aiExtractedData.value) return
  isSavingExtractedData.value = true
  try {
    await applyAiContactData(props.conversation.id, {
      first_name: aiExtractedData.value.first_name || undefined,
      email: aiExtractedData.value.email || undefined,
      ci: aiExtractedData.value.ci || undefined,
      city: aiExtractedData.value.city || undefined,
      alternative_phone: aiExtractedData.value.alternative_phone || undefined,
    })
    isExtractedDataDismissed.value = true
    queryClient.invalidateQueries({ queryKey: ['conversations'] })
    notify.success({ message: 'Ficha del contacto actualizada con los datos extraídos por IA.' })
  } catch (err: any) {
    notify.error({ message: err?.response?.data?.message || 'Error al actualizar contacto.' })
  } finally {
    isSavingExtractedData.value = false
  }
}

async function loadAiSummary() {
  if (!props.conversation?.id) return
  isLoadingAiSummary.value = true
  try {
    const res = await getHentleAiSummary(props.conversation.id)
    if (res) {
      aiSummaryData.value = res
      notify.success({ message: 'Resumen generado con MiniMax.' })
    }
  } catch (e: any) {
    notify.error({ message: 'Error generando resumen: ' + (e?.response?.data?.message || e?.message || 'Error de IA') })
  } finally {
    isLoadingAiSummary.value = false
  }
}

// Categorías del Lead / Conversación
const assignedCategories = ref<any[]>([])
const allCategories = ref<any[]>([])
const loadingCategories = ref(false)
const assigning = ref(false)
const isAssignDialogOpen = ref(false)
const selectedCategoryIdToAssign = ref<string | null>(null)

// Etiquetas (Tags) del Contacto
const assignedTags = ref<any[]>([])
const allAvailableTags = ref<any[]>([])
const isTagDialogOpen = ref(false)
const selectedTagToAssign = ref<string | null>(null)
const assigningTag = ref(false)

const availableTagOptions = computed(() => {
  return allAvailableTags.value.map((t) => t.name)
})

async function fetchContactTags() {
  const contactId = props.conversation?.contact?.id
  if (!contactId) return
  try {
    const res = await http.get(`/contacts/${contactId}`)
    if (res.data?.data?.tags && Array.isArray(res.data.data.tags)) {
      assignedTags.value = [...res.data.data.tags]
      if (props.conversation?.contact) {
        (props.conversation.contact as any).tags = [...res.data.data.tags]
      }
    }
  } catch (error) {
    console.error('Error fetching contact tags', error)
  }
}

watch(
  () => [props.conversation?.id, props.conversation?.contact?.id],
  ([, contactId]) => {
    if (contactId) {
      loadAiContactExtraction()
      const contact = props.conversation?.contact as any
      if (contact && Array.isArray(contact.tags) && contact.tags.length > 0) {
        assignedTags.value = [...contact.tags]
      } else {
        fetchContactTags()
      }
    } else {
      assignedTags.value = []
      aiExtractedData.value = null
    }
  },
  { immediate: true, deep: true },
)

async function fetchAllTags() {
  try {
    const res = await http.get('/tags')
    allAvailableTags.value = res.data.data || []
  } catch (error) {
    console.error('Error fetching tags', error)
  }
}

async function addTagToContact() {
  const contact = props.conversation?.contact as any
  if (!contact?.id || !selectedTagToAssign.value) return
  const rawVal = selectedTagToAssign.value as any
  const tagName = (typeof rawVal === 'string' ? rawVal : rawVal?.value || '').trim()
  if (!tagName) return

  assigningTag.value = true
  try {
    const currentTagNames = assignedTags.value.map((t) => t.name)
    if (!currentTagNames.includes(tagName)) {
      currentTagNames.push(tagName)
    }

    const payload = {
      first_name: contact.name || contact.first_name || 'Sin nombre',
      last_name: contact.last_name || null,
      email: contact.email || null,
      phone: contact.phone || null,
      tags: currentTagNames,
    }

    const res = await http.put(`/contacts/${contact.id}`, payload)
    if (res.data?.data?.tags) {
      assignedTags.value = res.data.data.tags
      contact.tags = res.data.data.tags
    } else {
      assignedTags.value = currentTagNames.map((n) => ({ name: n }))
    }
    selectedTagToAssign.value = null
    isTagDialogOpen.value = false
    notify.success({ message: `Etiqueta #${tagName} asignada.` })
    fetchAllTags()
    queryClient.invalidateQueries({ queryKey: ['conversation', props.conversation.id] })
    queryClient.invalidateQueries({ queryKey: ['conversations'] })
    queryClient.invalidateQueries({ queryKey: ['contacts'] })
  } catch (error: any) {
    notify.error({ message: 'Error al asignar etiqueta: ' + (error?.response?.data?.message || error?.message) })
  } finally {
    assigningTag.value = false
  }
}

async function removeTagFromContact(tagIdentifier: string) {
  const contact = props.conversation?.contact as any
  if (!contact?.id) return

  try {
    const newTags = assignedTags.value
      .filter((t) => t.id !== tagIdentifier && t.name !== tagIdentifier)
      .map((t) => t.name)

    const payload = {
      first_name: contact.name || contact.first_name || 'Sin nombre',
      last_name: contact.last_name || null,
      email: contact.email || null,
      phone: contact.phone || null,
      tags: newTags,
    }

    const res = await http.put(`/contacts/${contact.id}`, payload)
    if (res.data?.data?.tags) {
      assignedTags.value = res.data.data.tags
      contact.tags = res.data.data.tags
    } else {
      assignedTags.value = assignedTags.value.filter((t) => t.id !== tagIdentifier && t.name !== tagIdentifier)
    }
    notify.info({ message: 'Etiqueta eliminada.' })
    queryClient.invalidateQueries({ queryKey: ['conversation', props.conversation.id] })
    queryClient.invalidateQueries({ queryKey: ['conversations'] })
    queryClient.invalidateQueries({ queryKey: ['contacts'] })
  } catch (error: any) {
    notify.error({ message: 'Error al remover etiqueta: ' + (error?.response?.data?.message || error?.message) })
  }
}

const categorySearchQuery = ref('')

const availableCategoryOptions = computed(() => {
  const assignedIds = new Set(assignedCategories.value.map((c) => c.id))
  return allCategories.value
    .filter((c) => !assignedIds.has(c.id) && c.is_selectable !== false)
    .map((c) => {
      const displayPath = formatCategoryDisplayPath(c, allCategories.value)
      return {
        label: displayPath,
        value: c.id,
        name: c.name,
        code: c.code,
        color: c.color || '#10b981',
        icon: c.icon || 'sym_r_sell',
        display_path: displayPath,
      }
    })
})

const filteredCategoryOptions = computed(() => {
  const query = categorySearchQuery.value.toLowerCase().trim()
  if (!query) return availableCategoryOptions.value
  return availableCategoryOptions.value.filter((opt) =>
    matchesCategorySearch(opt, query, opt.display_path)
  )
})

function filterCategoryOptions(val: string, update: (fn: () => void) => void) {
  update(() => {
    categorySearchQuery.value = val
  })
}

async function fetchAssignedCategories() {
  if (!props.conversation?.id) return
  loadingCategories.value = true
  try {
    const res = await http.get(`/conversations/${props.conversation.id}/categories`)
    assignedCategories.value = res.data.data || []
  } catch (error) {
    console.error('Error fetching assigned categories', error)
  } finally {
    loadingCategories.value = false
  }
}

async function fetchAllCategories() {
  try {
    const res = await http.get('/categories')
    allCategories.value = res.data.data || []
  } catch (error) {
    console.error('Error fetching categories list', error)
  }
}

function openAssignDialog() {
  selectedCategoryIdToAssign.value = null
  categorySearchQuery.value = ''
  isAssignDialogOpen.value = true
}

async function assignCategory() {
  if (!selectedCategoryIdToAssign.value || !props.conversation?.id) return
  assigning.value = true
  try {
    const res = await http.post(`/conversations/${props.conversation.id}/categories`, {
      category_id: selectedCategoryIdToAssign.value,
    })
    assignedCategories.value = res.data.data || []
    isAssignDialogOpen.value = false
  } catch (error) {
    console.error('Error assigning category', error)
  } finally {
    assigning.value = false
  }
}

async function removeCategory(categoryId: string) {
  if (!props.conversation?.id) return
  try {
    await http.delete(`/conversations/${props.conversation.id}/categories/${categoryId}`)
    assignedCategories.value = assignedCategories.value.filter((c) => c.id !== categoryId)
  } catch (error) {
    console.error('Error removing category', error)
  }
}

// Estados Personalizados del Lead
const allCustomStatuses = ref<any[]>([])
const isChangeStatusDialogOpen = ref(false)
const selectedTargetStatusId = ref<string | null>(null)
const changeStatusNote = ref('')
const savingCustomStatus = ref(false)

const currentCustomStatus = computed(() => {
  if (!props.conversation?.custom_status_id) {
    return allCustomStatuses.value.find((s) => s.is_default) || null
  }
  return allCustomStatuses.value.find((s) => s.id === props.conversation.custom_status_id) || null
})

function isTransitionAllowed(targetStatus: any): boolean {
  if (!currentCustomStatus.value) return true
  if (targetStatus.id === currentCustomStatus.value.id) return true
  if (!targetStatus.allowed_previous_status_ids || targetStatus.allowed_previous_status_ids.length === 0) return true
  return targetStatus.allowed_previous_status_ids.includes(currentCustomStatus.value.id)
}

function openChangeStatusDialog() {
  selectedTargetStatusId.value = currentCustomStatus.value?.id || null
  changeStatusNote.value = ''
  isChangeStatusDialogOpen.value = true
}

function selectTargetStatus(st: any) {
  if (!isTransitionAllowed(st) && st.id !== currentCustomStatus.value?.id) {
    return
  }
  selectedTargetStatusId.value = st.id
}

async function submitChangeStatus() {
  if (!selectedTargetStatusId.value || !props.conversation?.id) return
  savingCustomStatus.value = true
  try {
    await http.put(`/conversations/${props.conversation.id}/custom-status`, {
      custom_status_id: selectedTargetStatusId.value,
      note: changeStatusNote.value || null,
    })
    props.conversation.custom_status_id = selectedTargetStatusId.value
    isChangeStatusDialogOpen.value = false
  } catch (error: any) {
    console.error('Error updating custom status', error)
  } finally {
    savingCustomStatus.value = false
  }
}

async function fetchCustomStatuses() {
  try {
    const res = await http.get('/custom-statuses')
    allCustomStatuses.value = res.data.data || []
  } catch (error) {
    console.error('Error fetching custom statuses', error)
  }
}

watch(
  () => props.conversation?.id,
  () => {
    aiSummaryData.value = null
    fetchAssignedCategories()
  },
  { immediate: true },
)

onMounted(() => {
  fetchAllCategories()
  fetchCustomStatuses()
  fetchAllTags()
})

const statusBadgeColor = computed(() => {
  switch (props.conversation.status) {
    case 'open':
      return 'teal-9'
    case 'pending':
      return 'amber-10'
    case 'closed':
    default:
      return 'grey-8'
  }
})

const statusBadgeLabel = computed(() => {
  switch (props.conversation.status) {
    case 'open':
      return 'Abierto'
    case 'pending':
      return 'En espera'
    case 'closed':
    default:
      return 'Cerrado'
  }
})

function getInitials(name: string): string {
  if (!name) return 'WA'
  const parts = name.trim().split(' ')
  if (parts.length >= 2) return (parts[0][0] + parts[1][0]).toUpperCase()
  return name.slice(0, 2).toUpperCase()
}

function cleanPhone(phone: string): string {
  return phone.replace(/[^0-9]/g, '')
}
</script>

<style scoped lang="scss">
.inbox-contact-sidebar {
  width: 290px;
  min-width: 280px;
  height: 100%;
  display: flex;
  flex-direction: column;
  background: var(--crm-bg-sidebar);
  border-left: 1px solid var(--crm-color-border);

  @media (max-width: 899px) {
    width: 100% !important;
    min-width: 100% !important;
    max-width: 100% !important;
    border-left: none !important;
  }
}

.inbox-contact-sidebar__header {
  height: 54px;
  border-bottom: 1px solid var(--crm-color-border);
}

.contact-profile-avatar {
  background: var(--crm-color-surface-elevated);
  color: var(--crm-color-primary);
  font-size: 1.1rem;
  font-weight: 700;
  border: 1px solid var(--crm-color-border);
}

.info-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 0.78rem;
}

.info-label {
  color: var(--crm-color-dim);
}

.info-val {
  color: var(--crm-color-ink);
  font-weight: 500;
}
</style>
