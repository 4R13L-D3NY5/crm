<template>
  <div class="xf-users-page">
    <!-- Header -->
    <div class="row items-center justify-between q-mb-lg">
      <div>
        <h1 class="text-h5 text-bold text-white q-my-none">Operadores & Equipo</h1>
        <p class="text-caption text-grey-4 q-mt-xs q-mb-none">
          Gestión de miembros del equipo, roles de acceso, asignación de filas y control de presencia en vivo.
        </p>
      </div>

      <div class="row items-center q-gutter-sm">
        <q-btn
          label="+ Agregar Operador"
          unelevated
          no-caps
          class="xf-btn-primary"
          @click="openCreateDialog"
        />
      </div>
    </div>

    <!-- Pestañas: Operadores | Perfiles y permisos -->
    <div class="xf-subnav-tabs q-mb-lg">
      <button
        class="xf-subnav-btn"
        :class="{ 'xf-subnav-btn--active': activeTab === 'users' }"
        @click="activeTab = 'users'"
      >
        <q-icon name="sym_r_group" size="18px" />
        <span>Operadores Activos</span>
      </button>

      <button
        class="xf-subnav-btn"
        :class="{ 'xf-subnav-btn--active': activeTab === 'roles' }"
        @click="activeTab = 'roles'"
      >
        <q-icon name="sym_r_shield_person" size="18px" />
        <span>Perfiles & Permisos</span>
      </button>
    </div>

    <!-- Contenido: Usuarios -->
    <div v-if="activeTab === 'users'">
      <!-- Barra de Filtros -->
      <div class="row items-center justify-between q-col-gutter-sm q-mb-md">
        <div class="col-12 col-sm-8 row items-center q-gutter-sm">
          <q-input
            v-model="search"
            dense
            outlined
            dark
            placeholder="Buscar por nombre o correo..."
            class="xf-filter-input"
          >
            <template #prepend>
              <q-icon name="sym_r_search" size="18px" color="grey-5" />
            </template>
          </q-input>

          <q-select
            v-model="selectedRoleFilter"
            :options="roleFilterOptions"
            emit-value
            map-options
            dense
            outlined
            dark
            class="xf-filter-select"
          />
        </div>
      </div>

      <!-- Tabla de Operadores -->
      <q-card flat bordered class="xf-table-card">
        <q-markup-table flat dark class="xf-table">
          <thead>
            <tr>
              <th class="text-left">OPERADOR</th>
              <th class="text-left">ROL DE ACCESO</th>
              <th class="text-left">FILAS / COLAS ASIGNADAS</th>
              <th class="text-left">ESTADO DE PRESENCIA</th>
              <th class="text-right">ACCIONES</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="user in filteredUsers" :key="user.id" class="xf-table-row">
              <!-- Nombre y Avatar -->
              <td class="text-left">
                <div class="row items-center q-gutter-x-sm">
                  <q-avatar size="34px" :style="{ backgroundColor: getAvatarColor(user.name) }" text-color="white" class="text-bold text-caption">
                    {{ user.name.charAt(0).toUpperCase() }}
                  </q-avatar>
                  <div>
                    <div class="text-bold text-white">{{ user.name }}</div>
                    <div class="text-caption text-grey-5">{{ user.email }}</div>
                  </div>
                </div>
              </td>

              <!-- Rol -->
              <td class="text-left">
                <span class="xf-role-chip" :class="`xf-role-chip--${user.role}`">
                  {{ formatRole(user.role) }}
                </span>
              </td>

              <!-- Colas Asignadas -->
              <td class="text-left">
                <div class="row q-gutter-xs">
                  <span
                    v-for="q in user.queues"
                    :key="q.id"
                    class="xf-queue-tag"
                    :style="{ borderColor: q.color || '#10b981' }"
                  >
                    {{ q.name }}
                  </span>
                  <span v-if="!user.queues?.length" class="text-caption text-grey-6 italic">Sin filas</span>
                </div>
              </td>

              <!-- Presencia en Vivo -->
              <td class="text-left">
                <span
                  class="xf-presence-pill cursor-pointer"
                  :class="`xf-presence-pill--${user.presence_status || 'offline'}`"
                  @click="togglePresence(user)"
                >
                  <span class="xf-presence-dot"></span>
                  <span>{{ formatPresence(user.presence_status) }}</span>
                </span>
              </td>

              <!-- Acciones -->
              <td class="text-right">
                <div class="row justify-end q-gutter-xs">
                  <q-btn flat round dense icon="sym_r_tune" color="teal-4" size="sm" @click="editUser(user)">
                    <q-tooltip>Editar permisos y colas</q-tooltip>
                  </q-btn>
                  <q-btn flat round dense icon="sym_r_delete" color="negative" size="sm" @click="deleteUser(user.id)">
                    <q-tooltip>Desvincular Operador</q-tooltip>
                  </q-btn>
                </div>
              </td>
            </tr>
          </tbody>
        </q-markup-table>
      </q-card>
    </div>

    <!-- Contenido: Roles y Permisos -->
    <div v-else>
      <div class="row q-col-gutter-md">
        <div class="col-12 col-md-4">
          <q-card flat bordered class="xf-role-card q-pa-md">
            <div class="text-subtitle1 text-bold text-white">👑 Administrador</div>
            <div class="text-caption text-grey-4 q-mt-xs">Acceso total a configuraciones, integraciones, reportes y supervisión.</div>
          </q-card>
        </div>
        <div class="col-12 col-md-4">
          <q-card flat bordered class="xf-role-card q-pa-md">
            <div class="text-subtitle1 text-bold text-white">👁️ Supervisor Fantasma</div>
            <div class="text-caption text-grey-4 q-mt-xs">Monitoreo de tickets en vivo, notas internas y susurro a operadores sin que el cliente lo vea.</div>
          </q-card>
        </div>
        <div class="col-12 col-md-4">
          <q-card flat bordered class="xf-role-card q-pa-md">
            <div class="text-subtitle1 text-bold text-white">🎧 Operador / Agente</div>
            <div class="text-caption text-grey-4 q-mt-xs">Atención de chats asignados en sus filas, envío de notas de voz y uso de respuestas rápidas.</div>
          </q-card>
        </div>
      </div>
    </div>

    <!-- Modal Crear / Editar Operador -->
    <q-dialog v-model="isDialogOpen">
      <q-card style="width: 580px; max-width: 95vw" class="xf-modal-card q-pa-md">
        <q-card-section>
          <div class="text-h6 text-bold text-white">
            {{ selectedUser ? 'Editar Operador' : 'Nuevo Operador' }}
          </div>
          <div class="text-caption text-grey-4">Asigna las credenciales, rol institucional y departamentos de atención.</div>
        </q-card-section>

        <q-card-section class="q-gutter-y-md">
          <q-input v-model="form.name" label="Nombre Completo" outlined dark dense />
          <q-input v-model="form.email" label="Correo Electrónico" outlined dark dense :disable="!!selectedUser" />
          <q-input v-if="!selectedUser" v-model="form.password" label="Contraseña Temporal" type="password" outlined dark dense />

          <q-select
            v-model="form.role"
            :options="[
              { label: 'Administrador', value: 'admin' },
              { label: 'Supervisor', value: 'supervisor' },
              { label: 'Operador / Agente', value: 'agent' },
            ]"
            emit-value
            map-options
            label="Rol de Acceso"
            outlined
            dark
            dense
          />

          <div class="text-caption text-grey-4 q-mt-sm">Filas / Departamentos Asignados:</div>
          <div class="row q-gutter-xs">
            <q-checkbox
              v-for="q in queueOptions"
              :key="q.id"
              v-model="form.queue_ids"
              :val="q.id"
              :label="q.name"
              dark
              dense
              class="q-mr-md"
            />
          </div>
        </q-card-section>

        <q-card-actions align="right">
          <q-btn flat label="Cancelar" color="grey-4" no-caps v-close-popup />
          <q-btn
            unelevated
            label="Guardar Operador"
            color="primary"
            no-caps
            class="xf-btn-primary"
            @click="saveUser"
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

