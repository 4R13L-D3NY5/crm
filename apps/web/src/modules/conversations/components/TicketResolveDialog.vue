<template>
  <q-dialog :model-value="modelValue" persistent @update:model-value="$emit('update:modelValue', $event)">
    <q-card style="width: 480px; max-width: 95vw" class="bg-dark text-white rounded-borders">
      <q-card-section class="row items-center q-pb-none">
        <div class="row items-center q-gutter-x-sm">
          <q-avatar icon="sym_r_done_all" color="negative" text-color="white" size="36px" font-size="20px" />
          <div>
            <div class="text-subtitle1 text-bold">Resolver y Finalizar Ticket</div>
            <div class="text-caption text-grey-4">Confirma la finalización de la atención</div>
          </div>
        </div>
        <q-space />
        <q-btn icon="sym_r_close" flat round dense v-close-popup />
      </q-card-section>

      <q-card-section class="q-pt-md q-gutter-y-md">
        <!-- Banner de Encuesta CSAT WhatsApp -->
        <div v-if="channel === 'whatsapp'" class="bg-dark-soft q-pa-md rounded-borders border-grey">
          <div class="row items-start no-wrap q-gutter-x-sm">
            <q-icon name="sym_r_stars" color="amber-4" size="22px" class="q-mt-xs" />
            <div class="column">
              <span class="text-weight-bold text-white text-body2">Encuesta de Satisfacción (CSAT)</span>
              <span class="text-caption text-grey-4">
                Se enviará un mensaje por WhatsApp invitando al cliente a calificar la atención de 1 a 5 estrellas.
              </span>
            </div>
          </div>

          <div class="q-mt-sm">
            <q-checkbox
              v-model="sendSurvey"
              label="Enviar encuesta automática por WhatsApp"
              color="positive"
              dense
              class="text-caption text-grey-2"
            />
          </div>
        </div>

        <!-- Calificación manual opcional -->
        <div class="bg-dark-soft q-pa-md rounded-borders">
          <div class="text-caption text-weight-medium text-grey-3 q-mb-xs">
            Calificación interna (opcional):
          </div>
          <div class="row items-center q-gutter-x-md">
            <q-rating
              v-model="manualRating"
              size="26px"
              color="amber-4"
              icon="sym_r_star"
              icon-selected="sym_r_star"
              no-dimming
            />
            <span v-if="manualRating > 0" class="text-caption text-amber-4 text-bold">
              {{ ratingLabel(manualRating) }}
            </span>
            <q-btn
              v-if="manualRating > 0"
              flat
              dense
              round
              size="xs"
              icon="sym_r_close"
              color="grey-5"
              @click="manualRating = 0"
            >
              <q-tooltip>Limpiar calificación manual</q-tooltip>
            </q-btn>
          </div>

          <q-input
            v-model="feedbackText"
            type="textarea"
            rows="2"
            outlined
            dense
            dark
            color="primary"
            placeholder="Observaciones de cierre o retroalimentación (opcional)..."
            class="q-mt-sm"
          />
        </div>
      </q-card-section>

      <q-card-actions align="right" class="q-pa-md bg-dark-soft">
        <q-btn flat label="Cancelar" color="grey-4" v-close-popup :disable="loading" />
        <q-btn
          unelevated
          color="negative"
          icon="sym_r_check_circle"
          label="Resolver Ticket"
          :loading="loading"
          @click="handleSubmit"
        />
      </q-card-actions>
    </q-card>
  </q-dialog>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'

const props = withDefaults(
  defineProps<{
    modelValue: boolean
    loading?: boolean
    channel?: string
  }>(),
  {
    loading: false,
    channel: 'whatsapp',
  }
)

const emit = defineEmits<{
  (e: 'update:modelValue', value: boolean): void
  (
    e: 'submit',
    payload: {
      send_survey: boolean
      rating?: number | null
      feedback?: string | null
    }
  ): void
}>()

const sendSurvey = ref(true)
const manualRating = ref(0)
const feedbackText = ref('')

watch(
  () => props.modelValue,
  (val) => {
    if (val) {
      sendSurvey.value = true
      manualRating.value = 0
      feedbackText.value = ''
    }
  }
)

function ratingLabel(r: number): string {
  switch (r) {
    case 5:
      return '5/5 - Excelente'
    case 4:
      return '4/5 - Muy Bueno'
    case 3:
      return '3/5 - Regular'
    case 2:
      return '2/5 - Malo'
    case 1:
      return '1/5 - Deficiente'
    default:
      return ''
  }
}

function handleSubmit() {
  emit('submit', {
    send_survey: props.channel === 'whatsapp' ? sendSurvey.value : false,
    rating: manualRating.value > 0 ? manualRating.value : null,
    feedback: feedbackText.value.trim() || null,
  })
}
</script>

<style scoped>
.border-grey {
  border: 1px solid rgba(255, 255, 255, 0.08);
}
</style>
