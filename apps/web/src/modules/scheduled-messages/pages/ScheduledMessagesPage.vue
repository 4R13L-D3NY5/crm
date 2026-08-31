<template>
  <div class="whaticket-scheduled-page">
    <!-- Header -->
    <div class="row items-center justify-between q-mb-md">
      <div>
        <h1 class="text-h5 text-bold text-white q-my-none">Mensajes Programados</h1>
        <p class="text-caption text-grey-4 q-mt-xs q-mb-none">
          Automatiza envíos diferidos en fechas y horas específicas hacia tus contactos.
        </p>
      </div>

      <q-btn
        color="primary"
        label="+ Programar mensaje"
        unelevated
        no-caps
        class="whaticket-btn-primary"
        @click="isDialogOpen = true"
      />
    </div>

    <!-- Filtros Superiores -->
    <div class="row items-center justify-between q-col-gutter-sm q-mb-md">
      <div class="col-12 col-sm-8 row items-center q-gutter-sm">
        <q-select
          v-model="filterStatus"
          :options="statusOptions"
          emit-value
          map-options
          dense
          outlined
          dark
          class="whaticket-filter-select"
        />

        <q-input
          v-model="filterDate"
          dense
          outlined
          dark
          placeholder="AAAA-MM-DD"
          class="whaticket-filter-input whaticket-filter-input--date"
        >
          <template #append>
            <q-icon name="sym_r_calendar_today" size="16px" color="grey-5" />
          </template>
        </q-input>

        <q-input
          v-model="searchContact"
          dense
          outlined
          dark
          placeholder="Buscar contacto"
          class="whaticket-filter-input"
        >
          <template #prepend>
            <q-icon name="sym_r_search" size="18px" color="grey-5" />
          </template>
        </q-input>
      </div>

      <div class="col-12 col-sm-4 row items-center justify-end q-gutter-sm">
        <div class="whaticket-view-toggle">
          <button class="whaticket-view-btn whaticket-view-btn--active">
            <q-icon name="sym_r_list" size="18px" />
          </button>
        </div>
      </div>
    </div>

    <!-- Tabla o Estado Vacío -->
    <div v-if="filteredScheduled.length === 0" class="whaticket-empty-scheduled-card">
      <div class="whaticket-empty-icon-wrap">
        <q-icon name="sym_r_schedule" size="48px" color="grey-5" />
      </div>
      <div class="text-h6 text-bold text-white q-mt-md">
        No se encontraron mensajes programados
      </div>
      <div class="text-caption text-grey-4 q-mt-xs q-mb-lg">
        Comience programando un mensaje para que aparezca aquí.
      </div>
      <q-btn
        to="/app/conversations"
        color="primary"
        label="Ir a la página de chats"
        unelevated
        no-caps
        class="whaticket-btn-primary"
      />
    </div>

    <q-card v-else flat bordered class="whaticket-table-card">
      <q-markup-table flat dark class="whaticket-table">
        <thead>
          <tr>
            <th class="text-left">CONTACTO</th>
            <th class="text-left">MENSAJE</th>
            <th class="text-left">PROGRAMADO PARA</th>
            <th class="text-left">ESTADO</th>
            <th class="text-right">ACCIONES</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in filteredScheduled" :key="item.id" class="whaticket-table-row">
            <td class="text-left text-bold text-white">
              {{ item.contact_name }} ({{ item.phone }})
            </td>
            <td class="text-left text-grey-3 ellipsis-2-lines">
              {{ item.body }}
            </td>
            <td class="text-left text-grey-4 text-caption">
              {{ item.schedule_at }}
            </td>
            <td class="text-left">
              <q-badge
                :color="item.status === 'sent' ? 'positive' : 'warning'"
                class="text-bold"
              >
                {{ item.status === 'sent' ? 'Enviado' : 'Pendiente' }}
              </q-badge>
            </td>
            <td class="text-right">
              <q-btn
                flat
                round
                dense
                icon="sym_r_delete"
                color="negative"
                size="sm"
                @click="deleteScheduled(item.id)"
              />
            </td>
          </tr>
        </tbody>
      </q-markup-table>
    </q-card>

    <!-- Modal Programar Mensaje -->
    <q-dialog v-model="isDialogOpen">
      <q-card style="width: 520px; max-width: 95vw" class="whaticket-modal-card q-pa-md">
        <q-card-section>
          <div class="text-h6 text-bold text-white">Programar Mensaje</div>
          <div class="text-caption text-grey-4">Elige destinatario, fecha y hora de entrega automática.</div>
        </q-card-section>

        <q-card-section class="q-gutter-y-md">
          <q-input
            v-model="form.phone"
            label="Número o Teléfono WhatsApp"
            placeholder="+591 71234567"
            outlined
            dark
            dense
          />
          <q-input
            v-model="form.contact_name"
            label="Nombre del contacto"
            outlined
            dark
            dense
          />
          <q-input
            v-model="form.schedule_at"
            label="Fecha y hora (AAAA-MM-DD HH:mm:ss)"
            outlined
            dark
            dense
          />
          <q-input
            v-model="form.body"
            label="Contenido del mensaje"
            type="textarea"
            rows="3"
            outlined
            dark
          />
        </q-card-section>

        <q-card-actions align="right">
          <q-btn flat label="Cancelar" color="grey-4" no-caps v-close-popup />
          <q-btn
            unelevated
            label="Programar"
            color="primary"
            no-caps
            class="whaticket-btn-primary"
            @click="saveScheduled"
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

