<template>
  <q-dialog
    :model-value="modelValue"
    persistent
    @update:model-value="emit('update:modelValue', $event)"
  >
    <q-card style="width: 720px; max-width: 95vw; max-height: 90vh;" class="contact-import-card column no-wrap">
      <!-- Header -->
      <q-card-section class="row items-center justify-between q-pb-none col-auto">
        <div class="row items-center q-gutter-x-sm">
          <q-avatar size="38px" color="teal-9" text-color="teal-2" icon="sym_r_contacts" />
          <div>
            <div class="text-h6 text-bold text-white">Importar Contactos</div>
            <div class="text-caption text-grey-4">
              Compatible con Google Contacts (Gmail), vCard (.vcf), Excel y CSV
            </div>
          </div>
        </div>
        <q-btn flat round dense icon="sym_r_close" color="grey-4" v-close-popup />
      </q-card-section>

      <!-- Pestañas de Modo -->
      <q-card-section class="q-py-xs col-auto">
        <q-tabs
          v-model="importMode"
          dense
          align="left"
          active-color="primary"
          indicator-color="primary"
          class="text-grey-4"
        >
          <q-tab name="file" icon="sym_r_upload_file" label="Subir Archivo (Google / vCard)" no-caps />
          <q-tab name="manual" icon="sym_r_edit_note" label="Pegar Texto (Excel / Manual)" no-caps />
        </q-tabs>
      </q-card-section>

      <q-card-section class="q-gutter-y-sm q-pt-none col scroll">
        <!-- Banner de Ayuda Google Contacts -->
        <q-banner rounded dense class="bg-blue-grey-10 text-grey-3 q-my-xs">
          <template #avatar>
            <q-icon name="sym_r_lightbulb" color="amber-4" size="22px" />
          </template>
          <div class="text-caption">
            <span class="text-bold text-white">¿Cómo exportar desde Gmail?</span>
            Ingresa a
            <a href="https://contacts.google.com" target="_blank" class="text-cyan-3 text-bold" style="text-decoration: underline;">
              contacts.google.com
            </a>
            ➜ Clic en <strong>Exportar</strong> ➜ Selecciona <strong>CSV de Google</strong> o <strong>vCard</strong> y sube el archivo aquí.
          </div>
        </q-banner>

        <!-- MODO 1: Archivo -->
        <div v-if="importMode === 'file'" class="q-gutter-y-sm">
          <q-file
            v-model="uploadedFile"
            outlined
            dark
            dense
            clearable
            accept=".csv, .vcf, .txt"
            label="Seleccionar o arrastrar archivo de contactos (.csv, .vcf)"
            class="contact-import-card__file-input"
            @update:model-value="onFileSelected"
          >
            <template #prepend>
              <q-icon name="sym_r_attach_file" color="cyan-4" />
            </template>
          </q-file>

          <div
            v-if="!uploadedFile && !rawText"
            class="contact-import-dropzone q-pa-md text-center text-grey-5 cursor-pointer"
            @click="triggerFileInput"
            @dragover.prevent
            @drop.prevent="onFileDrop"
          >
            <q-icon name="sym_r_cloud_upload" size="36px" color="cyan-4" class="q-mb-xs" />
            <div class="text-caption text-bold text-grey-3">
              Haz clic o arrastra aquí tu archivo .csv o .vcf de Google
            </div>
            <div class="text-caption text-grey-5" style="font-size: 11px;">
              Detecta automáticamente columnas de Nombre, Teléfono, Correo y Etiquetas de Google Contacts
            </div>
          </div>
        </div>

        <!-- MODO 2: Manual -->
        <div v-else class="q-gutter-y-xs">
          <div class="text-caption text-grey-4">
            Pega datos separados por comas o tabulaciones: <code>Nombre, Teléfono, Correo, Etiquetas</code>
          </div>
          <q-input
            v-model="rawText"
            type="textarea"
            rows="5"
            outlined
            dark
            placeholder="Juan Perez, +59170000001, juan@ejemplo.com, Clientes&#10;Maria Lopez, +59170000002, maria@ejemplo.com, Admisiones"
            class="contact-import-card__input font-mono"
          />
        </div>

        <!-- Tabla de Vista Previa -->
        <div v-if="parsedContacts.length > 0" class="q-mt-sm">
          <div class="row items-center justify-between q-mb-xs">
            <span class="text-caption text-bold text-teal-4">
              <q-icon name="sym_r_visibility" size="14px" class="q-mr-xs" />
              Vista Previa ({{ parsedContacts.length }} registros detectados)
            </span>
            <span class="text-caption" :class="validCount > 0 ? 'text-positive' : 'text-negative'">
              Válidos con teléfono: {{ validCount }} / {{ parsedContacts.length }}
            </span>
          </div>

          <q-markup-table dense flat dark class="contact-import-card__preview">
            <thead>
              <tr>
                <th class="text-left">Nombre</th>
                <th class="text-left">Teléfono</th>
                <th class="text-left">Correo</th>
                <th class="text-left">Etiquetas</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(item, idx) in parsedContacts.slice(0, 5)" :key="idx">
                <td class="text-left text-bold text-white">{{ item.name }}</td>
                <td class="text-left font-mono">
                  <span :class="item.phone ? 'text-positive' : 'text-negative'">
                    {{ item.phone || 'Sin teléfono' }}
                  </span>
                </td>
                <td class="text-left text-caption text-grey-4">{{ item.email || '-' }}</td>
                <td class="text-left text-caption">
                  <template v-if="item.tags && item.tags.length">
                    <q-badge v-for="t in item.tags" :key="t" color="primary" class="q-mr-xs">
                      {{ t }}
                    </q-badge>
                  </template>
                  <span v-else class="text-grey-6">-</span>
                </td>
              </tr>
              <tr v-if="parsedContacts.length > 5">
                <td colspan="4" class="text-center text-caption text-grey-5">
                  ... y {{ parsedContacts.length - 5 }} contacto(s) más listos para importar.
                </td>
              </tr>
            </tbody>
          </q-markup-table>
        </div>
      </q-card-section>

      <!-- Footer / Acciones -->
      <q-card-actions align="right" class="q-pa-md col-auto">
        <q-btn flat label="Cancelar" color="grey-4" no-caps v-close-popup />
        <q-btn
          unelevated
          :label="`Importar ${validCount} Contacto(s)`"
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

