<template>
  <q-dialog
    :model-value="modelValue"
    persistent
    @update:model-value="emit('update:modelValue', $event)"
  >
    <q-card style="width: 650px; max-width: 95vw" class="contact-import-card">
      <q-card-section class="row items-center justify-between q-pb-none">
        <div>
          <div class="text-h6 text-bold text-white">Importación Masiva de Contactos</div>
          <div class="text-caption text-grey-4">
            Pega datos desde Excel/CSV en formato: <code>Nombre, Teléfono, Correo, Etiquetas</code>
          </div>
        </div>
        <q-btn flat round dense icon="sym_r_close" color="grey-4" v-close-popup />
      </q-card-section>

      <q-card-section class="q-gutter-y-md q-pt-md">
        <!-- Input Textarea -->
        <q-input
          v-model="rawText"
          type="textarea"
          rows="5"
          outlined
          dark
          placeholder="Juan Perez, +59170000001, juan@ejemplo.com, Clientes&#10;Maria Lopez, +59170000002, maria@ejemplo.com, Admisiones"
          class="contact-import-card__input font-mono"
        />

        <!-- Tabla de Vista Previa -->
        <div v-if="parsedContacts.length > 0">
          <div class="row items-center justify-between q-mb-xs">
            <span class="text-caption text-bold text-teal-4">
              <q-icon name="sym_r_visibility" size="14px" class="q-mr-xs" />
              Vista Previa ({{ parsedContacts.length }} registros detectados)
            </span>
            <span class="text-caption text-grey-5">
              Válidos: {{ validCount }} / {{ parsedContacts.length }}
            </span>
          </div>

          <q-markup-table dense flat dark class="contact-import-card__preview">
            <thead>
              <tr>
                <th class="text-left">Nombre</th>
                <th class="text-left">Teléfono</th>
                <th class="text-left">Correo</th>
                <th class="text-left">Tags</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(item, idx) in parsedContacts.slice(0, 5)" :key="idx">
                <td class="text-left">{{ item.name }}</td>
                <td class="text-left font-mono">
                  <span :class="item.phone ? 'text-positive' : 'text-negative'">
                    {{ item.phone || 'Falta teléfono' }}
                  </span>
                </td>
                <td class="text-left text-caption text-grey-4">{{ item.email || '-' }}</td>
                <td class="text-left text-caption">
                  <q-badge v-for="t in item.tags" :key="t" color="primary" class="q-mr-xs">
                    {{ t }}
                  </q-badge>
                </td>
              </tr>
              <tr v-if="parsedContacts.length > 5">
                <td colspan="4" class="text-center text-caption text-grey-5">
                  ... y {{ parsedContacts.length - 5 }} contacto(s) más.
                </td>
              </tr>
            </tbody>
          </q-markup-table>
        </div>
      </q-card-section>

      <q-card-actions align="right" class="q-pa-md">
        <q-btn flat label="Cancelar" color="grey-4" no-caps v-close-popup />
        <q-btn
          unelevated
          label="Comenzar Importación"
          icon="sym_r_upload"
          color="primary"
          no-caps
          class="contact-import-card__btn"
          :disable="validCount === 0"
          :loading="loading"
          @click="handleImport"
        />
      </q-card-actions>
    </q-card>
  </q-dialog>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import type { ImportContactItem } from '../types/contact.types'

defineProps<{
  modelValue: boolean
  loading?: boolean
}>()

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  import: [contacts: ImportContactItem[]]
}>()

const rawText = ref('')

const parsedContacts = computed<ImportContactItem[]>(() => {
  if (!rawText.value.trim()) return []

  const lines = rawText.value.split('\n').map((l) => l.trim()).filter((l) => l.length > 0)
  return lines.map((line) => {
    // Split by comma or tab
    const delimiter = line.includes('\t') ? '\t' : ','
    const parts = line.split(delimiter).map((p) => p.trim())
    const tags = parts[3] ? parts[3].split(';').map((t) => t.trim()).filter(Boolean) : []

    return {
      name: parts[0] || 'Contacto',
      phone: parts[1] || '',
      email: parts[2] || undefined,
      tags: tags.length > 0 ? tags : undefined,
    }
  })
})

const validCount = computed(() => {
  return parsedContacts.value.filter((c) => c.phone && c.phone.length >= 6).length
})

function handleImport() {
  const validContacts = parsedContacts.value.filter((c) => c.phone && c.phone.length >= 6)
  if (validContacts.length === 0) return

  emit('import', validContacts)
}
</script>

<style scoped lang="scss">
.contact-import-card {
  background: var(--crm-bg-card, #111827);
  border: 1px solid var(--crm-color-border, rgba(255, 255, 255, 0.08));
  border-radius: 14px;
}

.contact-import-card__preview {
  background: rgba(0, 0, 0, 0.2);
  border-radius: 8px;
  max-height: 200px;
  overflow-y: auto;
}

.contact-import-card__btn {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%) !important;
  font-weight: 600;
  border-radius: 8px;
}
</style>
