<template>
  <div class="xf-departments-page">
    <!-- Header -->
    <div class="row items-center justify-between q-mb-lg">
      <div>
        <h1 class="text-h5 text-bold text-white q-my-none">Filas & Enrutamiento Inteligente</h1>
        <p class="text-caption text-grey-4 q-mt-xs q-mb-none">
          Configuración de colas de atención, chatbots de bienvenida, menús de opciones y derivación automática.
        </p>
      </div>

      <div class="row items-center q-gutter-sm">
        <q-btn
          label="+ Nueva Fila / Cola"
          unelevated
          no-caps
          class="xf-btn-primary"
          @click="openCreateDialog"
        />
      </div>
    </div>

    <!-- Panel Maestro: Menú de Bienvenida & Enrutamiento de Filas -->
    <q-card flat bordered class="xf-panel-card q-mb-xl">
      <div class="row items-center justify-between q-pb-md border-bottom-subtle">
        <div class="row items-center q-gutter-x-md">
          <div class="xf-bot-icon-circle">
            <q-icon name="sym_r_smart_toy" size="26px" color="teal-4" />
          </div>
          <div>
            <div class="row items-center q-gutter-x-sm">
              <span class="text-subtitle1 text-bold text-white">Menú de Bienvenida & Selección de Filas (WhatsApp)</span>
              <q-badge
                :color="botConfig.is_active ? 'positive' : 'grey-8'"
                :text-color="botConfig.is_active ? 'white' : 'grey-4'"
                class="text-caption text-bold q-px-sm"
              >
                {{ botConfig.is_active ? '🟢 Menú Automático Activo' : '⏸️ Menú Inactivo (Bandeja Directa)' }}
              </q-badge>
            </div>
            <div class="text-caption text-grey-4 q-mt-xs">
              {{ botConfig.is_active 
                ? 'Los clientes que escriban por primera vez recibirán el mensaje de bienvenida y el menú para elegir su sede.'
                : 'El menú automático está apagado: todos los mensajes entran directo a la Bandeja Multicanal ("Pendientes") para atención libre.'
              }}
            </div>
          </div>
        </div>

        <div class="row items-center q-gutter-x-md">
          <div class="text-right">
            <div class="text-caption text-grey-4">Activar Menú de Filas</div>
            <div class="text-weight-bold" :class="botConfig.is_active ? 'text-teal-4' : 'text-grey-5'">
              {{ botConfig.is_active ? 'Habilitado' : 'Deshabilitado' }}
            </div>
          </div>
          <q-toggle
            v-model="botConfig.is_active"
            color="positive"
            size="lg"
            dense
          />
        </div>
      </div>

      <!-- Configuración detallada y Vista Previa -->
      <div class="q-pt-md">
        <div class="row q-col-gutter-lg">
          <!-- Columna Izquierda: Formulario de Configuración -->
          <div class="col-12 col-md-7 q-gutter-y-md">
            <div>
              <div class="row items-center justify-between q-mb-xs">
                <label class="text-caption text-bold text-grey-3">
                  Mensaje de Bienvenida Predeterminado (Encabezado) *
                </label>
                <span class="text-caption text-teal-4" style="font-size: 0.72rem">
                  Se envía automáticamente al recibir un nuevo chat
                </span>
              </div>
              <q-input
                v-model="botConfig.greeting_message"
                type="textarea"
                rows="3"
                outlined
                dark
                dense
                placeholder="Escribe el saludo de bienvenida que antecede a las opciones..."
              />
              <div class="text-caption text-grey-5 q-mt-xs" style="font-size: 0.72rem">
                A continuación de este texto, el bot adjuntará automáticamente la lista numerada de las filas registradas abajo.
              </div>
            </div>

            <div>
              <label class="text-caption text-bold text-grey-3 q-mb-xs block">
                Mensaje de Opción Inválida (Fallback)
              </label>
              <q-input
                v-model="botConfig.fallback_message"
                type="textarea"
                rows="2"
                outlined
                dark
                dense
                placeholder="Opción no válida. Por favor responde únicamente con el número de tu opción..."
              />
            </div>

            <div class="row items-center justify-between bg-dark q-pa-sm rounded-borders">
              <div>
                <div class="text-caption text-bold text-white">Handoff Cognitivo Hentle-AI</div>
                <div class="text-caption text-grey-5" style="font-size: 0.75rem">
                  Si el cliente hace preguntas abiertas en lugar de un número, la IA responde y lo guía.
                </div>
              </div>
              <q-toggle v-model="botConfig.handoff_to_ai" color="primary" dense />
            </div>

            <div class="row justify-between items-center q-pt-sm">
              <span class="text-caption text-grey-5">
                {{ botConfig.is_active ? 'Efecto inmediato en los números de WhatsApp conectados.' : 'Configuración lista para cuando decidas encender el menú.' }}
              </span>
              <q-btn
                label="Guardar Configuración del Bot"
                icon="sym_r_save"
                color="primary"
                unelevated
                no-caps
                class="xf-btn-primary"
                :loading="savingBotConfig"
                @click="saveBotConfig"
              />
            </div>
          </div>

          <!-- Columna Derecha: Vista Previa en Vivo WhatsApp -->
          <div class="col-12 col-md-5">
            <div class="text-caption text-bold text-grey-3 q-mb-sm row items-center q-gutter-x-xs">
              <q-icon name="sym_r_visibility" size="16px" color="teal-4" />
              <span>Vista Previa del Mensaje en WhatsApp</span>
            </div>

            <div class="whatsapp-preview-box">
              <div class="whatsapp-preview-header">
                <q-avatar size="30px" color="teal-8" text-color="white" icon="sym_r_school" />
                <div class="q-ml-sm">
                  <div class="text-caption text-bold text-white leading-tight">UNITEPC Asistencia Oficial</div>
                  <div class="text-caption text-teal-2" style="font-size: 0.65rem">en línea</div>
                </div>
              </div>

              <div class="whatsapp-preview-body">
                <!-- Burbuja entrante del cliente -->
                <div class="chat-bubble chat-bubble--incoming">
                  <div class="bubble-text">Hola, buenas tardes</div>
                  <div class="bubble-time">10:15</div>
                </div>

                <!-- Burbuja saliente del bot -->
                <div class="chat-bubble chat-bubble--outgoing">
                  <div class="bubble-text" style="white-space: pre-wrap;">{{ previewBotText }}</div>
                  <div class="bubble-time">
                    10:15
                    <q-icon name="sym_r_done_all" size="12px" color="cyan-3" class="q-ml-xs" />
                  </div>
                </div>

                <!-- Overlay de advertencia si el menú está inactivo -->
                <div v-if="!botConfig.is_active" class="preview-disabled-overlay">
                  <div class="text-caption text-bold text-amber-3">
                    <q-icon name="sym_r_pause_circle" size="18px" class="q-mr-xs" />
                    Menú Desactivado (Bandeja Directa)
                  </div>
                  <div class="text-caption text-grey-3 q-mt-xs" style="font-size: 0.72rem">
                    El cliente NO recibirá este menú. Su mensaje entrará directamente a la Bandeja General para atención humana.
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </q-card>

    <!-- Barra de Filtros de Filas -->
    <div class="row items-center justify-between q-col-gutter-sm q-mb-md">
      <div class="col-12 col-sm-8 row items-center q-gutter-sm">
        <q-input
          v-model="search"
          dense
          outlined
          dark
          placeholder="Buscar fila o departamento..."
          class="xf-filter-input"
        >
          <template #prepend>
            <q-icon name="sym_r_search" size="18px" color="grey-5" />
          </template>
        </q-input>
      </div>

      <div class="text-caption text-grey-4">
        {{ filteredDepartments.length }} filas registradas
      </div>
    </div>

    <!-- Tabla de Departamentos / Filas -->
    <q-card flat bordered class="xf-table-card">
      <q-markup-table flat dark class="xf-table">
        <thead>
          <tr>
            <th class="text-left">FILA / DEPARTAMENTO</th>
            <th class="text-left">HORARIO & AGENTES</th>
            <th class="text-left">MENÚ BOT ASOCIADO</th>
            <th class="text-right" style="width: 140px">ACCIONES</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(dept, index) in filteredDepartments" :key="dept.id" class="xf-table-row">
            <!-- Departamento con barra de color y saludo -->
            <td class="text-left">
              <div class="row items-start q-gutter-x-sm">
                <div class="xf-dept-color-bar" :style="{ backgroundColor: dept.color }"></div>
                <div>
                  <div class="text-bold text-white text-subtitle2">{{ dept.name }}</div>
                  <div class="text-caption text-grey-4 ellipsis-2-lines q-mt-xs">
                    {{ dept.greeting || 'Sin mensaje de saludo configurado' }}
                  </div>
                </div>
              </div>
            </td>

            <!-- Horario y Agentes -->
            <td class="text-left">
              <div class="row items-center q-gutter-xs">
                <span class="xf-badge-soft">
                  <q-icon name="sym_r_schedule" size="13px" color="teal-4" class="q-mr-xs" />
                  {{ dept.business_hours ? 'Horario 08:00 - 18:00' : 'Atención 24/7' }}
                </span>
                <span class="xf-badge-soft">
                  <q-icon name="sym_r_group" size="13px" color="indigo-4" class="q-mr-xs" />
                  {{ dept.agents_count }} operadores
                </span>
              </div>
            </td>

            <!-- Menú Bot -->
            <td class="text-left">
              <span v-if="botConfig.is_active" class="xf-bot-status-pill">
                <q-icon name="sym_r_format_list_numbered" size="14px" color="amber-4" class="q-mr-xs" />
                <span>Opción {{ index + 1 }} en Menú</span>
              </span>
              <span v-else class="xf-badge-soft text-grey-5">
                <q-icon name="sym_r_pause" size="13px" class="q-mr-xs text-grey-5" />
                <span>Pausado (Bandeja Directa)</span>
              </span>
            </td>

            <!-- Acciones -->
            <td class="text-right">
              <div class="row justify-end q-gutter-xs">
                <q-btn flat round dense icon="sym_r_tune" color="teal-4" size="sm" @click="editDept(dept)">
                  <q-tooltip>Configurar Fila y Saludo Regional</q-tooltip>
                </q-btn>
                <q-btn flat round dense icon="sym_r_delete" color="negative" size="sm" @click="deleteDept(dept.id)">
                  <q-tooltip>Eliminar Fila</q-tooltip>
                </q-btn>
              </div>
            </td>
          </tr>
        </tbody>
      </q-markup-table>
    </q-card>

    <!-- Modal Crear / Editar Fila y BotFlow -->
    <q-dialog v-model="isDialogOpen">
      <q-card style="width: 620px; max-width: 95vw; max-height: 90vh;" class="xf-modal-card q-pa-md column no-wrap">
        <q-card-section class="col-auto">
          <div class="text-h6 text-bold text-white">
            {{ currentDept ? 'Editar Fila & Enrutamiento' : 'Nueva Fila de Atención' }}
          </div>
          <div class="text-caption text-grey-4">Define el nombre de la cola, color de identificación y saludo automático.</div>
        </q-card-section>

        <q-card-section class="q-gutter-y-md col scroll">
          <q-input v-model="form.name" label="Nombre del Departamento / Fila" outlined dark dense />

          <div class="row items-center justify-between bg-dark q-pa-sm rounded-borders">
            <span class="text-caption text-grey-4">Color de Identificación:</span>
            <input type="color" v-model="form.color" class="xf-color-picker-input" />
          </div>

          <q-input
            v-model="form.greeting"
            label="Mensaje de Bienvenida / Saludo Específico de esta Fila"
            type="textarea"
            rows="3"
            outlined
            dark
            placeholder="Mensaje que se envía cuando el cliente selecciona esta fila..."
          />

          <div class="row items-center justify-between bg-dark q-pa-sm rounded-borders">
            <div>
              <div class="text-caption text-bold text-white">Handoff Cognitivo Hentle-AI</div>
              <div class="text-caption text-grey-5">Traspasa a la IA si el usuario hace preguntas abiertas fuera del árbol.</div>
            </div>
            <q-toggle v-model="form.handoff_to_ai" dense color="primary" />
          </div>
        </q-card-section>

        <q-card-actions align="right" class="col-auto">
          <q-btn flat label="Cancelar" color="grey-4" no-caps v-close-popup />
          <q-btn
            unelevated
            label="Guardar Configuración"
            color="primary"
            no-caps
            class="xf-btn-primary"
            @click="saveDept"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { http } from '@/shared/api/http'
