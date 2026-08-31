<template>
  <div class="whaticket-quick-messages-page">
    <!-- Header -->
    <div class="q-mb-md">
      <h1 class="text-h5 text-bold text-white q-my-none">Respuestas Rápidas</h1>
      <p class="text-caption text-grey-4 q-mt-xs q-mb-none">
        Mensajes listos que el equipo inserta en el chat escribiendo "/" seguido del atajo.
      </p>
    </div>

    <!-- Filtros Superiores -->
    <div class="row items-center justify-between q-col-gutter-sm q-mb-md">
      <div class="col-12 col-sm-8 row items-center q-gutter-sm">
        <q-input
          v-model="search"
          dense
          outlined
          dark
          placeholder="Buscar por atajo o mensaje"
          class="whaticket-filter-input"
        >
          <template #prepend>
            <q-icon name="sym_r_search" size="18px" color="grey-5" />
          </template>
        </q-input>

        <q-select
          v-model="selectedDepartment"
          :options="departmentOptions"
          emit-value
          map-options
          dense
          outlined
          dark
          class="whaticket-filter-select"
        />
      </div>

      <!-- Acciones Derecha -->
      <div class="col-12 col-sm-4 row items-center justify-end q-gutter-sm">
        <div class="whaticket-view-toggle">
          <button class="whaticket-view-btn whaticket-view-btn--active">
            <q-icon name="sym_r_list" size="18px" />
          </button>
        </div>

        <q-btn
          color="primary"
          label="+ Añadir respuesta rápida"
          unelevated
          no-caps
          class="whaticket-btn-primary"
          @click="openCreateDialog"
        />
      </div>
    </div>

    <!-- Tabla de Respuestas Rápidas -->
    <q-card flat bordered class="whaticket-table-card">
      <q-markup-table flat dark class="whaticket-table">
        <thead>
          <tr>
            <th class="text-left" style="width: 220px">ATAJO</th>
            <th class="text-left">MENSAJE</th>
            <th class="text-left" style="width: 180px">DEPARTAMENTOS</th>
            <th class="text-right" style="width: 120px">ACCIONES</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in filteredQuickMessages" :key="item.id" class="whaticket-table-row">
            <td class="text-left text-bold text-primary">
              {{ item.shortcut.startsWith('/') ? item.shortcut : `/${item.shortcut}` }}
            </td>
            <td class="text-left text-grey-3 ellipsis-2-lines">
              {{ item.message }}
            </td>
            <td class="text-left text-caption text-grey-5 italic">
              {{ item.department || 'Sin departamento' }}
            </td>
            <td class="text-right">
              <div class="row justify-end q-gutter-xs">
                <q-btn
                  flat
                  round
                  dense
                  icon="sym_r_edit"
                  color="grey-4"
                  size="sm"
                  @click="editItem(item)"
                />
                <q-btn
                  flat
                  round
                  dense
                  icon="sym_r_delete"
                  color="negative"
                  size="sm"
                  @click="deleteItem(item.id)"
                />
              </div>
            </td>
          </tr>
        </tbody>
      </q-markup-table>
    </q-card>

    <!-- Modal Crear / Editar -->
    <q-dialog v-model="isDialogOpen">
      <q-card style="width: 580px; max-width: 95vw" class="whaticket-modal-card q-pa-md">
        <q-card-section>
          <div class="text-h6 text-bold text-white">
            {{ currentItem ? 'Editar Respuesta Rápida' : 'Añadir Respuesta Rápida' }}
          </div>
          <div class="text-caption text-grey-4">
            Usa atajos como <code>/bienvenida</code> para insertar este texto al chatear.
          </div>
        </q-card-section>

        <q-card-section class="q-gutter-y-md">
          <q-input
            v-model="form.shortcut"
            label="Atajo (ej. precios, horarios, promo)"
            prefix="/"
            outlined
            dark
            dense
          />

          <q-input
            v-model="form.message"
            label="Mensaje completo"
            type="textarea"
            rows="4"
            outlined
            dark
          />

          <div class="row q-gutter-xs">
            <span class="text-caption text-grey-4">Insertar variable:</span>
            <q-chip clickable dense dark color="dark" @click="form.message += ' {{name}}'">&#123;&#123;name&#125;&#125;</q-chip>
            <q-chip clickable dense dark color="dark" @click="form.message += ' {{phone}}'">&#123;&#123;phone&#125;&#125;</q-chip>
            <q-chip clickable dense dark color="dark" @click="form.message += ' {{greeting}}'">&#123;&#123;greeting&#125;&#125;</q-chip>
          </div>

          <q-select
            v-model="form.department"
            :options="['Sin departamento', 'Ventas', 'Soporte', 'Admisiones']"
            label="Departamento / Fila"
            outlined
            dark
            dense
          />
        </q-card-section>

        <q-card-actions align="right">
          <q-btn flat label="Cancelar" color="grey-4" no-caps v-close-popup />
          <q-btn
            unelevated
            label="Guardar"
            color="primary"
            no-caps
            class="whaticket-btn-primary"
            @click="saveItem"
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

interface QuickMessageItem {
  id: string
  shortcut: string
  message: string
  department: string | null
}

const search = ref('')
const selectedDepartment = ref('all')
const isDialogOpen = ref(false)
const currentItem = ref<QuickMessageItem | null>(null)

const departmentOptions = [
  { label: 'Departamentos (Todos)', value: 'all' },
  { label: 'Ventas', value: 'Ventas' },
  { label: 'Soporte', value: 'Soporte' },
  { label: 'Admisiones', value: 'Admisiones' },
]

const form = reactive({
  shortcut: '',
  message: '',
  department: 'Sin departamento',
})