const importMode = ref<'file' | 'manual'>('file')
const rawText = ref('')
const uploadedFile = ref<File | null>(null)

// Disparador de input de archivo oculto
function triggerFileInput() {
  const fileInput = document.querySelector('.contact-import-card__file-input input[type="file"]') as HTMLInputElement
  if (fileInput) fileInput.click()
}

// Manejar Drag & Drop
function onFileDrop(e: DragEvent) {
  const file = e.dataTransfer?.files[0]
  if (file) {
    uploadedFile.value = file
    onFileSelected(file)
  }
}

// Leer archivo
function onFileSelected(file: File | null) {
  if (!file) {
    rawText.value = ''
    return
  }

  const reader = new FileReader()
  reader.onload = (e) => {
    rawText.value = (e.target?.result as string) || ''
  }
  reader.readAsText(file)
}

// Limpiador de teléfonos (conserva signo + y dígitos, elimina espacios/guiones)
function sanitizePhone(raw: string): string {
  if (!raw) return ''
  let cleaned = raw.trim().replace(/[\s\(\)\-\.]/g, '')
  if (cleaned.startsWith('00')) {
    cleaned = '+' + cleaned.substring(2)
  }
  return cleaned
}

// Parser CSV respetando comillas
function parseCsvLine(line: string, delimiter: string): string[] {
  const result: string[] = []
  let current = ''
  let inQuotes = false

  for (let i = 0; i < line.length; i++) {
    const char = line[i]
    if (char === '"') {
      if (inQuotes && line[i + 1] === '"') {
        current += '"'
        i++
      } else {
        inQuotes = !inQuotes
      }
    } else if (char === delimiter && !inQuotes) {
      result.push(current.trim())
      current = ''
    } else {
      current += char
    }
  }
  result.push(current.trim())
  return result
}

