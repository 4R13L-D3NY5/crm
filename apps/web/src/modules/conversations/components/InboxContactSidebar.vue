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
            @click="openTagDialog"
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

      <!-- Diálogo Asignar Etiquetas al Contacto (Selección Múltiple & Checkboxes) -->
      <q-dialog v-model="isTagDialogOpen">
        <q-card style="width: 440px; max-width: 95vw; background: var(--crm-bg-card); border: 1px solid var(--crm-color-border);" class="q-pa-md" dark>
          <q-card-section>
            <div class="text-subtitle1 text-bold text-white">Asignar Etiquetas al Contacto</div>
            <div class="text-caption text-grey-4">Marca una o más etiquetas existentes, o escribe nuevas (presiona Enter para agregar).</div>
          </q-card-section>

          <q-card-section class="q-gutter-y-sm">
            <q-select
              v-model="selectedTagsToAssign"
              :options="filteredTagOptions"
              multiple
              use-chips
              use-input
              new-value-mode="add-unique"
              outlined
              dark
              dense
              label="Escribe o checkea etiquetas..."
              @filter="filterTagOptions"
            >
              <template #prepend>
                <q-icon name="sym_r_label" size="18px" class="text-teal-4" />
              </template>

              <!-- Checkbox en cada opción de tag -->
              <template #option="{ itemProps, opt, selected, toggleOption }">
                <q-item v-bind="itemProps" dense dark class="q-py-xs cursor-pointer" @click="toggleOption(opt)">
                  <q-item-section side>
                    <q-checkbox :model-value="selected" dense color="teal-4" @update:model-value="toggleOption(opt)" />
                  </q-item-section>
                  <q-item-section>
                    <q-item-label class="text-body2 text-white">
                      #{{ typeof opt === 'string' ? opt : (opt.label || opt.name || opt.value) }}
                    </q-item-label>
                  </q-item-section>
                </q-item>
              </template>

              <template #selected-item="scope">
                <q-chip
                  dense
                  dark
                  removable
                  color="teal-9"
                  text-color="teal-2"
                  class="q-ma-none q-mr-xs q-my-xs"
                  @remove="scope.removeAtIndex(scope.index)"
                >
                  <span class="text-caption">#{{ typeof scope.opt === 'string' ? scope.opt : (scope.opt.label || scope.opt.name || scope.opt.value) }}</span>
                </q-chip>
              </template>
            </q-select>
          </q-card-section>

          <q-card-actions align="right">
            <q-btn flat label="Cancelar" color="grey-4" no-caps v-close-popup />
            <q-btn
              unelevated
              :label="selectedTagsToAssign.length > 1 ? `Asignar (${selectedTagsToAssign.length})` : 'Asignar'"
              color="primary"
              no-caps
              :loading="assigningTag"
              :disable="selectedTagsToAssign.length === 0"
              @click="addTagToContact"
            />
          </q-card-actions>
        </q-card>
      </q-dialog>

      <!-- Diálogo Asignar Categoría al Chat (Selección Múltiple & Checkboxes) -->
      <q-dialog v-model="isAssignDialogOpen">
        <q-card style="width: 560px; max-width: 95vw; background: var(--crm-bg-card); border: 1px solid var(--crm-color-border);" class="q-pa-md" dark>
          <q-card-section>
            <div class="text-subtitle1 text-bold text-white">Asignar Clasificación al Chat</div>
            <div class="text-caption text-grey-4">Busca y checkea una o más carreras, áreas o programas de interés del contacto.</div>
          </q-card-section>

          <q-card-section class="q-gutter-y-sm">
            <q-select
              v-model="selectedCategoryIdsToAssign"
              :options="filteredCategoryOptions"
              multiple
              use-chips
              emit-value
              map-options
              outlined
              dark
              dense
              use-input
              input-debounce="0"
              clearable
              label="Escribe para buscar y checkear categorías..."
              class="xf-category-select"
              @filter="filterCategoryOptions"
            >
              <template #prepend>
                <q-icon name="sym_r_search" size="18px" class="text-teal-4" />
              </template>

              <!-- Vista de elementos seleccionados (chips) -->
              <template #selected-item="scope">
                <q-chip
                  dense
                  dark
                  removable
                  color="dark"
                  text-color="teal-3"
                  class="q-ma-none q-mr-xs q-my-xs"
                  @remove="scope.removeAtIndex(scope.index)"
                >
                  <q-avatar size="16px" :style="{ backgroundColor: scope.opt.color || '#10b981' }" text-color="white">
                    <q-icon :name="scope.opt.icon || 'sym_r_sell'" size="10px" />
                  </q-avatar>
                  <span class="text-caption text-weight-medium q-ml-xs">{{ scope.opt.name }}</span>
                </q-chip>
              </template>

              <!-- Opciones en dropdown con Checkbox -->
              <template #option="{ itemProps, opt, selected, toggleOption }">
                <q-item v-bind="itemProps" dense dark class="q-py-sm cursor-pointer category-select-item" @click="toggleOption(opt)">
                  <q-item-section side>
                    <q-checkbox :model-value="selected" dense color="teal-4" @update:model-value="toggleOption(opt)" />
                  </q-item-section>
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
              :label="selectedCategoryIdsToAssign.length > 1 ? `Asignar al Chat (${selectedCategoryIdsToAssign.length})` : 'Asignar al Chat'"
              color="primary"
              no-caps
              :loading="assigning"
              :disable="selectedCategoryIdsToAssign.length === 0"
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
          v-if="conversation.status !== 'closed' && (conversation.assignee || conversation.assigned_to_user_id)"
          outline
          dense
          no-caps
          color="amber-4"
          icon="sym_r_lock_open"
          label="Liberar chat (Relevo / Guardia)"
          class="full-width q-py-xs"
          @click="emit('release')"
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
import { getHentleAiSummary } from '../api/conversations.api'
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
  (e: 'release'): void
  (e: 'resolve'): void
  (e: 'reopen'): void
}>()

