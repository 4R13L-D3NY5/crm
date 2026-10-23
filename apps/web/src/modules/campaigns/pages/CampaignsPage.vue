<template>
  <div class="xf-campaigns-page">
    <!-- Header Principal -->
    <div class="row items-center justify-between q-mb-lg">
      <div>
        <div class="row items-center q-gutter-x-sm">
          <q-avatar size="38px" color="primary" text-color="white">
            <q-icon name="sym_r_campaign" size="24px" />
          </q-avatar>
          <div>
            <h1 class="text-h5 text-bold text-ink q-my-none">Campañas Masivas de Difusión</h1>
            <p class="text-caption text-muted q-mt-xs q-mb-none">
              Comunícate masivamente con tus segmentos de clientes con algoritmo anti-bloqueo y segmentación por categorías y etiquetas.
            </p>
          </div>
        </div>
      </div>

      <div class="row items-center q-gutter-sm">
        <q-btn
          color="primary"
          icon="sym_r_add"
          label="Nueva Campaña"
          unelevated
          no-caps
          class="text-weight-bold"
          @click="openCreateDialog"
        />
      </div>
    </div>

    <!-- KPIs de Campañas -->
    <div class="row q-col-gutter-md q-mb-lg">
      <div class="col-12 col-sm-6 col-md-3">
        <q-card flat class="xf-kpi-card">
          <q-card-section class="row items-center justify-between no-wrap">
            <div>
              <div class="text-caption text-muted text-uppercase text-weight-bold letter-spacing-wide">Total Campañas</div>
              <div class="text-h5 text-bold text-ink q-mt-xs">{{ totalCampaignsCount }}</div>
            </div>
            <q-avatar size="40px" color="primary" text-color="white" class="xf-kpi-icon-bg">
              <q-icon name="sym_r_campaign" size="22px" />
            </q-avatar>
          </q-card-section>
        </q-card>
      </div>

      <div class="col-12 col-sm-6 col-md-3">
        <q-card flat class="xf-kpi-card">
          <q-card-section class="row items-center justify-between no-wrap">
            <div>
              <div class="text-caption text-muted text-uppercase text-weight-bold letter-spacing-wide">En Proceso</div>
              <div class="text-h5 text-bold text-amber-5 q-mt-xs">{{ processingCount }}</div>
            </div>
            <q-avatar size="40px" color="amber-9" text-color="amber-2" class="xf-kpi-icon-bg">
              <q-icon name="sym_r_sync" size="22px" />
            </q-avatar>
          </q-card-section>
        </q-card>
      </div>

      <div class="col-12 col-sm-6 col-md-3">
        <q-card flat class="xf-kpi-card">
          <q-card-section class="row items-center justify-between no-wrap">
            <div>
              <div class="text-caption text-muted text-uppercase text-weight-bold letter-spacing-wide">Mensajes Enviados</div>
              <div class="text-h5 text-bold text-positive q-mt-xs">{{ totalSentMessages }}</div>
            </div>
            <q-avatar size="40px" color="positive" text-color="white" class="xf-kpi-icon-bg">
              <q-icon name="sym_r_done_all" size="22px" />
            </q-avatar>
          </q-card-section>
        </q-card>
      </div>

      <div class="col-12 col-sm-6 col-md-3">
        <q-card flat class="xf-kpi-card">
          <q-card-section class="row items-center justify-between no-wrap">
            <div>
              <div class="text-caption text-muted text-uppercase text-weight-bold letter-spacing-wide">Destinatarios Totales</div>
              <div class="text-h5 text-bold text-ink q-mt-xs">{{ totalRecipients }}</div>
            </div>
            <q-avatar size="40px" color="secondary" text-color="white" class="xf-kpi-icon-bg">
              <q-icon name="sym_r_group" size="22px" />
            </q-avatar>
          </q-card-section>
        </q-card>
      </div>
    </div>

    <!-- Barra de Filtros y Búsqueda -->
    <q-card flat class="xf-filter-card q-mb-md">
      <q-card-section class="row items-center justify-between q-col-gutter-sm">
        <div class="col-12 col-md-8 row items-center q-gutter-sm">
          <q-input
            v-model="search"
            dense
            outlined
            placeholder="Buscar por nombre o segmento..."
            class="xf-search-input"
            clearable
          >
            <template #prepend>
              <q-icon name="sym_r_search" size="18px" class="text-muted" />
            </template>
          </q-input>

          <q-select
            v-model="selectedStatus"
            :options="statusOptions"
            emit-value
            map-options
            dense
            outlined
            label="Filtrar por Estado"
            style="min-width: 170px;"
          />
        </div>

        <div class="col-12 col-md-4 row items-center justify-end">
          <span class="text-caption text-muted">
            Mostrando <strong>{{ filteredCampaigns.length }}</strong> de <strong>{{ campaigns.length }}</strong> campañas
          </span>
        </div>
      </q-card-section>
    </q-card>

    <!-- Estado Vacío -->
    <q-card v-if="filteredCampaigns.length === 0" flat class="xf-empty-card q-pa-xl text-center">
      <div class="xf-empty-icon-circle q-mx-auto q-mb-md">
        <q-icon name="sym_r_campaign" size="44px" color="primary" />
      </div>
      <div class="text-h6 text-bold text-ink">No se encontraron campañas</div>
      <div class="text-caption text-muted q-mt-xs q-mb-lg" style="max-width: 440px; margin-inline: auto;">
        Crea una campaña masiva para comunicar promociones, recordatorios o convocatorias a tus contactos segmentando por categorías o etiquetas.
      </div>
      <q-btn
        color="primary"
        icon="sym_r_add"
        label="Crear Primera Campaña"
        unelevated
        no-caps
        class="text-weight-bold"
        @click="openCreateDialog"
      />
    </q-card>

    <!-- Tabla de Campañas -->
    <q-card v-else flat class="xf-table-card">
      <q-markup-table flat class="xf-campaigns-table">
        <thead>
          <tr>
            <th class="text-left">CAMPAÑA Y SEGMENTO</th>
            <th class="text-left">CANAL REMITENTE</th>
            <th class="text-left" style="width: 220px">PROGRESO DE ENVÍO</th>
            <th class="text-left">INTERVALO ANTI-BAN</th>
            <th class="text-left">ESTADO</th>
            <th class="text-right">ACCIONES</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="c in filteredCampaigns" :key="c.id" class="xf-table-row">
            <!-- Nombre, Fecha y Segmento -->
            <td class="text-left">
              <div class="text-weight-bold text-ink">{{ c.name }}</div>
              <div class="row items-center q-gutter-x-xs q-mt-xs">
                <span class="text-caption text-muted">{{ c.created_at }}</span>
                <span class="text-muted">•</span>
                <q-badge
                  :color="c.target_type === 'categories' ? 'cyan-9' : c.target_type === 'tags' ? 'purple-9' : c.target_type === 'mixed' ? 'deep-purple-9' : 'blue-grey-8'"
                  :text-color="c.target_type === 'categories' ? 'cyan-2' : c.target_type === 'tags' ? 'purple-2' : c.target_type === 'mixed' ? 'deep-purple-2' : 'blue-grey-2'"
                  size="sm"
                  class="text-weight-medium q-px-xs"
                >
                  <q-icon
                    :name="c.target_type === 'categories' ? 'sym_r_category' : c.target_type === 'tags' ? 'sym_r_sell' : c.target_type === 'mixed' ? 'sym_r_tune' : 'sym_r_public'"
                    size="12px"
                    class="q-mr-xs"
                  />
                  {{ c.target_summary || (c.target_type === 'categories' ? 'Categorías' : c.target_type === 'tags' ? 'Etiquetas' : 'Toda la base') }}
                </q-badge>
              </div>
            </td>

            <!-- Conexión -->
            <td class="text-left">
              <div class="row items-center q-gutter-x-xs">
                <q-icon name="sym_r_phone" size="16px" color="positive" />
                <span class="text-ink text-weight-medium">{{ c.connection }}</span>
              </div>
            </td>

            <!-- Progreso -->
            <td class="text-left">
              <div class="row items-center justify-between q-mb-xs">
                <span class="text-caption text-weight-bold text-ink">{{ c.progress }}%</span>
                <span class="text-caption text-muted">{{ c.sent_count }} / {{ c.total_contacts }}</span>
              </div>
              <q-linear-progress
                :value="c.progress / 100"
                rounded
                size="6px"
                :color="c.status === 'completed' ? 'positive' : c.status === 'paused' ? 'amber-8' : 'primary'"
                class="xf-progress-bar"
              />
              <div v-if="c.failed_count > 0" class="text-caption text-negative q-mt-xs" style="font-size: 11px;">
                {{ c.failed_count }} fallidos
              </div>
            </td>

            <!-- Delay -->
            <td class="text-left">
              <q-badge outline color="primary" class="text-weight-medium">
                {{ c.delay_seconds }}s intervalo
              </q-badge>
            </td>

            <!-- Estado -->
            <td class="text-left">
              <q-badge
                :color="getStatusBadgeColor(c.status)"
                :label="getStatusBadgeLabel(c.status)"
                class="text-bold q-px-sm q-py-xs"
                rounded
              />
            </td>

            <!-- Acciones -->
            <td class="text-right">
              <div class="row items-center justify-end q-gutter-xs">
                <!-- Iniciar (si está en borrador) -->
                <q-btn
                  v-if="c.status === 'draft'"
                  flat
                  round
                  dense
                  icon="sym_r_play_arrow"
                  color="positive"
                  @click="startCampaign(c.id)"
                >
                  <q-tooltip>Iniciar disparo</q-tooltip>
                </q-btn>

                <!-- Pausar (si está enviando) -->
                <q-btn
                  v-if="c.status === 'processing'"
                  flat
                  round
                  dense
                  icon="sym_r_pause"
                  color="warning"
                  @click="pauseCampaign(c.id)"
                >
                  <q-tooltip>Pausar campaña</q-tooltip>
                </q-btn>

                <!-- Reanudar (si está pausada) -->
                <q-btn
                  v-if="c.status === 'paused'"
                  flat
                  round
                  dense
                  icon="sym_r_play_arrow"
                  color="positive"
                  @click="resumeCampaign(c.id)"
                >
                  <q-tooltip>Reanudar campaña</q-tooltip>
                </q-btn>

                <!-- Reintentar Fallidos (si tiene fallidos y no está enviando) -->
                <q-btn
                  v-if="c.failed_count > 0 && c.status !== 'processing'"
                  flat
                  round
                  dense
                  icon="sym_r_replay"
                  color="amber-9"
                  @click="retryFailedCampaign(c.id)"
                >
                  <q-tooltip>Reintentar {{ c.failed_count }} envíos fallidos</q-tooltip>
                </q-btn>

                <!-- Eliminar -->
                <q-btn
                  flat
                  round
                  dense
                  icon="sym_r_delete"
                  color="negative"
                  @click="deleteCampaign(c.id)"
                >
                  <q-tooltip>Eliminar campaña</q-tooltip>
                </q-btn>
              </div>
            </td>
          </tr>
        </tbody>
      </q-markup-table>
    </q-card>

    <!-- Modal Crear Nueva Campaña Masiva -->
    <q-dialog v-model="isDialogOpen" persistent>
      <q-card style="width: 720px; max-width: 95vw;" class="xf-modal-card">
        <q-card-section class="row items-center justify-between q-pb-none">
          <div class="row items-center q-gutter-x-sm">
            <q-avatar size="36px" color="primary" text-color="white">
              <q-icon name="sym_r_campaign" size="20px" />
            </q-avatar>
            <div>
              <div class="text-h6 text-bold text-ink">Nueva Campaña Masiva</div>
              <div class="text-caption text-muted">Configura el segmento por categorías o etiquetas, redacción y ritmo anti-bloqueo</div>
            </div>
          </div>
          <q-btn flat round dense icon="sym_r_close" class="text-muted" v-close-popup />
        </q-card-section>

        <q-separator class="q-my-md" />

        <q-card-section class="q-gutter-y-md q-pt-none scroll" style="max-height: 74vh;">
          <!-- 1. Identificación y Canal -->
          <div class="row q-col-gutter-md">
            <div class="col-12 col-md-7">
              <q-input
                ref="nameInputRef"
                v-model="form.name"
                label="Nombre de la Campaña *"
                outlined
                dense
                placeholder="ej. Admisiones 2026 - Convocatoria Salud"
                :rules="[val => !!val?.trim() || 'El nombre de la campaña es obligatorio']"
                lazy-rules="ondemand"
              />
            </div>
            <div class="col-12 col-md-5">
              <q-select
                v-model="form.whatsapp_account_id"
                :options="channelOptions"
                emit-value
                map-options
                outlined
                dense
                label="Canal Remitente *"
              />
            </div>
          </div>

          <!-- 2. Segmentación de Audiencia -->
          <div class="xf-section-box q-pa-md rounded-borders">
            <div class="row items-center justify-between q-mb-xs">
              <div class="text-subtitle2 text-bold text-ink">Segmentación de Destinatarios</div>
              <q-badge color="primary" outline class="text-caption text-weight-medium">
                {{ targetModeLabel }}
              </q-badge>
            </div>
            <div class="text-caption text-muted q-mb-sm">
              Selecciona cómo se filtrará la audiencia. Puedes segmentar por categorías organizacionales (carreras/sedes), etiquetas o combinar ambas.
            </div>

            <!-- Botones Segmentados de Modo -->
            <div class="row items-center q-gutter-xs q-mb-md">
              <q-btn
                :outline="targetMode !== 'categories'"
                :unelevated="targetMode === 'categories'"
                color="cyan-8"
                no-caps
                dense
                class="q-px-sm text-weight-bold"
                icon="sym_r_category"
                label="Por Categorías"
                @click="setTargetMode('categories')"
              />
              <q-btn
                :outline="targetMode !== 'tags'"
                :unelevated="targetMode === 'tags'"
                color="purple-8"
                no-caps
                dense
                class="q-px-sm text-weight-bold"
                icon="sym_r_sell"
                label="Por Etiquetas"
                @click="setTargetMode('tags')"
              />
              <q-btn
                :outline="targetMode !== 'mixed'"
                :unelevated="targetMode === 'mixed'"
                color="deep-purple-8"
                no-caps
                dense
                class="q-px-sm text-weight-bold"
                icon="sym_r_tune"
                label="Combinado"
                @click="setTargetMode('mixed')"
              />
              <q-btn
                :outline="targetMode !== 'all'"
                :unelevated="targetMode === 'all'"
                color="blue-grey-7"
                no-caps
                dense
                class="q-px-sm text-weight-bold"
                icon="sym_r_public"
                label="Toda la Base"
                @click="setTargetMode('all')"
              />
            </div>

            <!-- Selector de Categorías (Buscable y con jerarquía) -->
            <div v-if="targetMode === 'categories' || targetMode === 'mixed'" class="q-mb-sm">
              <q-select
                v-model="form.category_ids"
                :options="filteredCategoryOptions"
                multiple
                use-input
                use-chips
                emit-value
                map-options
                outlined
                dense
                label="Categorías Objetivo (Sedes / Carreras / Áreas) *"
                placeholder="Busca y selecciona una o más categorías..."
                @filter="filterCategories"
              >
                <template #prepend>
                  <q-icon name="sym_r_category" size="18px" color="cyan-5" />
                </template>

                <template #option="{ itemProps, opt, selected, toggleOption }">
                  <q-item v-bind="itemProps" dense class="cursor-pointer" @click="toggleOption(opt)">
                    <q-item-section side class="q-pr-xs">
                      <q-checkbox :model-value="selected" dense @update:model-value="toggleOption(opt)" />
                    </q-item-section>
                    <q-item-section avatar style="min-width: 22px;" class="q-pr-xs">
                      <span class="filter-dot" :style="{ backgroundColor: opt.color || '#06b6d4' }"></span>
                    </q-item-section>
                    <q-item-section>
                      <q-item-label class="text-body2 text-ink">{{ opt.name || opt.label }}</q-item-label>
                      <q-item-label v-if="opt.display_path" caption class="text-cyan-7 text-caption">
                        {{ opt.display_path }}
                      </q-item-label>
                    </q-item-section>
                  </q-item>
                </template>

                <template #selected-item="scope">
                  <q-chip
                    removable
                    dense
                    color="cyan-9"
                    text-color="cyan-1"
                    class="q-my-none text-caption"
                    @remove="scope.removeAtIndex(scope.index)"
                  >
                    <span class="filter-dot-chip q-mr-xs" :style="{ backgroundColor: getCategoryColor(scope.opt.value) }"></span>
                    {{ scope.opt.label || scope.opt.name }}
                  </q-chip>
                </template>
              </q-select>
              <div class="text-caption text-muted q-mt-xs" style="font-size: 11px;">
                💡 <em>Al seleccionar una categoría padre (ej. una Facultad o Sede), el envío incluirá automáticamente a todos sus programas y subcategorías.</em>
              </div>
            </div>

            <!-- Selector de Etiquetas -->
            <div v-if="targetMode === 'tags' || targetMode === 'mixed'" class="q-mb-sm">
              <q-select
                v-model="form.tag_ids"
                :options="filteredTagOptions"
                multiple
                use-input
                use-chips
                emit-value
                map-options
                outlined
                dense
                label="Etiquetas Objetivo *"
                placeholder="Busca y selecciona una o más etiquetas..."
                @filter="filterTags"
              >
                <template #prepend>
                  <q-icon name="sym_r_sell" size="18px" color="purple-5" />
                </template>
                <template #option="{ itemProps, opt, selected, toggleOption }">
                  <q-item v-bind="itemProps" dense class="cursor-pointer" @click="toggleOption(opt)">
                    <q-item-section side class="q-pr-xs">
                      <q-checkbox :model-value="selected" dense @update:model-value="toggleOption(opt)" />
                    </q-item-section>
                    <q-item-section>
                      <q-item-label class="text-body2 text-ink">{{ opt.label }}</q-item-label>
                    </q-item-section>
                  </q-item>
                </template>
              </q-select>
            </div>

            <!-- Banner de Conteo en Vivo de Destinatarios -->
            <div
              class="xf-recipient-preview q-pa-sm q-mt-md rounded-borders"
              :class="previewLoading ? 'bg-grey-2 text-grey-8' : (previewCount ?? 0) > 0 ? 'bg-teal-1 text-teal-10 border-teal' : 'bg-amber-1 text-amber-10 border-amber'"
            >
              <div class="row items-center justify-between no-wrap">
                <div class="row items-center q-gutter-x-sm">
                  <q-spinner-dots v-if="previewLoading" size="18px" color="primary" />
                  <q-icon
                    v-else
                    :name="(previewCount ?? 0) > 0 ? 'sym_r_group' : 'sym_r_warning'"
                    size="20px"
                    :color="(previewCount ?? 0) > 0 ? 'teal-8' : 'amber-9'"
                  />
                  <div>
                    <span class="text-weight-bold text-body2">
                      {{ previewLoading ? 'Calculando destinatarios...' : `${previewCount ?? 0} contactos con teléfono encontrados` }}
                    </span>
                    <div class="text-caption text-muted" style="font-size: 11px;">
                      {{ (previewCount ?? 0) > 0 ? 'Destinatarios únicos validados listos para recibir el mensaje' : 'No hay contactos con teléfono que cumplan este filtro' }}
                    </div>
                  </div>
                </div>

                <div v-if="!previewLoading && previewSamples.length > 0" class="row items-center q-gutter-xs">
                  <q-badge
                    v-for="s in previewSamples.slice(0, 3)"
                    :key="s.id"
                    outline
                    color="teal-8"
                    class="text-caption"
                  >
                    {{ s.name }}
                  </q-badge>
                  <span v-if="(previewCount ?? 0) > 3" class="text-caption text-weight-medium text-teal-9">
                    +{{ (previewCount ?? 0) - 3 }} más
                  </span>
                </div>
              </div>
            </div>
          </div>

          <!-- 3. Redacción del Mensaje y Variables -->
          <div class="xf-section-box q-pa-md rounded-borders">
            <div class="row items-center justify-between q-mb-xs">
              <div class="text-subtitle2 text-bold text-ink">Plantilla del Mensaje</div>
              <div class="row items-center q-gutter-x-xs">
                <span class="text-caption text-muted">Insertar:</span>
                <q-btn size="xs" outline color="primary" label="{nombre}" no-caps @click="insertVariable('{name}')" />
                <q-btn size="xs" outline color="primary" label="{telefono}" no-caps @click="insertVariable('{phone}')" />
                <q-btn size="xs" outline color="amber-8" label="Spintax Saludo" no-caps @click="insertVariable('{Hola|Buen día|Estimado/a}')" />
              </div>
            </div>

            <q-input
              v-model="form.message_template"
              type="textarea"
              rows="4"
              outlined
              placeholder="Escribe el mensaje aquí... ej: {Hola|Buen día} {name}, queremos informarte que..."
              class="q-mt-xs"
            />

            <!-- Preview interactivo en tiempo real -->
            <div class="q-mt-sm">
              <div class="text-caption text-bold text-muted q-mb-xs">Vista Previa Estimada del Cliente:</div>
              <div class="xf-whatsapp-preview q-pa-sm rounded-borders">
                <div class="text-caption text-ink" style="white-space: pre-wrap;">{{ previewText }}</div>
                <div class="text-right text-caption text-muted" style="font-size: 10px;">14:30 ✓✓</div>
              </div>
            </div>
          </div>

          <!-- 4. Algoritmo Anti-Baneo y Delay -->
          <div class="xf-anti-ban-box q-pa-md rounded-borders">
            <div class="row items-center justify-between">
              <div class="row items-center q-gutter-x-xs">
                <q-icon name="sym_r_shield" size="20px" color="positive" />
                <span class="text-subtitle2 text-bold text-ink">Protección Anti-Baneo de WhatsApp</span>
              </div>
              <q-badge color="positive" class="text-bold">{{ form.delay_seconds }}s intervalo promedio</q-badge>
            </div>
            <p class="text-caption text-muted q-mt-xs q-mb-sm">
              Un intervalo dinámico entre cada mensaje simula el comportamiento humano y previene restricciones por parte de Meta.
            </p>

            <q-slider
              v-model="form.delay_seconds"
              :min="10"
              :max="60"
              :step="5"
              color="primary"
              label
              class="q-mt-md"
            />
          </div>
        </q-card-section>

        <q-separator />

        <q-card-actions align="right" class="q-pa-md">
          <q-btn flat label="Cancelar" color="grey-7" no-caps v-close-popup />
          <q-btn
            outline
            label="Guardar Borrador"
            color="primary"
            no-caps
            :loading="saving"
            :disable="previewCount === 0 || previewLoading"
            @click="handleSaveCampaign(false)"
          />
          <q-btn
            unelevated
            label="Crear e Iniciar Envío"
            color="positive"
            no-caps
            class="text-weight-bold"
            :loading="saving"
            :disable="previewCount === 0 || previewLoading"
            @click="handleSaveCampaign(true)"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue'