// Parser de formato vCard (.vcf)
function parseVCard(text: string): ImportContactItem[] {
  const cards = text.split(/BEGIN:VCARD/i).filter((c) => c.trim().length > 0)
  const list: ImportContactItem[] = []

  for (const card of cards) {
    let name = ''
    let phone = ''
    let email: string | undefined = undefined
    const tags: string[] = []

    const lines = card.split(/\r?\n/)
    for (const rawLine of lines) {
      const line = rawLine.trim()
      if (line.startsWith('FN:')) {
        name = line.substring(3).trim()
      } else if (!name && line.startsWith('N:')) {
        const parts = line.substring(2).split(';').map((p) => p.trim()).filter(Boolean)
        name = parts.reverse().join(' ')
      } else if (/^TEL[;:].+/i.test(line)) {
        const colonIdx = line.indexOf(':')
        if (colonIdx !== -1 && !phone) {
          phone = sanitizePhone(line.substring(colonIdx + 1))
        }
      } else if (/^EMAIL[;:].+/i.test(line)) {
        const colonIdx = line.indexOf(':')
        if (colonIdx !== -1 && !email) {
          const val = line.substring(colonIdx + 1).trim()
          if (val.includes('@')) email = val
        }
      } else if (/^CATEGORIES[;:].+/i.test(line)) {
        const colonIdx = line.indexOf(':')
        if (colonIdx !== -1) {
          const catStr = line.substring(colonIdx + 1)
          catStr.split(',').forEach((c) => {
            const clean = c.trim()
            if (clean) tags.push(clean)
          })
        }
      }
    }

    if (name || phone) {
      list.push({
        name: name || phone || 'Contacto vCard',
        phone,
        email,
        tags: tags.length > 0 ? tags : undefined,
      })
    }
  }

  return list
}

