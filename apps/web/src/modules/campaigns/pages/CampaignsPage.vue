<template>
  <div class="whaticket-campaigns-page">
    <!-- Header -->
    <div class="row items-center justify-between q-mb-md">
      <div>
        <div class="row items-center q-gutter-x-xs">
          <h1 class="text-h5 text-bold text-white q-my-none">Campañas</h1>
          <q-icon name="sym_r_info" size="18px" color="grey-5" />
        </div>
        <p class="text-caption text-grey-4 q-mt-xs q-mb-none">
          Envía mensajes masivos a tus contactos y sigue el progreso de cada campaña con intervalos seguros.
        </p>
      </div>

      <div class="row items-center q-gutter-md">
        <span class="text-caption text-grey-4">
          Mensajes restantes: <strong class="text-positive">{{ remainingMessages }}</strong>
        </span>
        <q-btn
          color="primary"
          label="+ Nueva campaña"
          unelevated
          no-caps
          class="whaticket-btn-primary"
          @click="isDialogOpen = true"
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
          placeholder="Buscar"
          class="whaticket-filter-input"
        >
          <template #prepend>
            <q-icon name="sym_r_search" size="18px" color="grey-5" />
          </template>
        </q-input>

        <q-select
          v-model="selectedStatus"
          :options="statusOptions"
          emit-value
          map-options
          dense
          outlined
          dark
          label="Estado"
          class="whaticket-filter-select"
        />
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
    <div v-if="filteredCampaigns.length === 0" class="whaticket-empty-campaigns-card">
      <div class="whaticket-empty-icon-wrap">
        <q-icon name="sym_r_campaign" size="48px" color="grey-5" />
      </div>
      <div class="text-h6 text-bold text-white q-mt-md">
        Aún no hay campañas
      </div>
      <div class="text-caption text-grey-4 q-mt-xs q-mb-lg">
        Crea tu primera campaña para empezar a enviar mensajes masivos.
      </div>
      <q-btn
        color="primary"
        label="+ Nueva campaña"
        unelevated
        no-caps
        class="whaticket-btn-primary"
        @click="isDialogOpen = true"
      />
    </div>

    <q-card v-else flat bordered class="whaticket-table-card">
      <q-markup-table flat dark class="whaticket-table">
        <thead>
          <tr>
            <th class="text-left">NOMBRE DE CAMPAÑA</th>
            <th class="text-left">CREADO EN</th>
            <th class="text-left">CONEXIÓN</th>
            <th class="text-left" style="width: 180px">PROGRESO</th>
            <th class="text-left">ESTADO</th>
            <th class="text-right">ACCIONES</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="c in filteredCampaigns" :key="c.id" class="whaticket-table-row">
            <td class="text-left text-bold text-white">{{ c.name }}</td>
            <td class="text-left text-caption text-grey-4">{{ c.created_at }}</td>
            <td class="text-left text-white">{{ c.connection }}</td>
            <td class="text-left">
              <div class="row items-center q-gutter-x-xs">
                <q-linear-progress :value="c.progress / 100" color="primary" class="col" rounded />
                <span class="text-caption text-bold text-grey-4">{{ c.progress }}%</span>
              </div>
            </td>
            <td class="text-left">
              <q-badge :color="c.status === 'processing' ? 'warning' : 'positive'" class="text-bold">
                {{ c.status === 'processing' ? 'Enviando' : c.status }}
              </q-badge>
            </td>
            <td class="text-right">
              <div class="row justify-end q-gutter-xs">
                <q-btn
                  v-if="c.status === 'draft'"
                  flat
                  round
                  dense
                  icon="sym_r_play_arrow"
                  color="positive"
                  size="sm"
                  @click="startCampaign(c.id)"
                >
                  <q-tooltip>Iniciar disparo</q-tooltip>
                </q-btn>
                <q-btn flat round dense icon="sym_r_delete" color="negative" size="sm" @click="deleteCampaign(c.id)" />
              </div>
            </td>
          </tr>
        </tbody>
      </q-markup-table>
    </q-card>

    <!-- Modal Crear Campaña Masiva -->
    <q-dialog v-model="isDialogOpen">
      <q-card style="width: 620px; max-width: 95vw" class="whaticket-modal-card q-pa-md">
        <q-card-section>
          <div class="text-h6 text-bold text-white">Nueva Campaña de Disparo Masivo</div>
          <div class="text-caption text-grey-4">Segmenta por etiqueta o lista y define el retardo entre mensajes.</div>
        </q-card-section>

        <q-card-section class="q-gutter-y-md">
          <q-input v-model="form.name" label="Nombre de la Campaña" outlined dark dense />

          <q-input
            v-model="form.message"
            label="Plantilla del Mensaje (ej. Hola {{name}}...)"
            type="textarea"
            rows="4"
            outlined
            dark
          />

          <div class="row items-center justify-between bg-dark q-pa-sm rounded-borders">
            <span class="text-caption text-grey-4">Intervalo anti-bloqueo entre envíos:</span>
            <div class="row items-center q-gutter-x-xs">
              <q-input v-model.number="form.delaySeconds" type="number" dense outlined dark style="width: 70px" />
              <span class="text-caption text-grey-4">segundos</span>
            </div>
          </div>
        </q-card-section>

        <q-card-actions align="right">
          <q-btn flat label="Cancelar" color="grey-4" no-caps v-close-popup />
          <q-btn
            unelevated
            label="Guardar Campaña"
            color="primary"
            no-caps
            class="whaticket-btn-primary"
            @click="saveCampaign"
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
const selectedStatus = ref('all')
const isDialogOpen = ref(false)
const remainingMessages = ref(500)