import { http } from '@/shared/api/http'
import { useAppNotify } from '@/shared/composables/useAppNotify'
import { formatCategoryDisplayPath, matchesCategorySearch } from '@/modules/parameters/utils/category-formatter'

const notify = useAppNotify()

const search = ref('')
const selectedStatus = ref('all')
const isDialogOpen = ref(false)
const saving = ref(false)
const targetMode = ref<'all' | 'categories' | 'tags' | 'mixed'>('categories')

interface CampaignModel {
  id: string
  name: string
  created_at: string
  connection: string
  total_contacts: number
  sent_count: number
  failed_count: number
  progress: number
  delay_seconds: number
  status: 'draft' | 'processing' | 'paused' | 'completed'
  target_type?: string
  target_summary?: string
}

const campaigns = ref<CampaignModel[]>([])
const channelOptions = ref<{ label: string; value: string }[]>([])
const rawCategories = ref<any[]>([])
const categorySearch = ref('')
const tagOptions = ref<{ label: string; value: string }[]>([])
const tagSearch = ref('')

const previewLoading = ref(false)
const previewCount = ref<number | null>(null)
const previewSamples = ref<{ id: string; name: string; phone: string }[]>([])

const statusOptions = [
  { label: 'Todos los estados', value: 'all' },
  { label: 'Borrador', value: 'draft' },
  { label: 'Enviando (Activa)', value: 'processing' },
  { label: 'Pausada', value: 'paused' },
  { label: 'Completada', value: 'completed' },
]