// Parser inteligente general
const parsedContacts = computed<ImportContactItem[]>(() => {
  const content = rawText.value.trim()
  if (!content) return []

  // Si es formato vCard
  if (/BEGIN:VCARD/i.test(content)) {
    return parseVCard(content)
  }

  const lines = content.split(/\r?\n/).map((l) => l.trim()).filter((l) => l.length > 0)
  if (lines.length === 0) return []

  // Detectar delimitador
  const firstLine = lines[0]
  let delimiter = ','
  if (firstLine.includes('\t')) {
    delimiter = '\t'
  } else if ((firstLine.match(/;/g) || []).length > (firstLine.match(/,/g) || []).length) {
    delimiter = ';'
  }

  const headerCells = parseCsvLine(firstLine, delimiter).map((h) => h.toLowerCase().trim())

  // Detectar si la primera fila es una cabecera conocida
  const isHeaderRow = headerCells.some((h) =>
    ['name', 'nombre', 'given name', 'first name', 'phone', 'teléfono', 'telefono', 'email', 'correo', 'group membership'].some((key) => h.includes(key))
  )

  if (isHeaderRow) {
    let nameIdx = headerCells.findIndex((h) => h === 'name' || h === 'nombre' || h === 'full name' || h === 'nombre completo')
    const givenNameIdx = headerCells.findIndex((h) => h.includes('given name') || h.includes('first name') || h.includes('nombre de pila'))
    const familyNameIdx = headerCells.findIndex((h) => h.includes('family name') || h.includes('last name') || h.includes('apellidos') || h.includes('apellido'))
    
    // Buscar teléfono (priorizar columnas de Google / Outlook como "Phone 1 - Value")
    let phoneIdx = headerCells.findIndex((h) => h.includes('phone 1 - value') || h.includes('teléfono 1 - valor') || h.includes('telefono 1 - valor'))
    if (phoneIdx === -1) {
      phoneIdx = headerCells.findIndex((h) => h.includes('mobile') || h.includes('celular') || h.includes('móvil') || h.includes('movil'))
    }
    if (phoneIdx === -1) {
      phoneIdx = headerCells.findIndex((h) => h.includes('phone') || h.includes('teléfono') || h.includes('telefono'))
    }

    // Buscar correo (priorizar "E-mail 1 - Value")
    let emailIdx = headerCells.findIndex((h) => h.includes('e-mail 1 - value') || h.includes('correo electrónico 1 - valor') || h.includes('correo electronico 1 - valor'))
    if (emailIdx === -1) {
      emailIdx = headerCells.findIndex((h) => h.includes('email') || h.includes('correo') || h.includes('e-mail address'))
    }

    // Buscar etiquetas / grupos de Google Contacts ("Group Membership")
    const tagsIdx = headerCells.findIndex((h) =>
      h.includes('group membership') || h.includes('etiqueta') || h.includes('tags') || h.includes('etiquetas') || h.includes('categoría') || h.includes('labels')
    )

    const dataLines = lines.slice(1)
    const items: ImportContactItem[] = []

    for (const line of dataLines) {
      const cells = parseCsvLine(line, delimiter)
      let name = ''
      if (nameIdx !== -1 && cells[nameIdx]) {
        name = cells[nameIdx]
      } else if (givenNameIdx !== -1) {
        const given = cells[givenNameIdx] || ''
        const family = (familyNameIdx !== -1 ? cells[familyNameIdx] : '') || ''
        name = `${given} ${family}`.trim()
      }

      const phoneRaw = phoneIdx !== -1 ? (cells[phoneIdx] || '') : ''
      const phone = sanitizePhone(phoneRaw)

      let email: string | undefined = undefined
      if (emailIdx !== -1 && cells[emailIdx] && cells[emailIdx].includes('@')) {
        email = cells[emailIdx].trim()
      }

      const tags: string[] = []
      if (tagsIdx !== -1 && cells[tagsIdx]) {
        // En Google Contacts el formato es "* myContacts ::: Grupo1 ::: Grupo2"
        const rawTagParts = cells[tagsIdx].split(/:::|;|,/).map((t) => t.trim())
        for (const part of rawTagParts) {
          if (!part || part.startsWith('*')) continue // Descartar * myContacts, * starred
          tags.push(part)
        }
      }

      if (name || phone) {
        items.push({
          name: name || phone || 'Contacto Importado',
          phone,
          email,
          tags: tags.length > 0 ? tags : undefined,
        })
      }
    }

    return items
  }

  // Si no tiene cabeceras reconocidas, usar split posicional clásico (Nombre, Teléfono, Correo, Etiquetas)
  return lines.map((line) => {
    const parts = parseCsvLine(line, delimiter)
    const tags = parts[3] ? parts[3].split(';').map((t) => t.trim()).filter(Boolean) : []
    const phone = sanitizePhone(parts[1] || '')
    const emailCandidate = parts[2] ? parts[2].trim() : undefined
    const email = (emailCandidate && emailCandidate.includes('@')) ? emailCandidate : undefined

    return {
      name: parts[0] || 'Contacto',
      phone,
      email,
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
  display: flex;
  flex-direction: column;
  max-height: 90vh;
}

.contact-import-dropzone {
  border: 2px dashed rgba(20, 184, 166, 0.35);
  border-radius: 10px;
  background: rgba(20, 184, 166, 0.04);
  transition: all 0.2s ease;

  &:hover {
    border-color: #14b8a6;
    background: rgba(20, 184, 166, 0.08);
  }
}

.contact-import-card__preview {
  background: rgba(0, 0, 0, 0.25);
  border-radius: 8px;
  max-height: 220px;
  overflow-y: auto;
}

.contact-import-card__btn {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%) !important;
  font-weight: 600;
  border-radius: 8px;
}
</style>