const activeTab = ref<'users' | 'roles'>('users')
const search = ref('')
const selectedRoleFilter = ref('all')
const isDialogOpen = ref(false)
const selectedUser = ref<any>(null)

const roleFilterOptions = [
  { label: 'Todos los roles', value: 'all' },
  { label: 'Administradores', value: 'admin' },
  { label: 'Supervisores', value: 'supervisor' },
  { label: 'Operadores', value: 'agent' },
]

const queueOptions = ref<any[]>([
  { id: '1', name: 'Admisiones Pregrado', color: '#10b981' },
  { id: '2', name: 'Atención Financiera', color: '#06b6d4' },
  { id: '3', name: 'Soporte Campus', color: '#8b5cf6' },
])

const users = ref<any[]>([
  {
    id: '1',
    name: 'Admin UNITEPC',
    email: 'admin@unitepc.edu.bo',
    role: 'admin',
    presence_status: 'online',
    queues: [{ id: '1', name: 'Admisiones Pregrado', color: '#10b981' }],
  },
  {
    id: '2',
    name: 'Carla Guzman',
    email: 'carla.guzman@unitepc.edu.bo',
    role: 'supervisor',
    presence_status: 'busy',
    queues: [{ id: '1', name: 'Admisiones Pregrado', color: '#10b981' }, { id: '2', name: 'Atención Financiera', color: '#06b6d4' }],
  },
  {
    id: '3',
    name: 'Alvaro Orellana',
    email: 'alvaro@correo.com',
    role: 'agent',
    presence_status: 'online',
    queues: [{ id: '1', name: 'Admisiones Pregrado', color: '#10b981' }],
  },
])