const form = reactive({
  name: '',
  whatsapp_account_id: '',
  category_ids: [] as string[],
  tag_ids: [] as string[],
  message_template: '{Hola|Buen día} {name}, te escribimos de UNITEPC para informarte sobre el inicio de clases y beneficios de matrícula de este periodo.',
  delay_seconds: 20,
})

const targetModeLabel = computed(() => {
  switch (targetMode.value) {
    case 'categories':
      return 'Filtrado por Categorías'
    case 'tags':
      return 'Filtrado por Etiquetas'
    case 'mixed':
      return 'Filtro Combinado (Categorías + Etiquetas)'
    case 'all':
    default:
      return 'Toda la Base de Contactos'
  }
})

onMounted(async () => {
  await Promise.all([loadCampaigns(), loadChannels(), loadCategories(), loadTags()])
})

async function loadCampaigns() {
  try {
    const res = await http.get('/campaigns')
    if (res.data?.data) {
      campaigns.value = res.data.data.map((c: any) => ({
        id: c.id,
        name: c.name,
        created_at: formatDate(c.created_at),
        connection: c.whatsapp_account?.name || 'Canal Predeterminado',
        total_contacts: c.total_contacts || 0,
        sent_count: c.sent_count || 0,
        failed_count: c.failed_count || 0,
        progress: c.total_contacts > 0 ? Math.round((c.sent_count / c.total_contacts) * 100) : 0,
        delay_seconds: c.delay_seconds || 20,
        status: c.status || 'draft',
        target_type: c.target_type || 'all',
        target_summary: c.target_summary || '',
      }))
      checkPollingState()
    }
  } catch {
    // Si no hay campañas, mantener array vacío
  }
}

