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
            v-model="form.custom_status_id"
            :options="customStatusOptions"
            emit-value
            map-options
            label="Estado Oficial del Lead"
            outlined
            dark
            dense
            clearable
          >
            <template #prepend>
              <q-icon name="sym_r_flag" size="16px" color="teal-4" />
            </template>
            <template #selected-item="scope">
              <div v-if="scope.opt?.value" class="row items-center no-wrap ellipsis text-caption">
                <span
                  class="status-form-dot q-mr-xs"
                  :style="{ backgroundColor: scope.opt.color || '#10b981' }"
                ></span>
                <span class="text-white">{{ scope.opt.label }}</span>
              </div>
            </template>
            <template #option="scope">
              <q-item v-bind="scope.itemProps" dense dark>
                <q-item-section avatar style="min-width: 24px">
                  <span
                    class="status-form-dot"
                    :style="{ backgroundColor: scope.opt.color || '#10b981' }"
                  ></span>
                </q-item-section>
                <q-item-section>
                  <q-item-label class="text-white">{{ scope.opt.label }}</q-item-label>
                </q-item-section>
              </q-item>
            </template>
          </q-select>

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
import { useQuery } from '@tanstack/vue-query'
import { http } from '@/shared/api/http'
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

// Cargar Estados Oficiales de la Organización
const { data: customStatusesQuery } = useQuery({
  queryKey: ['custom-statuses'],
  queryFn: async () => {
    const res = await http.get('/custom-statuses')
    return res.data.data || []
  },
})

const customStatusOptions = computed(() => {
  const list = (customStatusesQuery.value as any[]) || []
  return list.map((s: any) => ({
    label: s.name,
    value: s.id,
    color: s.color,
    icon: s.icon || 'sym_r_flag',
    is_default: s.is_default,
  }))
})

const form = reactive({
  first_name: '',
  last_name: '',
  email: '',
  phone: '',
  status: 'active' as 'active' | 'lead' | 'inactive',
  custom_status_id: null as string | null,
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
      form.custom_status_id = c.custom_status_id || c.custom_status?.id || null
      form.notes = c.notes || ''
      selectedTags.value = c.tags ? c.tags.map((t) => (typeof t === 'string' ? t : t.name)) : []
    } else {
      form.first_name = ''
      form.last_name = ''
      form.email = ''
      form.phone = ''
      form.status = 'active'
      const defaultStatus = customStatusOptions.value.find((s: any) => s.is_default)
      form.custom_status_id = defaultStatus?.value || null
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
    custom_status_id: form.custom_status_id || undefined,
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

.status-form-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  display: inline-block;
  flex-shrink: 0;
}

.contact-dialog-btn {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%) !important;
  font-weight: 600;
  border-radius: 8px;
}
</style>