import { useAppNotify } from '@/shared/composables/useAppNotify'

const notify = useAppNotify()

const search = ref('')
const isDialogOpen = ref(false)
const currentDept = ref<any>(null)
const savingBotConfig = ref(false)

interface DepartmentItem {
  id: string
  name: string
  color: string
  greeting: string
  business_hours: boolean
  agents_count: number
}

const departments = ref<DepartmentItem[]>([])

const botConfig = reactive({
  is_active: false,
  greeting_message: '¡Hola! Bienvenido al canal de atención oficial de la Universidad UNITEPC. Por favor escribe el número de la sede o área con la que deseas comunicarte:',
  fallback_message: 'Opción no válida. Por favor responde únicamente con el número correspondiente a tu sede o consulta.',
  handoff_to_ai: true,
})

const form = reactive({
  name: '',
  color: '#10b981',
  greeting: '',
  handoff_to_ai: true,
})

// Texto dinámico para previsualización en WhatsApp
const previewBotText = computed(() => {
  const greeting = botConfig.greeting_message || '¡Hola! Bienvenido al canal oficial de atención de UNITEPC.'
  const options = departments.value.map((d, index) => `${index + 1}. ${d.name}`)
  if (options.length === 0) {
    return greeting
  }
  return `${greeting}\n\n${options.join('\n')}`
})