async function loadChannels() {
  try {
    const res = await http.get('/whatsapp/accounts')
    if (res.data?.data) {
      channelOptions.value = res.data.data.map((a: any) => ({
        label: `${a.name} (${a.display_phone_number || 'Conectado'})`,
        value: a.id,
      }))
      if (channelOptions.value.length > 0 && !form.whatsapp_account_id) {
        form.whatsapp_account_id = channelOptions.value[0].value
      }
    }
  } catch {
    channelOptions.value = [{ label: 'WhatsApp Principal', value: '' }]
  }
}

async function loadCategories() {
  try {
    const res = await http.get('/categories')
    if (res.data?.data) {
      rawCategories.value = res.data.data
    }
  } catch {
    rawCategories.value = []
  }
}

async function loadTags() {
  try {
    const res = await http.get('/tags')
    if (res.data?.data) {
      tagOptions.value = res.data.data.map((t: any) => ({
        label: t.name,
        value: t.id,
      }))
    }
  } catch {
    tagOptions.value = []
  }
}

const allCategoryOptions = computed(() => {
  const list = rawCategories.value
  return list.map((c: any) => {
    const displayPath = formatCategoryDisplayPath(c, list)
    return {
      label: displayPath,
      name: c.name,
      code: c.code,
      value: c.id,
      color: c.color || '#06b6d4',
      icon: c.icon || 'sym_r_category',
      display_path: displayPath,
      full_path: c.full_path,
    }
  })
})

