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
          <span class="info-label">Canal:</span>
          <span class="info-val text-capitalize">{{ conversation.channel }}</span>
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
            <q-tooltip>Asignar categoría, carrera o sede</q-tooltip>
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
              {{ cat.parent_name ? `${cat.parent_name} › ` : '' }}{{ cat.name }}
            </span>
          </q-chip>
        </div>
      </div>

      <!-- Diálogo Asignar Categoría al Chat -->
      <q-dialog v-model="isAssignDialogOpen">
        <q-card style="width: 420px; max-width: 95vw; background: var(--crm-bg-card); border: 1px solid var(--crm-color-border);" class="q-pa-md" dark>
          <q-card-section>
            <div class="text-subtitle1 text-bold text-white">Asignar Clasificación al Chat</div>
            <div class="text-caption text-grey-4">Selecciona la carrera, sede o categoría de interés del postulante.</div>
          </q-card-section>

          <q-card-section class="q-gutter-y-sm">
            <q-select
              v-model="selectedCategoryIdToAssign"
              :options="availableCategoryOptions"
              emit-value
              map-options
              outlined
              dark
              dense
              label="Seleccionar categoría / subcategoría"
            >
              <template #prepend>
                <q-icon name="sym_r_account_tree" size="18px" class="text-grey-5" />
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
              placeholder="ej: Postulante confirmó interés en la carrera..."
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
      </div>
    </div>
  </aside>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { http } from '@/shared/api/http'
import type { Conversation } from '../types/conversation.types'

const props = defineProps<{
  conversation: Conversation
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'transfer'): void
  (e: 'resolve'): void
}>()

// Categorías del Lead / Conversación
const assignedCategories = ref<any[]>([])
const allCategories = ref<any[]>([])
const loadingCategories = ref(false)
const assigning = ref(false)
const isAssignDialogOpen = ref(false)
const selectedCategoryIdToAssign = ref<string | null>(null)

const availableCategoryOptions = computed(() => {
  const assignedIds = new Set(assignedCategories.value.map((c) => c.id))
  return allCategories.value
    .filter((c) => !assignedIds.has(c.id) && c.is_selectable !== false)
    .map((c) => ({
      label: c.code ? `[${c.code}] ${c.full_path || c.name}` : (c.full_path || c.name),
      value: c.id,
    }))
})

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
    fetchAssignedCategories()
  },
  { immediate: true },
)

onMounted(() => {
  fetchAllCategories()
  fetchCustomStatuses()
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