const quickMessages = ref<QuickMessageItem[]>([
  { id: '1', shortcut: '/25% agosto', message: '🎁 *¡Gran Promoción!* Inscríbete con un *25% de descuento por 4 semestres* en la carrera que elijas.', department: null },
  { id: '2', shortcut: '/amable', message: 'Sería tan amable de brindarme su nombre completo y número de celular para poder registrar su solicitud.', department: null },
  { id: '3', shortcut: '/a que sede', message: '¿En qué ciudad deseas estudiar? Contamos con campus en: Cochabamba, La Paz, El Alto, Santa Cruz, Cobija, Puerto Quijarro.', department: null },
  { id: '4', shortcut: '/becas', message: '*Contamos con diferentes modalidades de Becas:* Beca Patriota, Beca Deportiva y Talento Artístico.', department: null },
])

onMounted(async () => {
  try {
    const response = await http.get('/quick-messages')
    if (response.data?.data && response.data.data.length > 0) {
      quickMessages.value = response.data.data.map((q: any) => ({
        id: q.id,
        shortcut: q.shortcode || q.shortcut,
        message: q.message,
        department: q.queue?.name ?? null,
      }))
    }
  } catch {
    // Mantener datos demo
  }
})

const filteredQuickMessages = computed(() => {
  return quickMessages.value.filter((item) => {
    const matchSearch =
      !search.value ||
      item.shortcut.toLowerCase().includes(search.value.toLowerCase()) ||
      item.message.toLowerCase().includes(search.value.toLowerCase())

    const matchDept =
      selectedDepartment.value === 'all' ||
      item.department === selectedDepartment.value

    return matchSearch && matchDept
  })
})

function openCreateDialog() {
  currentItem.value = null
  form.shortcut = ''
  form.message = ''
  form.department = 'Sin departamento'
  isDialogOpen.value = true
}

function editItem(item: QuickMessageItem) {
  currentItem.value = item
  form.shortcut = item.shortcut.replace(/^\/+/, '')
  form.message = item.message
  form.department = item.department || 'Sin departamento'
  isDialogOpen.value = true
}

async function saveItem() {
  if (!form.shortcut.trim() || !form.message.trim()) return

  const cleanShortcut = `/${form.shortcut.trim().replace(/^\/+/, '')}`

  try {
    if (currentItem.value) {
      await http.put(`/quick-messages/${currentItem.value.id}`, {
        shortcode: cleanShortcut,
        message: form.message,
      })
      currentItem.value.shortcut = cleanShortcut
      currentItem.value.message = form.message
      currentItem.value.department = form.department === 'Sin departamento' ? null : form.department
      notify.success({ message: 'Respuesta rápida actualizada.' })
    } else {
      const response = await http.post('/quick-messages', {
        shortcode: cleanShortcut,
        message: form.message,
      })
      const created = response.data?.data
      quickMessages.value.unshift({
        id: created?.id ?? Date.now().toString(),
        shortcut: cleanShortcut,
        message: form.message,
        department: form.department === 'Sin departamento' ? null : form.department,
      })
      notify.success({ message: 'Respuesta rápida creada.' })
    }
  } catch {
    if (currentItem.value) {
      currentItem.value.shortcut = cleanShortcut
      currentItem.value.message = form.message
    } else {
      quickMessages.value.unshift({
        id: Date.now().toString(),
        shortcut: cleanShortcut,
        message: form.message,
        department: form.department === 'Sin departamento' ? null : form.department,
      })
    }
    notify.success({ message: 'Guardado localmente.' })
  }

  isDialogOpen.value = false
}

async function deleteItem(id: string) {
  try {
    await http.delete(`/quick-messages/${id}`)
    notify.warning({ message: 'Respuesta rápida eliminada.' })
  } catch {
    notify.warning({ message: 'Eliminada localmente.' })
  }
  quickMessages.value = quickMessages.value.filter((q) => q.id !== id)
}
</script>

<style scoped lang="scss">
.whaticket-quick-messages-page {
  padding: 24px 32px;
  background-color: var(--crm-bg-app);
  min-height: calc(100vh - 52px);
}

.whaticket-filter-input,
.whaticket-filter-select {
  min-width: 200px;
  .q-field__control {
    background: #182229 !important;
    border-radius: 8px !important;
    height: 36px !important;
    min-height: 36px !important;
    font-size: 0.85rem;
  }
}

.whaticket-view-toggle {
  display: flex;
  background: #182229;
  border-radius: 8px;
  padding: 2px;
  border: 1px solid rgba(255, 255, 255, 0.08);
}

.whaticket-view-btn {
  background: transparent;
  border: none;
  color: #8696a0;
  padding: 4px 8px;
  border-radius: 6px;
  cursor: pointer;
  display: flex;
  align-items: center;

  &--active {
    background: #202c33;
    color: #ffffff;
  }
}

.whaticket-btn-primary {
  background-color: #00a884 !important;
  color: #ffffff !important;
  font-weight: 600;
  border-radius: 8px;
  height: 36px;
}

.whaticket-table-card {
  background-color: #182229 !important;
  border: 1px solid rgba(255, 255, 255, 0.08) !important;
  border-radius: 12px !important;
  overflow: hidden;
}

.whaticket-table {
  background-color: transparent !important;

  thead tr {
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    th {
      font-size: 0.72rem;
      font-weight: 700;
      letter-spacing: 0.05em;
      color: #8696a0;
      padding: 12px 16px;
    }
  }

  tbody tr {
    border-bottom: 1px solid rgba(255, 255, 255, 0.04);
    td {
      padding: 12px 16px;
      font-size: 0.85rem;
    }
  }
}

.whaticket-modal-card {
  background-color: #111b21 !important;
  border: 1px solid rgba(255, 255, 255, 0.1) !important;
  border-radius: 16px !important;
}
</style>