onMounted(async () => {
  try {
    const [queuesRes, flowRes] = await Promise.allSettled([
      http.get('/queues'),
      http.get('/bot-flows/default'),
    ])

    if (queuesRes.status === 'fulfilled' && queuesRes.value.data?.data && queuesRes.value.data.data.length > 0) {
      departments.value = queuesRes.value.data.data.map((q: any) => ({
        id: q.id,
        name: q.name,
        color: q.color || '#10b981',
        greeting: q.greeting_message || '',
        business_hours: true,
        agents_count: q.users_count || (q.users ? q.users.length : 3),
      }))
    }

    if (flowRes.status === 'fulfilled' && flowRes.value.data?.data) {
      const flow = flowRes.value.data.data
      botConfig.greeting_message = flow.greeting_message || botConfig.greeting_message
      botConfig.is_active = flow.is_active ?? false
      botConfig.handoff_to_ai = flow.handoff_to_ai ?? true
      botConfig.fallback_message = flow.fallback_message || botConfig.fallback_message
    }
  } catch {
    // Mantener estado seguro
  }
})

const filteredDepartments = computed(() => {
  return departments.value.filter((d) => {
    return !search.value || d.name.toLowerCase().includes(search.value.toLowerCase())
  })
})

async function saveBotConfig() {
  savingBotConfig.value = true
  try {
    const options = departments.value.map((d, index) => ({
      option_number: (index + 1).toString(),
      label: d.name,
      queue_id: d.id,
      reply_text: d.greeting || `Has seleccionado la opción: ${d.name}. En breve un asesor te responderá.`
    }))

    await http.post('/bot-flows/default', {
      name: 'Menú Principal de Bienvenida',
      greeting_message: botConfig.greeting_message,
      is_active: botConfig.is_active,
      handoff_to_ai: botConfig.handoff_to_ai,
      fallback_message: botConfig.fallback_message,
      options,
    })

    notify.success({
      message: botConfig.is_active 
        ? 'Menú de Bienvenida activado y guardado correctamente.'
        : 'Menú automático desactivado. Los mensajes entrarán directamente a la bandeja general.',
    })
  } catch (err: any) {
    notify.error({ message: 'Error al guardar configuración: ' + (err.message || 'Error de red') })
  } finally {
    savingBotConfig.value = false
  }
}