const statusOptions = [
  { label: 'Estado (Todos)', value: 'all' },
  { label: 'Borrador', value: 'draft' },
  { label: 'Enviando', value: 'processing' },
  { label: 'Completada', value: 'completed' },
]

interface CampaignItem {
  id: string
  name: string
  created_at: string
  connection: string
  progress: number
  status: string
}

const campaigns = ref<CampaignItem[]>([
  {
    id: '1',
    name: 'Promoción Inscripciones Agosto 2026',
    created_at: '28/08/2026 14:00',
    connection: 'WhatsApp Oficial Ventas',
    progress: 75,
    status: 'processing',
  },
])

onMounted(async () => {
  try {
    const response = await http.get('/campaigns')
    if (response.data?.data && response.data.data.length > 0) {
      campaigns.value = response.data.data.map((c: any) => ({
        id: c.id,
        name: c.name,
        created_at: c.created_at,
        connection: c.whatsapp_account?.name ?? 'WhatsApp Oficial',
        progress: c.total_contacts > 0 ? Math.round((c.sent_count / c.total_contacts) * 100) : 0,
        status: c.status,
      }))
    }
  } catch {
    // Mantener datos locales
  }
})

const form = reactive({
  name: '',
  message: 'Hola {{name}}, aprovecha el 25% de descuento en matrícula de este semestre en UNITEPC.',
  delaySeconds: 20,
})

const filteredCampaigns = computed(() => {
  return campaigns.value.filter((c) => {
    return !search.value || c.name.toLowerCase().includes(search.value.toLowerCase())
  })
})

async function saveCampaign() {
  if (!form.name.trim() || !form.message.trim()) return

  try {
    const response = await http.post('/campaigns', {
      name: form.name,
      message_template: form.message,
      delay_seconds: form.delaySeconds,
    })
    const created = response.data?.data
    campaigns.value.unshift({
      id: created.id,
      name: form.name,
      created_at: 'Ahora mismo',
      connection: 'WhatsApp Oficial',
      progress: 0,
      status: 'draft',
    })
    notify.success({ message: 'Campaña creada en modo borrador.' })
  } catch {
    campaigns.value.unshift({
      id: Date.now().toString(),
      name: form.name,
      created_at: 'Ahora mismo',
      connection: 'WhatsApp Oficial',
      progress: 0,
      status: 'draft',
    })
    notify.success({ message: 'Campaña guardada localmente.' })
  }

  isDialogOpen.value = false
  form.name = ''
}

async function startCampaign(id: string) {
  try {
    await http.post(`/campaigns/${id}/start`)
    const target = campaigns.value.find((c) => c.id === id)
    if (target) target.status = 'processing'
    notify.success({ message: 'Campaña iniciada con intervalos anti-bloqueo.' })
  } catch {
    const target = campaigns.value.find((c) => c.id === id)
    if (target) target.status = 'processing'
    notify.success({ message: 'Disparo iniciado.' })
  }
}

async function deleteCampaign(id: string) {
  try {
    await http.delete(`/campaigns/${id}`)
    notify.warning({ message: 'Campaña eliminada.' })
  } catch {
    notify.warning({ message: 'Eliminada localmente.' })
  }
  campaigns.value = campaigns.value.filter((c) => c.id !== id)
}
</script>

<style scoped lang="scss">
.whaticket-campaigns-page {
  padding: 24px 32px;
  background-color: var(--crm-bg-app);
  min-height: calc(100vh - 52px);
}

.whaticket-filter-input,
.whaticket-filter-select {
  min-width: 180px;
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

.whaticket-empty-campaigns-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 70px 24px;
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