const filteredCategoryOptions = computed(() => {
  const needle = categorySearch.value.toLowerCase().trim()
  if (!needle) return allCategoryOptions.value
  return allCategoryOptions.value.filter((c) =>
    matchesCategorySearch(c, needle, c.display_path)
  )
})

function filterCategories(val: string, update: (cb: () => void) => void) {
  update(() => {
    categorySearch.value = val
  })
}

const filteredTagOptions = computed(() => {
  const needle = tagSearch.value.toLowerCase().trim()
  if (!needle) return tagOptions.value
  return tagOptions.value.filter((t) => t.label.toLowerCase().includes(needle))
})

function filterTags(val: string, update: (cb: () => void) => void) {
  update(() => {
    tagSearch.value = val
  })
}

function getCategoryColor(id: string): string {
  const match = rawCategories.value.find((c) => c.id === id)
  return match?.color || '#06b6d4'
}

function setTargetMode(mode: 'all' | 'categories' | 'tags' | 'mixed') {
  targetMode.value = mode
}

// Conteo y vista previa reactiva de destinatarios
let previewDebounceTimer: any = null
watch(
  [targetMode, () => form.category_ids, () => form.tag_ids],
  () => {
    clearTimeout(previewDebounceTimer)
    previewDebounceTimer = setTimeout(() => {
      fetchRecipientPreview()
    }, 250)
  },
  { deep: true },
)