function openCreateDialog() {
  currentDept.value = null
  form.name = ''
  form.color = '#10b981'
  form.greeting = ''
  form.handoff_to_ai = true
  isDialogOpen.value = true
}

function editDept(dept: DepartmentItem) {
  currentDept.value = dept
  form.name = dept.name
  form.color = dept.color
  form.greeting = dept.greeting
  form.handoff_to_ai = true
  isDialogOpen.value = true
}

async function saveDept() {
  if (!form.name.trim()) return

  try {
    if (currentDept.value) {
      await http.put(`/queues/${currentDept.value.id}`, {
        name: form.name,
        color: form.color,
        greeting_message: form.greeting,
      })
      currentDept.value.name = form.name
      currentDept.value.color = form.color
      currentDept.value.greeting = form.greeting
      notify.success({ message: 'Fila de atención actualizada.' })
    } else {
      const res = await http.post('/queues', {
        name: form.name,
        color: form.color,
        greeting_message: form.greeting,
      })
      const created = res.data?.data
      departments.value.push({
        id: created?.id || Date.now().toString(),
        name: form.name,
        color: form.color,
        greeting: form.greeting,
        business_hours: true,
        agents_count: 1,
      })
      notify.success({ message: 'Fila de atención creada.' })
    }
  } catch {
    if (currentDept.value) {
      currentDept.value.name = form.name
      currentDept.value.color = form.color
      currentDept.value.greeting = form.greeting
    } else {
      departments.value.push({
        id: Date.now().toString(),
        name: form.name,
        color: form.color,
        greeting: form.greeting,
        business_hours: true,
        agents_count: 1,
      })
    }
    notify.success({ message: 'Guardado localmente.' })
  }

  isDialogOpen.value = false
}

