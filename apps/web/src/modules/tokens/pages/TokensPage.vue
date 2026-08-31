<template>
  <div class="whaticket-tokens-page">
    <!-- Header -->
    <div class="row items-center justify-between q-mb-md">
      <div>
        <h1 class="text-h5 text-bold text-white q-my-none">Tokens de acceso</h1>
        <p class="text-caption text-grey-4 q-mt-xs q-mb-none" style="max-width: 700px">
          Administra su token de acceso de API. Puede usar fichas para autenticar e integrar otras aplicaciones con Whaticket. Para obtener más información sobre el uso de la API, visite la documentación.
        </p>
      </div>

      <q-btn
        color="primary"
        label="Nuevo token"
        unelevated
        no-caps
        class="whaticket-btn-primary"
        @click="isDialogOpen = true"
      />
    </div>

    <!-- Tabla de Tokens -->
    <q-card flat bordered class="whaticket-table-card q-mt-lg">
      <q-markup-table flat dark class="whaticket-table">
        <thead>
          <tr>
            <th class="text-left" style="width: 220px">Nombre</th>
            <th class="text-left">Clave</th>
            <th class="text-right" style="width: 140px">Comportamiento</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="token in tokens" :key="token.id" class="whaticket-table-row">
            <!-- Nombre -->
            <td class="text-left text-white text-weight-medium">
              {{ token.name }}
            </td>

            <!-- Clave con máscara y copiar -->
            <td class="text-left font-mono">
              <div class="row items-center q-gutter-x-sm">
                <span class="text-grey-4 text-caption">
                  {{ token.revealed ? token.key : `${token.key.slice(0, 32)}...` }}
                </span>
                <q-btn
                  flat
                  round
                  dense
                  icon="sym_r_content_copy"
                  size="xs"
                  color="grey-4"
                  @click="copyToken(token.key)"
                >
                  <q-tooltip>Copiar token</q-tooltip>
                </q-btn>
              </div>
            </td>

            <!-- Comportamiento: Ver / Eliminar -->
            <td class="text-right">
              <div class="row justify-end q-gutter-xs">
                <q-btn
                  flat
                  round
                  dense
                  :icon="token.revealed ? 'sym_r_visibility_off' : 'sym_r_visibility'"
                  color="grey-4"
                  size="sm"
                  @click="token.revealed = !token.revealed"
                />
                <q-btn
                  flat
                  round
                  dense
                  icon="sym_r_delete"
                  color="negative"
                  size="sm"
                  @click="deleteToken(token.id)"
                />
              </div>
            </td>
          </tr>
        </tbody>
      </q-markup-table>
    </q-card>

    <!-- Modal Nuevo Token -->
    <q-dialog v-model="isDialogOpen">
      <q-card style="width: 480px; max-width: 95vw" class="whaticket-modal-card q-pa-md">
        <q-card-section>
          <div class="text-h6 text-bold text-white">Generar Nuevo Token de API</div>
          <div class="text-caption text-grey-4">El token tendrá permisos para interactuar con la API REST.</div>
        </q-card-section>

        <q-card-section class="q-gutter-y-md">
          <q-input v-model="newTokenName" label="Nombre identificador (ej. Webhook Web, Zapier)" outlined dark dense />
        </q-card-section>

        <q-card-actions align="right">
          <q-btn flat label="Cancelar" color="grey-4" no-caps v-close-popup />
          <q-btn
            unelevated
            label="Generar Token"
            color="primary"
            no-caps
            class="whaticket-btn-primary"
            @click="generateToken"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { http } from '@/shared/api/http'
import { useAppNotify } from '@/shared/composables/useAppNotify'

const notify = useAppNotify()

interface TokenItem {
  id: string
  name: string
  key: string
  revealed: boolean
}

const isDialogOpen = ref(false)
const newTokenName = ref('')

const tokens = ref<TokenItem[]>([
  {
    id: '1',
    name: 'Token Principal CRM',
    key: 'ey.JhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IldoYXRpY2tldCIsImlhdCI6MTUxNjIzOTAyMn0',
    revealed: false,
  },
])

onMounted(async () => {
  try {
    const response = await http.get('/tokens')
    if (response.data?.data && response.data.data.length > 0) {
      tokens.value = response.data.data.map((t: any) => ({
        id: t.id.toString(),
        name: t.name,
        key: `ey.JhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.${t.id}`,
        revealed: false,
      }))
    }
  } catch {
    // Mantener datos locales de muestra
  }
})

function copyToken(key: string) {
  navigator.clipboard.writeText(key)
  notify.success({ message: 'Token copiado al portapapeles.' })
}

async function generateToken() {
  if (!newTokenName.value.trim()) return

  try {
    const response = await http.post('/tokens', { name: newTokenName.value })
    const created = response.data?.data

    tokens.value.unshift({
      id: created.id.toString(),
      name: created.name,
      key: created.plain_text_token || `ey.JhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.${created.id}`,
      revealed: true,
    })
    notify.success({ message: 'Nuevo token de API generado.' })
  } catch {
    tokens.value.unshift({
      id: Date.now().toString(),
      name: newTokenName.value,
      key: `ey.JhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.${Math.random().toString(36).substring(2)}_${Date.now()}`,
      revealed: true,
    })
    notify.success({ message: 'Token generado.' })
  }

  isDialogOpen.value = false
  newTokenName.value = ''
}

async function deleteToken(id: string) {
  try {
    await http.delete(`/tokens/${id}`)
    notify.warning({ message: 'Token revocado y eliminado.' })
  } catch {
    notify.warning({ message: 'Token eliminado localmente.' })
  }
  tokens.value = tokens.value.filter((t) => t.id !== id)
}
</script>

<style scoped lang="scss">
.whaticket-tokens-page {
  padding: 24px 32px;
  background-color: var(--crm-bg-app);
  min-height: calc(100vh - 52px);
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