const form = reactive({
  name: '',
  email: '',
  password: '',
  role: 'agent',
  queue_ids: [] as string[],
})

onMounted(async () => {
  try {
    const res = await http.get('/users')
    if (res.data?.data && res.data.data.length > 0) {
      users.value = res.data.data
    }

    const qRes = await http.get('/queues')
    if (qRes.data?.data && qRes.data.data.length > 0) {
      queueOptions.value = qRes.data.data
    }
  } catch {
    // Mantener datos demo
  }
})

const filteredUsers = computed(() => {
  return users.value.filter((u) => {
    const matchesSearch =
      !search.value ||
      u.name.toLowerCase().includes(search.value.toLowerCase()) ||
      u.email.toLowerCase().includes(search.value.toLowerCase())

    const matchesRole = selectedRoleFilter.value === 'all' || u.role === selectedRoleFilter.value

    return matchesSearch && matchesRole
  })
})

function getAvatarColor(name: string) {
  const colors = ['#10b981', '#06b6d4', '#8b5cf6', '#f59e0b', '#ec4899', '#3b82f6']
  let hash = 0
  for (let i = 0; i < name.length; i++) hash = name.charCodeAt(i) + ((hash << 5) - hash)
  return colors[Math.abs(hash) % colors.length]
}

function formatRole(role: string) {
  if (role === 'admin') return 'Administrador'
  if (role === 'supervisor') return 'Supervisor'
  return 'Operador'
}

function formatPresence(status: string) {
  if (status === 'online') return 'Disponible (Online)'
  if (status === 'busy') return 'En Pausa / Almuerzo'
  return 'Desconectado'
}

function togglePresence(user: any) {
  const nextStatus = user.presence_status === 'online' ? 'busy' : user.presence_status === 'busy' ? 'offline' : 'online'
  user.presence_status = nextStatus
  http.put(`/users/${user.id}/presence`, { presence_status: nextStatus }).catch(() => {})
  notify.info({ message: `Estado de ${user.name} cambiado a: ${formatPresence(nextStatus)}` })
}

function openCreateDialog() {
  selectedUser.value = null
  form.name = ''
  form.email = ''
  form.password = ''
  form.role = 'agent'
  form.queue_ids = []
  isDialogOpen.value = true
}

function editUser(user: any) {
  selectedUser.value = user
  form.name = user.name
  form.email = user.email
  form.role = user.role
  form.queue_ids = (user.queues || []).map((q: any) => q.id)
  isDialogOpen.value = true
}

async function saveUser() {
  if (!form.name.trim() || !form.email.trim()) return

  try {
    if (selectedUser.value) {
      await http.put(`/users/${selectedUser.value.id}`, {
        name: form.name,
        role: form.role,
        queue_ids: form.queue_ids,
      })
      selectedUser.value.name = form.name
      selectedUser.value.role = form.role
      selectedUser.value.queues = queueOptions.value.filter((q) => form.queue_ids.includes(q.id))
      notify.success({ message: 'Operador actualizado.' })
    } else {
      const res = await http.post('/users', {
        name: form.name,
        email: form.email,
        password: form.password || '12345678',
        role: form.role,
        queue_ids: form.queue_ids,
      })
      const created = res.data?.data
      users.value.push({
        id: created?.id || Date.now().toString(),
        name: form.name,
        email: form.email,
        role: form.role,
        presence_status: 'offline',
        queues: queueOptions.value.filter((q) => form.queue_ids.includes(q.id)),
      })
      notify.success({ message: 'Operador creado con éxito.' })
    }
  } catch {
    if (selectedUser.value) {
      selectedUser.value.name = form.name
      selectedUser.value.role = form.role
      selectedUser.value.queues = queueOptions.value.filter((q) => form.queue_ids.includes(q.id))
    } else {
      users.value.push({
        id: Date.now().toString(),
        name: form.name,
        email: form.email,
        role: form.role,
        presence_status: 'offline',
        queues: queueOptions.value.filter((q) => form.queue_ids.includes(q.id)),
      })
    }
    notify.success({ message: 'Guardado localmente.' })
  }

  isDialogOpen.value = false
}

