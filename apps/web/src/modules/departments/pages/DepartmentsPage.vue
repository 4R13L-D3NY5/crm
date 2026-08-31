<template>
  <div class="xf-departments-page">
    <!-- Header -->
    <div class="row items-center justify-between q-mb-lg">
      <div>
        <h1 class="text-h5 text-bold text-white q-my-none">Filas & Enrutamiento Inteligente</h1>
        <p class="text-caption text-grey-4 q-mt-xs q-mb-none">
          Configuración de colas de atención, horarios laborales, chatbots de bienvenida y derivación automática.
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

    <!-- Barra de Filtros -->
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
          <tr v-for="dept in filteredDepartments" :key="dept.id" class="xf-table-row">
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
              <span class="xf-bot-status-pill">
                <q-icon name="sym_r_smart_toy" size="14px" color="amber-4" class="q-mr-xs" />
                <span>Árbol de Decisión Activo</span>
              </span>
            </td>

            <!-- Acciones -->
            <td class="text-right">
              <div class="row justify-end q-gutter-xs">
                <q-btn flat round dense icon="sym_r_tune" color="teal-4" size="sm" @click="editDept(dept)">
                  <q-tooltip>Configurar Bot y Enrutamiento</q-tooltip>
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
      <q-card style="width: 620px; max-width: 95vw" class="xf-modal-card q-pa-md">
        <q-card-section>
          <div class="text-h6 text-bold text-white">
            {{ currentDept ? 'Editar Fila & Enrutamiento' : 'Nueva Fila de Atención' }}
          </div>
          <div class="text-caption text-grey-4">Define el nombre de la cola, color de identificación y saludo automático.</div>
        </q-card-section>

        <q-card-section class="q-gutter-y-md">
          <q-input v-model="form.name" label="Nombre del Departamento / Fila" outlined dark dense />

          <div class="row items-center justify-between bg-dark q-pa-sm rounded-borders">
            <span class="text-caption text-grey-4">Color de Identificación:</span>
            <input type="color" v-model="form.color" class="xf-color-picker-input" />
          </div>

          <q-input
            v-model="form.greeting"
            label="Mensaje de Bienvenida / Saludo"
            type="textarea"
            rows="3"
            outlined
            dark
          />

          <div class="row items-center justify-between bg-dark q-pa-sm rounded-borders">
            <div>
              <div class="text-caption text-bold text-white">Handoff Cognitivo Hentle-AI</div>
              <div class="text-caption text-grey-5">Traspasa a la IA si el usuario hace preguntas abiertas fuera del árbol.</div>
            </div>
            <q-toggle v-model="form.handoff_to_ai" dense color="primary" />
          </div>
        </q-card-section>

        <q-card-actions align="right">
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

interface DepartmentItem {
  id: string
  name: string
  color: string
  greeting: string
  business_hours: boolean
  agents_count: number
}

const departments = ref<DepartmentItem[]>([
  {
    id: '1',
    name: 'Admisiones & Matrícula',
    color: '#10b981',
    greeting: '¡Hola! Gracias por comunicarte con Admisiones UNITEPC. ¿En qué carrera o sede deseas inscribirte?',
    business_hours: true,
    agents_count: 5,
  },
  {
    id: '2',
    name: 'Atención Financiera & Becas',
    color: '#06b6d4',
    greeting: 'Bienvenido al área financiera. Consulta sobre planes de pago y postulación a Becas.',
    business_hours: true,
    agents_count: 3,
  },
  {
    id: '3',
    name: 'Soporte Técnico Campus Virtual',
    color: '#8b5cf6',
    greeting: 'Área de soporte para plataforma virtual y accesos académicos.',
    business_hours: false,
    agents_count: 2,
  },
])

const form = reactive({
  name: '',
  color: '#10b981',
  greeting: '',
  handoff_to_ai: true,
})

onMounted(async () => {
  try {
    const res = await http.get('/queues')
    if (res.data?.data && res.data.data.length > 0) {
      departments.value = res.data.data.map((q: any) => ({
        id: q.id,
        name: q.name,
        color: q.color || '#10b981',
        greeting: q.greeting_message || '',
        business_hours: true,
        agents_count: q.users_count || 3,
      }))
    }
  } catch {
    // Mantener datos demo
  }
})

const filteredDepartments = computed(() => {
  return departments.value.filter((d) => {
    return !search.value || d.name.toLowerCase().includes(search.value.toLowerCase())
  })
})

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