async function fetchRecipientPreview() {
  previewLoading.value = true
  try {
    const payload = {
      target_mode: targetMode.value,
      category_ids: targetMode.value === 'categories' || targetMode.value === 'mixed' ? form.category_ids : [],
      tag_ids: targetMode.value === 'tags' || targetMode.value === 'mixed' ? form.tag_ids : [],
    }
    const res = await http.post('/campaigns/preview-recipients', payload)
    previewCount.value = res.data?.count ?? 0
    previewSamples.value = res.data?.samples ?? []
  } catch {
    previewCount.value = 0
    previewSamples.value = []
  } finally {
    previewLoading.value = false
  }
}

const filteredCampaigns = computed(() => {
  return campaigns.value.filter((c) => {
    const matchesSearch =
      !search.value ||
      c.name.toLowerCase().includes(search.value.toLowerCase()) ||
      (c.target_summary && c.target_summary.toLowerCase().includes(search.value.toLowerCase()))
    const matchesStatus = selectedStatus.value === 'all' || c.status === selectedStatus.value
    return matchesSearch && matchesStatus
  })
})

const totalCampaignsCount = computed(() => campaigns.value.length)
const processingCount = computed(() => campaigns.value.filter((c) => c.status === 'processing').length)
const totalSentMessages = computed(() => campaigns.value.reduce((acc, c) => acc + (c.sent_count || 0), 0))
const totalRecipients = computed(() => campaigns.value.reduce((acc, c) => acc + (c.total_contacts || 0), 0))

const previewText = computed(() => {
  let text = form.message_template
  // Resolver Spintax básico {A|B|C}
  text = text.replace(/\{([^{}]+)\}/g, (match, choices) => {
    if (choices.includes('|')) {
      const parts = choices.split('|')
      return parts[0]
    }
    if (choices === 'name') return 'Carlos Mendoza'
    if (choices === 'phone') return '+591 70000000'
    return match
  })
  return text || 'Escribe tu mensaje para previsualizar...'
})