const notify = useAppNotify()
const queryClient = useQueryClient()
const aiSummaryData = ref<{ summary: string; key_points: string[] } | null>(null)
const isLoadingAiSummary = ref(false)

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
const selectedCategoryIdsToAssign = ref<string[]>([])

// Etiquetas (Tags) del Contacto
const assignedTags = ref<any[]>([])
const allAvailableTags = ref<any[]>([])
const isTagDialogOpen = ref(false)
const selectedTagsToAssign = ref<any[]>([])
const assigningTag = ref(false)
const tagFilterQuery = ref('')

const availableTagOptions = computed(() => {
  return allAvailableTags.value.map((t) => t.name)
})

const filteredTagOptions = computed(() => {
  const query = tagFilterQuery.value.trim().toLowerCase()
  if (!query) return availableTagOptions.value
  return availableTagOptions.value.filter((name) => name.toLowerCase().includes(query))
})

function filterTagOptions(val: string, update: (fn: () => void) => void) {
  update(() => {
    tagFilterQuery.value = val
  })
}

function openTagDialog() {
  selectedTagsToAssign.value = []
  tagFilterQuery.value = ''
  isTagDialogOpen.value = true
}

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
      const contact = props.conversation?.contact as any
      if (contact && Array.isArray(contact.tags) && contact.tags.length > 0) {
        assignedTags.value = [...contact.tags]
      } else {
        fetchContactTags()
      }
    } else {
      assignedTags.value = []
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
  if (!contact?.id || selectedTagsToAssign.value.length === 0) return

  assigningTag.value = true
  try {
    const currentTagNames = assignedTags.value.map((t) => t.name)
    const newTagNamesToAdd: string[] = selectedTagsToAssign.value
      .map((t: any) => {
        const raw = typeof t === 'string' ? t : (t.value || t.name || t.label || '')
        return String(raw).trim()
      })
      .filter(Boolean)

    for (const name of newTagNamesToAdd) {
      if (!currentTagNames.includes(name)) {
        currentTagNames.push(name)
      }
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
    selectedTagsToAssign.value = []
    isTagDialogOpen.value = false
    notify.success({ message: `Etiquetas asignadas al contacto.` })
    fetchAllTags()
    queryClient.invalidateQueries({ queryKey: ['conversation', props.conversation.id] })
    queryClient.invalidateQueries({ queryKey: ['conversations'] })
    queryClient.invalidateQueries({ queryKey: ['contacts'] })
  } catch (error: any) {
    notify.error({ message: 'Error al asignar etiquetas: ' + (error?.response?.data?.message || error?.message) })
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
  selectedCategoryIdsToAssign.value = []
  categorySearchQuery.value = ''
  isAssignDialogOpen.value = true
}

async function assignCategory() {
  if (selectedCategoryIdsToAssign.value.length === 0 || !props.conversation?.id) return
  assigning.value = true
  try {
    const res = await http.post(`/conversations/${props.conversation.id}/categories`, {
      category_ids: selectedCategoryIdsToAssign.value,
    })
    assignedCategories.value = res.data.data || []
    selectedCategoryIdsToAssign.value = []
    isAssignDialogOpen.value = false
    notify.success({ message: 'Clasificación asignada al chat.' })
    queryClient.invalidateQueries({ queryKey: ['conversation', props.conversation.id] })
    queryClient.invalidateQueries({ queryKey: ['conversations'] })
  } catch (error: any) {
    console.error('Error assigning category', error)
    notify.error({ message: 'Error al asignar categorías: ' + (error?.response?.data?.message || error?.message) })
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