async function deleteDept(id: string) {
  try {
    await http.delete(`/queues/${id}`)
    notify.warning({ message: 'Fila eliminada.' })
  } catch {
    notify.warning({ message: 'Eliminado localmente.' })
  }
  departments.value = departments.value.filter((d) => d.id !== id)
}
</script>

<style scoped lang="scss">
.xf-departments-page {
  padding: 28px 36px;
  background-color: var(--crm-bg-app);
  min-height: calc(100vh - 56px);
}

.border-bottom-subtle {
  border-bottom: 1px solid var(--crm-color-border);
}

.xf-panel-card {
  background-color: var(--crm-bg-card) !important;
  border: 1px solid var(--crm-color-border) !important;
  border-radius: 14px !important;
  padding: 20px 24px;
}

.xf-bot-icon-circle {
  width: 48px;
  height: 48px;
  border-radius: 50%;
  background: rgba(20, 184, 166, 0.12);
  border: 1px solid rgba(20, 184, 166, 0.3);
  display: flex;
  align-items: center;
  justify-content: center;
}

.whatsapp-preview-box {
  background-color: #0b141a;
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
}

.whatsapp-preview-header {
  background-color: #1f2c34;
  padding: 10px 14px;
  display: flex;
  align-items: center;
  border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}

.whatsapp-preview-body {
  position: relative;
  padding: 16px;
  background-image: radial-gradient(#1f2c34 1px, transparent 1px);
  background-size: 16px 16px;
  background-color: #0c1317;
  min-height: 250px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.chat-bubble {
  max-width: 88%;
  padding: 8px 12px;
  border-radius: 8px;
  font-size: 0.8rem;
  line-height: 1.35;
  position: relative;

  &--incoming {
    align-self: flex-start;
    background-color: #202c33;
    color: #e9edef;
    border-bottom-left-radius: 2px;
  }

  &--outgoing {
    align-self: flex-end;
    background-color: #005c4b;
    color: #e9edef;
    border-bottom-right-radius: 2px;
  }

  .bubble-text {
    word-break: break-word;
  }

  .bubble-time {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    font-size: 0.65rem;
    color: rgba(255, 255, 255, 0.6);
    margin-top: 4px;
  }
}

.preview-disabled-overlay {
  position: absolute;
  inset: 0;
  background: rgba(15, 23, 42, 0.88);
  backdrop-filter: blur(2px);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  padding: 20px;
  z-index: 5;
}

.xf-filter-input {
  min-width: 260px;
  .q-field__control {
    background: var(--crm-bg-card) !important;
    border-radius: 8px !important;
    height: 38px !important;
    min-height: 38px !important;
    font-size: 0.85rem;
  }
}

.xf-table-card {
  background-color: var(--crm-bg-card) !important;
  border: 1px solid var(--crm-color-border) !important;
  border-radius: 14px !important;
  overflow: hidden;
}

.xf-table {
  background-color: transparent !important;

  thead tr {
    border-bottom: 1px solid var(--crm-color-border);
    th {
      font-size: 0.72rem;
      font-weight: 800;
      letter-spacing: 0.06em;
      color: var(--crm-color-muted);
      padding: 14px 18px;
    }
  }

  tbody tr {
    border-bottom: 1px solid var(--crm-color-border);
    transition: background-color var(--crm-transition);

    &:hover {
      background-color: var(--crm-bg-card-hover);
    }

    td {
      padding: 14px 18px;
      font-size: 0.86rem;
    }
  }
}

.xf-dept-color-bar {
  width: 4px;
  height: 38px;
  border-radius: 4px;
  flex-shrink: 0;
}

.xf-badge-soft {
  display: inline-flex;
  align-items: center;
  font-size: 0.74rem;
  font-weight: 600;
  color: var(--crm-color-muted);
  background: rgba(255, 255, 255, 0.04);
  border: 1px solid var(--crm-color-border);
  padding: 3px 8px;
  border-radius: 6px;
}

.xf-bot-status-pill {
  display: inline-flex;
  align-items: center;
  font-size: 0.72rem;
  font-weight: 700;
  color: #f59e0b;
  background: rgba(245, 158, 11, 0.12);
  border: 1px solid rgba(245, 158, 11, 0.3);
  padding: 3px 10px;
  border-radius: 10px;
}

.xf-color-picker-input {
  width: 38px;
  height: 38px;
  border: none;
  background: transparent;
  cursor: pointer;
}

.xf-modal-card {
  background-color: #0f172a !important;
  border: 1px solid var(--crm-color-border) !important;
  border-radius: 16px !important;
  padding: 12px;
}
</style>