function openCreateDialog() {
  const today = new Date().toLocaleDateString('es-BO', { day: '2-digit', month: '2-digit' })
  const time = new Date().toLocaleTimeString('es-BO', { hour: '2-digit', minute: '2-digit' })
  form.name = `Campaña Difusión - ${today} ${time}`
  form.category_ids = []
  form.tag_ids = []
  targetMode.value = 'categories'
  form.delay_seconds = 20
  isDialogOpen.value = true
  fetchRecipientPreview()
}

// Auto-sugerir nombre descriptivo de campaña según la primera categoría seleccionada
watch(
  () => form.category_ids,
  (newIds) => {
    if (newIds.length > 0 && (!form.name.trim() || form.name.startsWith('Campaña '))) {
      const cat = rawCategories.value.find((c) => c.id === newIds[0])
      if (cat) {
        const today = new Date().toLocaleDateString('es-BO', { day: '2-digit', month: '2-digit' })
        form.name = `Campaña ${cat.name} - ${today}`
      }
    }
  },
  { deep: true },
)

// Auto-sugerir nombre descriptivo de campaña según la primera etiqueta seleccionada
watch(
  () => form.tag_ids,
  (newIds) => {
    if (newIds.length > 0 && targetMode.value === 'tags' && (!form.name.trim() || form.name.startsWith('Campaña '))) {
      const tag = tagOptions.value.find((t) => t.value === newIds[0])
      if (tag) {
        const today = new Date().toLocaleDateString('es-BO', { day: '2-digit', month: '2-digit' })
        form.name = `Campaña ${tag.label} - ${today}`
      }
    }
  },
  { deep: true },
)

function insertVariable(variableTag: string) {
  form.message_template += ` ${variableTag}`
}

async function handleSaveCampaign(startImmediately = false) {
  if (!form.name.trim()) {
    const today = new Date().toLocaleDateString('es-BO', { day: '2-digit', month: '2-digit' })
    const time = new Date().toLocaleTimeString('es-BO', { hour: '2-digit', minute: '2-digit' })
    const segmentName =
      targetMode.value === 'categories' && form.category_ids.length > 0
        ? rawCategories.value.find((c) => c.id === form.category_ids[0])?.name || 'Categorías'
        : targetMode.value === 'tags' && form.tag_ids.length > 0
        ? tagOptions.value.find((t) => t.value === form.tag_ids[0])?.label || 'Etiquetas'
        : 'General'
    form.name = `Campaña ${segmentName} - ${today} ${time}`
  }
  if (!form.message_template.trim()) {
    notify.warning({ message: 'La plantilla del mensaje no puede estar vacía.' })
    return
  }
  if (targetMode.value === 'categories' && form.category_ids.length === 0) {
    notify.warning({ message: 'Debes seleccionar al menos una categoría objetivo.' })
    return
  }
  if (targetMode.value === 'tags' && form.tag_ids.length === 0) {
    notify.warning({ message: 'Debes seleccionar al menos una etiqueta objetivo.' })
    return
  }
  if (targetMode.value === 'mixed' && form.category_ids.length === 0 && form.tag_ids.length === 0) {
    notify.warning({ message: 'Debes seleccionar al menos una categoría o etiqueta objetivo.' })
    return
  }
  if ((previewCount.value ?? 0) === 0) {
    notify.warning({ message: 'No hay destinatarios encontrados para este filtro. Ajusta los criterios para continuar.' })
    return
  }

  saving.value = true
  try {
    const payload = {
      name: form.name.trim(),
      whatsapp_account_id: form.whatsapp_account_id || undefined,
      message_template: form.message_template.trim(),
      delay_seconds: form.delay_seconds,
      target_mode: targetMode.value,
      category_ids: targetMode.value === 'categories' || targetMode.value === 'mixed' ? form.category_ids : undefined,
      tag_ids: targetMode.value === 'tags' || targetMode.value === 'mixed' ? form.tag_ids : undefined,
    }

    const res = await http.post('/campaigns', payload)
    const newCampaign = res.data?.data

    if (startImmediately && newCampaign?.id) {
      await http.post(`/campaigns/${newCampaign.id}/start`)
      notify.success({ message: 'Campaña creada e iniciada con intervalos anti-bloqueo.' })
    } else {
      notify.success({ message: 'Campaña guardada en modo borrador.' })
    }

    await loadCampaigns()
    isDialogOpen.value = false
  } catch (err: any) {
    notify.error({ message: err?.response?.data?.message || 'Error al guardar campaña.' })
  } finally {
    saving.value = false
  }
}

async function startCampaign(id: string) {
  try {
    await http.post(`/campaigns/${id}/start`)
    const target = campaigns.value.find((c) => c.id === id)
    if (target) target.status = 'processing'
    notify.success({ message: 'Disparo de campaña iniciado con protección anti-ban.' })
    startPolling()
  } catch (err: any) {
    notify.error({ message: err?.response?.data?.message || 'Error al iniciar campaña.' })
  }
}

async function pauseCampaign(id: string) {
  try {
    await http.post(`/campaigns/${id}/pause`)
    const target = campaigns.value.find((c) => c.id === id)
    if (target) target.status = 'paused'
    notify.info({ message: 'Campaña pausada.' })
    checkPollingState()
  } catch (err: any) {
    notify.error({ message: err?.response?.data?.message || 'Error al pausar campaña.' })
  }
}