async function deleteUser(id: string) {
  try {
    await http.delete(`/users/${id}`)
    notify.warning({ message: 'Operador desvinculado.' })
  } catch {
    notify.warning({ message: 'Eliminado localmente.' })
  }
  users.value = users.value.filter((u) => u.id !== id)
}
</script>

<style scoped lang="scss">
.xf-users-page {
  padding: 28px 36px;
  background-color: var(--crm-bg-app);
  min-height: calc(100vh - 56px);
}

.xf-subnav-tabs {
  display: flex;
  gap: 8px;
  border-bottom: 1px solid var(--crm-color-border);
  padding-bottom: 8px;
}

.xf-subnav-btn {
  display: flex;
  align-items: center;
  gap: 8px;
  background: transparent;
  border: none;
  color: var(--crm-color-muted);
  padding: 8px 16px;
  border-radius: 10px;
  font-size: 0.88rem;
  font-weight: 600;
  cursor: pointer;
  transition: all var(--crm-transition);

  &:hover {
    background: rgba(255, 255, 255, 0.04);
    color: var(--crm-color-ink);
  }

  &--active {
    background: var(--crm-bg-card);
    color: #ffffff;
    border: 1px solid var(--crm-color-border);
  }
}

.xf-filter-input {
  min-width: 250px;
  .q-field__control {
    background: var(--crm-bg-card) !important;
    border-radius: 8px !important;
    height: 38px !important;
    min-height: 38px !important;
    font-size: 0.85rem;
  }
}

.xf-filter-select {
  min-width: 180px;
  .q-field__control {
    background: var(--crm-bg-card) !important;
    border-radius: 8px !important;
    height: 38px !important;
    min-height: 38px !important;
    font-size: 0.85rem;
  }
}

.xf-table-card,
.xf-role-card {
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
      padding: 12px 18px;
      font-size: 0.86rem;
    }
  }
}

.xf-role-chip {
  display: inline-flex;
  font-size: 0.72rem;
  font-weight: 700;
  padding: 3px 10px;
  border-radius: 8px;

  &--admin {
    background: rgba(168, 85, 247, 0.15);
    color: #c084fc;
    border: 1px solid rgba(168, 85, 247, 0.3);
  }

  &--supervisor {
    background: rgba(245, 158, 11, 0.15);
    color: #fbbf24;
    border: 1px solid rgba(245, 158, 11, 0.3);
  }

  &--agent {
    background: rgba(6, 182, 212, 0.15);
    color: #22d3ee;
    border: 1px solid rgba(6, 182, 212, 0.3);
  }
}

.xf-queue-tag {
  font-size: 0.72rem;
  color: #ffffff;
  background: rgba(255, 255, 255, 0.04);
  border: 1px solid;
  padding: 2px 8px;
  border-radius: 6px;
}

.xf-presence-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 0.72rem;
  font-weight: 700;
  padding: 3px 10px;
  border-radius: 12px;

  &--online {
    background: rgba(16, 185, 129, 0.15);
    color: #10b981;
    border: 1px solid rgba(16, 185, 129, 0.3);
    .xf-presence-dot { background-color: #10b981; box-shadow: 0 0 6px rgba(16, 185, 129, 0.8); }
  }

  &--busy {
    background: rgba(245, 158, 11, 0.15);
    color: #f59e0b;
    border: 1px solid rgba(245, 158, 11, 0.3);
    .xf-presence-dot { background-color: #f59e0b; box-shadow: 0 0 6px rgba(245, 158, 11, 0.8); }
  }

  &--offline {
    background: rgba(100, 116, 139, 0.15);
    color: #94a3b8;
    border: 1px solid rgba(100, 116, 139, 0.3);
    .xf-presence-dot { background-color: #94a3b8; }
  }
}

.xf-presence-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
}

.xf-modal-card {
  background-color: #0f172a !important;
  border: 1px solid var(--crm-color-border) !important;
  border-radius: 16px !important;
  padding: 12px;
}
</style>