const filterStatus = ref('all')
const filterDate = ref('')
const searchContact = ref('')
const isDialogOpen = ref(false)

const statusOptions = [
  { label: 'Todos los mensajes', value: 'all' },
  { label: 'Pendientes', value: 'pending' },
  { label: 'Enviados', value: 'sent' },
]

interface ScheduledItem {
  id: string
  contact_name: string
  phone: string
  body: string
  schedule_at: string
  status: 'pending' | 'sent'
}

const scheduledList = ref<ScheduledItem[]>([
  {
    id: '1',
    contact_name: 'Carlos Mendoza',
    phone: '+591 77889901',
    body: 'Hola Carlos, recordatorio de tu reunión agendada para el día de mañana.',
    schedule_at: '2026-08-30 09:00:00',
    status: 'pending',
  },
])

onMounted(async () => {
  try {
    const response = await http.get('/scheduled-messages')
    if (response.data?.data && response.data.data.length > 0) {
      scheduledList.value = response.data.data.map((s: any) => ({
        id: s.id,
        contact_name: s.contact?.name ?? 'Contacto',
        phone: s.recipient_phone,
        body: s.body,
        schedule_at: s.scheduled_at,
        status: s.status,
      }))
    }
  } catch {
    // Mantener datos locales
  }
})

const form = reactive({
  contact_name: '',
  phone: '',
  body: '',
  schedule_at: '2026-08-30 10:00:00',
})

const filteredScheduled = computed(() => {
  return scheduledList.value.filter((item) => {
    const matchStatus = filterStatus.value === 'all' || item.status === filterStatus.value
    const matchContact = !searchContact.value || item.contact_name.toLowerCase().includes(searchContact.value.toLowerCase()) || item.phone.includes(searchContact.value)
    return matchStatus && matchContact
  })
})

async function saveScheduled() {
  if (!form.phone.trim() || !form.body.trim()) return

  try {
    const response = await http.post('/scheduled-messages', {
      recipient_phone: form.phone,
      contact_name: form.contact_name,
      body: form.body,
      scheduled_at: form.schedule_at,
    })
    const created = response.data?.data
    scheduledList.value.unshift({
      id: created.id,
      contact_name: form.contact_name || 'Contacto',
      phone: form.phone,
      body: form.body,
      schedule_at: form.schedule_at,
      status: 'pending',
    })
    notify.success({ message: 'Mensaje programado con éxito.' })
  } catch {
    scheduledList.value.unshift({
      id: Date.now().toString(),
      contact_name: form.contact_name || 'Contacto',
      phone: form.phone,
      body: form.body,
      schedule_at: form.schedule_at,
      status: 'pending',
    })
    notify.success({ message: 'Guardado localmente.' })
  }

  isDialogOpen.value = false
  form.contact_name = ''
  form.phone = ''
  form.body = ''
}

async function deleteScheduled(id: string) {
  try {
    await http.delete(`/scheduled-messages/${id}`)
    notify.warning({ message: 'Mensaje programado cancelado.' })
  } catch {
    notify.warning({ message: 'Cancelado localmente.' })
  }
  scheduledList.value = scheduledList.value.filter((s) => s.id !== id)
}
</script>

<style scoped lang="scss">
.whaticket-scheduled-page {
  padding: 24px 32px;
  background-color: var(--crm-bg-app);
  min-height: calc(100vh - 52px);
}

.whaticket-filter-input,
.whaticket-filter-select {
  min-width: 170px;
  .q-field__control {
    background: #182229 !important;
    border-radius: 8px !important;
    height: 36px !important;
    min-height: 36px !important;
    font-size: 0.85rem;
  }
  &--date {
    max-width: 150px;
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

.whaticket-empty-scheduled-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 60px 24px;
  background-color: #182229;
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 14px;
  text-align: center;
}

.whaticket-empty-icon-wrap {
  width: 72px;
  height: 72px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.05);
  display: flex;
  align-items: center;
  justify-content: center;
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