async function resumeCampaign(id: string) {
  try {
    await http.post(`/campaigns/${id}/resume`)
    const target = campaigns.value.find((c) => c.id === id)
    if (target) target.status = 'processing'
    notify.success({ message: 'Campaña reanudada.' })
    startPolling()
  } catch (err: any) {
    notify.error({ message: err?.response?.data?.message || 'Error al reanudar campaña.' })
  }
}

async function deleteCampaign(id: string) {
  try {
    await http.delete(`/campaigns/${id}`)
    campaigns.value = campaigns.value.filter((c) => c.id !== id)
    notify.success({ message: 'Campaña eliminada correctamente.' })
    checkPollingState()
  } catch (err: any) {
    notify.error({ message: err?.response?.data?.message || 'Error al eliminar campaña.' })
  }
}

async function retryFailedCampaign(id: string) {
  try {
    const res = await http.post(`/campaigns/${id}/retry-failed`)
    notify.success({ message: res.data?.message || 'Destinatarios reactivados para reintento.' })
    await loadCampaigns()
    startPolling()
  } catch (err: any) {
    notify.error({ message: err?.response?.data?.message || 'Error al reintentar envíos fallidos.' })
  }
}

let pollingInterval: any = null

function checkPollingState() {
  const hasActiveCampaign = campaigns.value.some((c) => c.status === 'processing')
  if (hasActiveCampaign) {
    if (!pollingInterval) {
      pollingInterval = setInterval(() => {
        loadCampaigns()
      }, 3000)
    }
  } else if (pollingInterval) {
    clearInterval(pollingInterval)
    pollingInterval = null
  }
}

function startPolling() {
  if (!pollingInterval) {
    pollingInterval = setInterval(() => {
      loadCampaigns()
    }, 3000)
  }
}

onUnmounted(() => {
  if (pollingInterval) {
    clearInterval(pollingInterval)
    pollingInterval = null
  }
})

function getStatusBadgeColor(status: string) {
  switch (status) {
    case 'processing':
      return 'warning'
    case 'paused':
      return 'amber-8'
    case 'completed':
      return 'positive'
    case 'draft':
    default:
      return 'grey-6'
  }
}

function getStatusBadgeLabel(status: string) {
  switch (status) {
    case 'processing':
      return 'Enviando...'
    case 'paused':
      return 'Pausada'
    case 'completed':
      return 'Completada'
    case 'draft':
    default:
      return 'Borrador'
  }
}

function formatDate(isoDate?: string) {
  if (!isoDate) return 'Hoy'
  try {
    const d = new Date(isoDate)
    return d.toLocaleDateString('es-BO', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })
  } catch {
    return isoDate
  }
}
</script>

<style scoped lang="scss">
.xf-campaigns-page {
  padding: 24px 32px;
  background-color: var(--crm-bg-app);
  min-height: calc(100vh - 52px);
}

.xf-kpi-card {
  background: var(--crm-bg-card);
  border: 1px solid var(--crm-color-border);
  border-radius: var(--crm-radius-card);
}

.xf-filter-card {
  background: var(--crm-bg-card);
  border: 1px solid var(--crm-color-border);
  border-radius: var(--crm-radius-control);
}

.xf-search-input {
  min-width: 240px;
}

.xf-table-card {
  background: var(--crm-bg-card);
  border: 1px solid var(--crm-color-border);
  border-radius: var(--crm-radius-card);
  overflow: hidden;
}

.xf-campaigns-table {
  background: transparent;

  thead tr th {
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.06em;
    color: var(--crm-color-muted);
    border-bottom: 1px solid var(--crm-color-border);
    padding: 14px 16px;
  }

  tbody tr td {
    padding: 14px 16px;
    border-bottom: 1px solid var(--crm-color-border);
  }
}

.xf-empty-card {
  background: var(--crm-bg-card);
  border: 1px dashed var(--crm-color-border);
  border-radius: var(--crm-radius-card);
}

.xf-empty-icon-circle {
  width: 72px;
  height: 72px;
  border-radius: 50%;
  background: var(--crm-color-primary-soft);
  display: flex;
  align-items: center;
  justify-content: center;
}

.xf-modal-card {
  background: var(--crm-bg-card);
  border: 1px solid var(--crm-color-border);
  border-radius: var(--crm-radius-card);
  color: var(--crm-color-ink);
}

.xf-section-box {
  background: rgba(255, 255, 255, 0.02);
  border: 1px solid var(--crm-color-border);
}

.body--light .xf-section-box {
  background: #f8fafc;
  border-color: #e2e8f0;
}

.filter-dot {
  display: inline-block;
  width: 9px;
  height: 9px;
  border-radius: 50%;
}

.filter-dot-chip {
  display: inline-block;
  width: 7px;
  height: 7px;
  border-radius: 50%;
}

.xf-recipient-preview {
  border: 1px solid transparent;
  transition: all 0.2s ease;
}

.border-teal {
  border-color: rgba(20, 184, 166, 0.35);
}

.border-amber {
  border-color: rgba(245, 158, 11, 0.35);
}

.xf-whatsapp-preview {
  background: rgba(16, 185, 129, 0.08);
  border: 1px solid rgba(16, 185, 129, 0.2);
}

.xf-anti-ban-box {
  background: rgba(16, 185, 129, 0.05);
  border: 1px solid rgba(16, 185, 129, 0.2);
}

.letter-spacing-wide {
  letter-spacing: 0.06em;
}
</style>
