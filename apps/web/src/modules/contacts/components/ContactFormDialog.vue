<template>
  <q-dialog
    :model-value="modelValue"
    persistent
    @update:model-value="emit('update:modelValue', $event)"
  >
    <q-card style="width: 540px; max-width: 95vw" class="contact-dialog-card">
      <q-card-section class="row items-center justify-between q-pb-none">
        <div>
          <div class="text-h6 text-bold text-white">
            {{ isEditing ? 'Editar Contacto' : 'Nuevo Contacto' }}
          </div>
          <div class="text-caption text-grey-4">
            {{ isEditing ? 'Modifica los datos del contacto seleccionado' : 'Registra un nuevo contacto en el workspace' }}
          </div>
        </div>
        <q-btn flat round dense icon="sym_r_close" color="grey-4" v-close-popup />
      </q-card-section>

      <q-card-section class="q-pt-md">
        <q-form class="q-gutter-y-sm" @submit.prevent="handleSubmit">
          <div class="row q-col-gutter-sm">
            <div class="col-12 col-md-6">
              <q-input
                v-model="form.first_name"
                label="Nombre *"
                outlined
                dark
                dense
                :rules="[val => !!val || 'El nombre es obligatorio']"
              />
            </div>
            <div class="col-12 col-md-6">
              <q-input
                v-model="form.last_name"
                label="Apellido"
                outlined
                dark
                dense
              />
            </div>
          </div>

          <div class="row q-col-gutter-sm">
            <div class="col-12 col-md-6">
              <q-input
                v-model="form.phone"
                label="Teléfono / WhatsApp"
                outlined
                dark
                dense
                placeholder="+591 70000000"
              >
                <template #prepend>
                  <q-icon name="sym_r_call" size="16px" color="teal-4" />
                </template>
              </q-input>
            </div>
            <div class="col-12 col-md-6">
              <q-input
                v-model="form.email"
                label="Correo Electrónico"
                outlined
                dark
                dense
                type="email"
              >
                <template #prepend>
                  <q-icon name="sym_r_mail" size="16px" color="teal-4" />
                </template>
              </q-input>
            </div>
          </div>

          <q-select
            v-model="form.status"
            :options="statusOptions"
            emit-value
            map-options
            label="Estado del Contacto"
            outlined
            dark
            dense
          />

          <!-- Selector de Etiquetas con chips dinámicos -->
          <q-select
            v-model="selectedTags"
            :options="availableTagNames"
            use-input
            use-chips
            multiple
            new-value-mode="add-unique"
            label="Etiquetas"
            hint="Escribe y presiona enter para agregar etiquetas personalizadas"
            outlined
            dark
            dense
          >
            <template #prepend>
              <q-icon name="sym_r_label" size="16px" color="teal-4" />
            </template>
          </q-select>

          <q-input
            v-model="form.notes"
            label="Notas Internas"
            type="textarea"
            rows="3"
            outlined
            dark
            dense
          />

          <div class="row justify-end q-gutter-x-sm q-mt-md">
            <q-btn flat label="Cancelar" color="grey-4" no-caps v-close-popup />
            <q-btn
              type="submit"
              color="primary"
              :label="isEditing ? 'Guardar Cambios' : 'Crear Contacto'"
              unelevated
              no-caps
              class="contact-dialog-btn"
              :loading="loading"
            />
          </div>
        </q-form>
      </q-card-section>
    </q-card>
  </q-dialog>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import type { Contact, ContactPayload, ContactTag } from '../types/contact.types'

const props = withDefaults(
  defineProps<{
    modelValue: boolean
    contact?: Contact | null
    loading?: boolean
    availableTags?: ContactTag[]
  }>(),
  {
    contact: null,
    loading: false,
    availableTags: () => [],
  },
)

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  submit: [payload: ContactPayload]
}>()

const isEditing = computed(() => Boolean(props.contact?.id))

const statusOptions = [
  { label: 'Activo (Cliente)', value: 'active' },
  { label: 'Lead (Prospecto Comercial)', value: 'lead' },
  { label: 'Inactivo', value: 'inactive' },
]

const form = reactive({
  first_name: '',
  last_name: '',
  email: '',
  phone: '',
  status: 'active' as 'active' | 'lead' | 'inactive',
  notes: '',
})

const selectedTags = ref<string[]>([])

const availableTagNames = computed(() => {
  return props.availableTags.map((t) => t.name)
})

watch(
  () => props.contact,
  (c) => {
    if (c) {
      form.first_name = c.first_name || (c.name ? c.name.split(' ')[0] : '')
      form.last_name = c.last_name || (c.name ? c.name.split(' ').slice(1).join(' ') : '')
      form.email = c.email || ''
      form.phone = c.phone || ''
      form.status = c.status || 'active'
      form.notes = c.notes || ''
      selectedTags.value = c.tags ? c.tags.map((t) => (typeof t === 'string' ? t : t.name)) : []
    } else {
      form.first_name = ''
      form.last_name = ''
      form.email = ''
      form.phone = ''
      form.status = 'active'
      form.notes = ''
      selectedTags.value = []
    }
  },
  { immediate: true },
)

function handleSubmit() {
  if (!form.first_name.trim()) return

  const payload: ContactPayload = {
    first_name: form.first_name.trim(),
    last_name: form.last_name.trim(),
    email: form.email.trim(),
    phone: form.phone.trim(),
    status: form.status,
    notes: form.notes.trim(),
    tags: selectedTags.value,
  }

  emit('submit', payload)
}
</script>

<style scoped lang="scss">
.contact-dialog-card {
  background: var(--crm-bg-card, #111827);
  border: 1px solid var(--crm-color-border, rgba(255, 255, 255, 0.08));
  border-radius: 14px;
}

.contact-dialog-btn {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%) !important;
  font-weight: 600;
  border-radius: 8px;
}
</style>
