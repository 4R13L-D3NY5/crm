<template>
  <div class="business-hours-section q-gutter-y-lg">
    <!-- 1. Estado en Tiempo Real -->
    <q-card flat bordered class="xf-status-banner q-pa-md" :class="isCurrentlyOpen ? 'xf-status-banner--open' : 'xf-status-banner--closed'">
      <div class="row items-center justify-between no-wrap">
        <div class="row items-center q-gutter-x-md">
          <q-avatar size="44px" :color="isCurrentlyOpen ? 'positive' : 'amber-9'" text-color="white">
            <q-icon :name="isCurrentlyOpen ? 'sym_r_storefront' : 'sym_r_nightlight'" size="26px" />
          </q-avatar>
          <div>
            <div class="row items-center q-gutter-x-sm">
              <span class="text-subtitle1 text-bold text-white">
                {{ isCurrentlyOpen ? '🟢 EN HORARIO DE ATENCIÓN' : '🌙 FUERA DE HORARIO LABORAL' }}
              </span>
              <q-badge :color="selectedConfig?.is_active ? 'teal-9' : 'grey-8'" class="text-caption text-bold">
                {{ selectedConfig?.is_active ? 'Horario Activo' : 'Pausado' }}
              </q-badge>
              <q-badge v-if="selectedConfig?.auto_reply_enabled" color="cyan-9" text-color="cyan-2" class="text-caption">
                Auto-respuesta Activa
              </q-badge>
            </div>
            <div class="text-caption text-grey-4 q-mt-xs">
              {{ currentDayName }}, {{ currentTime }} ({{ form.timezone }}).
              {{ isCurrentlyOpen ? 'Los operadores se encuentran atendiendo chats.' : 'Los mensajes entrantes recibirán la respuesta automática programada.' }}
            </div>
          </div>
        </div>

        <div class="row items-center q-gutter-x-sm">
          <span class="text-caption text-grey-4">Estado de este Horario:</span>
          <q-toggle
            v-model="form.is_active"
            dense
            color="positive"
            @update:model-value="toggleCurrentActive"
          >
            <q-tooltip>{{ form.is_active ? 'Pausar este horario' : 'Activar este horario' }}</q-tooltip>
          </q-toggle>
        </div>
      </div>
    </q-card>

    <!-- Spinner de carga -->
    <div v-if="loading" class="text-center q-pa-xl text-grey-4">
      <q-spinner-dots size="36px" color="primary" />
      <div class="q-mt-sm">Cargando parametrizaciones de horario laboral...</div>
    </div>

    <div v-else class="q-gutter-y-lg">
      <!-- 2. Selector de Parametrizaciones (Perfiles, Campañas y Operadores) -->
      <q-card flat bordered class="xf-panel-card q-pa-md">
        <div class="row items-center justify-between q-mb-sm">
          <div class="row items-center q-gutter-x-xs">
            <q-icon name="sym_r_tune" color="teal-4" size="20px" />
            <span class="text-subtitle2 text-bold text-white">Parametrizaciones de Horario Guardadas</span>
            <q-badge color="grey-8" class="q-ml-xs text-caption">{{ configsList.length }} configuraciones</q-badge>
          </div>

          <q-btn
            unelevated
            size="sm"
            color="teal-4"
            text-color="dark"
            icon="sym_r_add"
            label="Nueva Parametrización / Campaña"
            no-caps
            class="text-weight-bold"
            @click="openNewConfigForm"
          />
        </div>

        <!-- Chips / Píldoras de Configuraciones -->
        <div class="row items-center q-gutter-sm q-pt-xs">
          <div
            v-for="cfg in configsList"
            :key="cfg.id"
            class="xf-config-pill cursor-pointer"
            :class="{ 'xf-config-pill--active': selectedConfig?.id === cfg.id }"
            @click="selectConfig(cfg)"
          >
            <div class="row items-center q-gutter-x-xs no-wrap">
              <q-icon
                :name="cfg.scope_type === 'operator' ? 'sym_r_person' : 'sym_r_domain'"
                size="16px"
                :color="selectedConfig?.id === cfg.id ? 'teal-3' : 'grey-4'"
              />
              <span class="text-body2 text-weight-medium text-white ellipsis" style="max-width: 200px">
                {{ cfg.name }}
              </span>
              <q-badge v-if="cfg.is_default" color="blue-9" class="text-caption font-mono" style="font-size: 0.65rem">
                Principal
              </q-badge>
              <q-badge v-if="(cfg.channels || []).length > 0" color="teal-9" text-color="teal-2" class="text-caption" style="font-size: 0.65rem">
                {{ (cfg.channels || []).length }} can.
              </q-badge>
              <q-badge v-if="(cfg.users || []).length > 0" color="purple-9" text-color="purple-2" class="text-caption" style="font-size: 0.65rem">
                {{ (cfg.users || []).length }} op.
              </q-badge>
              <q-badge v-if="cfg.validity_type === 'date_range'" color="amber-9" class="text-caption" style="font-size: 0.65rem">
                Temporada
              </q-badge>
            </div>
          </div>
        </div>
      </q-card>

      <!-- 3. Formulario de Edición de la Parametrización Seleccionada -->
      <q-card flat bordered class="xf-panel-card q-pa-lg">
        <div class="row items-center justify-between q-mb-lg border-bottom-subtle pb-sm">
          <div>
            <div class="text-subtitle1 text-bold text-white flex items-center q-gutter-x-xs">
              <q-icon name="sym_r_edit_calendar" color="teal-4" size="22px" />
              <span>{{ isCreatingNew ? 'Creando Nueva Parametrización' : `Editando: ${form.name}` }}</span>
            </div>
            <div class="text-caption text-grey-4">
              Configura el nombre, ámbito (general o por operador), vigencia por campaña y turnos mañana/tarde.
            </div>
          </div>

          <div v-if="!isCreatingNew && configsList.length > 1" class="row items-center q-gutter-x-xs">
            <q-btn
              flat
              dense
              color="negative"
              icon="sym_r_delete"
              label="Eliminar Configuración"
              no-caps
              size="sm"
              @click="confirmDeleteConfig"
            />
          </div>
        </div>

        <!-- Fila 1: Nombre y Ámbito (General vs Operador) -->
        <div class="row q-col-gutter-md q-mb-md">
          <div class="col-12 col-md-6">
            <q-input
              v-model="form.name"
              label="Nombre de la Parametrización *"
              outlined
              dark
              dense
              placeholder="ej: Horario General UNITEPC, Campaña Admisiones 2026, Turno Tarde..."
              hint="Identificador de la temporada, campaña u horario de operador"
            >
              <template #prepend>
                <q-icon name="sym_r_label" color="grey-4" size="18px" />
              </template>
            </q-input>
          </div>

          <div class="col-12 col-md-6">
            <div class="row items-center q-gutter-x-md">
              <div class="text-caption text-grey-4 text-bold">ÁMBITO / ASIGNACIÓN:</div>
              <q-radio
                v-model="form.scope_type"
                val="general"
                label="🏢 General (Toda la Sede / Organización)"
                dark
                color="teal-4"
                dense
              />
              <q-radio
                v-model="form.scope_type"
                val="operator"
                label="👤 Diferenciado por Operador"
                dark
                color="purple-4"
                dense
              />
            </div>
          </div>
        </div>

        <!-- Checkbox Horario Predeterminado (si es general) -->
        <div v-if="form.scope_type === 'general'" class="q-mb-sm">
          <q-checkbox
            v-model="form.is_default"
            label="Marcar como Horario General Predeterminado de la Universidad"
            dark
            color="primary"
            dense
          />
        </div>

        <!-- 🎯 Asignación de Canales, Operadores y Enrutamiento para este Horario / Turno -->
        <div class="q-mb-md bg-dark-subtle q-pa-md rounded-borders" style="border: 1px solid rgba(255, 255, 255, 0.08);">
          <div class="row items-center justify-between q-mb-md">
            <div class="row items-center q-gutter-x-sm">
              <q-avatar size="28px" color="teal-9" text-color="teal-2">
                <q-icon name="sym_r_hub" size="18px" />
              </q-avatar>
              <div>
                <div class="text-subtitle2 text-bold text-white">Cobertura & Asignación del Turno</div>
                <div class="text-caption text-grey-4">
                  Define los canales y operadores asignados a este horario de atención
                </div>
              </div>
            </div>

            <div class="row items-center q-gutter-x-xs">
              <q-badge color="teal-9" text-color="teal-2" class="text-caption">
                {{ form.channel_ids.length }} canales seleccionados
              </q-badge>
              <q-badge color="purple-9" text-color="purple-2" class="text-caption">
                {{ form.user_ids.length }} operadores asignados
              </q-badge>
            </div>
          </div>

          <div class="row q-col-gutter-md">
            <!-- 1. Canales Asignados -->
            <div class="col-12 col-md-6">
              <div class="row items-center justify-between q-mb-xs">
                <span class="text-caption text-teal-3 text-bold">📱 CANALES ASIGNADOS A ESTE HORARIO:</span>
                <div class="row items-center q-gutter-x-xs">
                  <q-btn flat dense no-caps size="xs" color="teal-3" label="Todos" @click="selectAllChannels" />
                  <span class="text-grey-6">•</span>
                  <q-btn flat dense no-caps size="xs" color="grey-4" label="Limpiar" @click="clearChannels" />
                </div>
              </div>
              <q-select
                v-model="form.channel_ids"
                :options="channelsList"
                option-value="id"
                option-label="name"
                emit-value
                map-options
                multiple
                use-chips
                outlined
                dark
                dense
                placeholder="Selecciona uno o más canales (WhatsApp La Paz, Cochabamba, Redes...)"
                hint="Los mensajes de estos canales serán atendidos bajo este horario"
              >
                <template #prepend>
                  <q-icon name="sym_r_cell_tower" color="teal-4" size="18px" />
                </template>
                <template #option="{ itemProps, opt, selected, toggleOption }">
                  <q-item v-bind="itemProps" dense dark class="cursor-pointer" @click="toggleOption(opt)">
                    <q-item-section side>
                      <q-checkbox :model-value="selected" dense dark @update:model-value="toggleOption(opt)" />
                    </q-item-section>
                    <q-item-section avatar style="min-width: 28px;">
                      <q-icon
                        :name="getChannelIcon(opt)"
                        :color="getChannelIconColor(opt)"
                        size="18px"
                      />
                    </q-item-section>
                    <q-item-section>
                      <q-item-label class="text-white text-bold">{{ opt.name }}</q-item-label>
                      <q-item-label caption class="text-grey-4">{{ opt.phone_number || opt.session_type || 'Canal' }}</q-item-label>
                    </q-item-section>
                  </q-item>
                </template>
              </q-select>
            </div>

            <!-- 2. Operadores Asignados -->
            <div class="col-12 col-md-6">
              <div class="row items-center justify-between q-mb-xs">
                <span class="text-caption text-purple-3 text-bold">👥 OPERADORES EN TURNO / GUARDIA:</span>
                <div class="row items-center q-gutter-x-xs">
                  <q-btn flat dense no-caps size="xs" color="purple-3" label="Todos" @click="selectAllOperators" />
                  <span class="text-grey-6">•</span>
                  <q-btn flat dense no-caps size="xs" color="grey-4" label="Limpiar" @click="clearOperators" />
                </div>
              </div>
              <q-select
                v-model="form.user_ids"
                :options="operatorsList"
                option-value="id"
                option-label="name"
                emit-value
                map-options
                multiple
                use-chips
                outlined
                dark
                dense
                placeholder="Selecciona los operadores de este turno (Yerko, Anet, Juan Pablo...)"
                hint="Operadores que tendrán visibilidad y responderán los canales en este horario"
              >
                <template #prepend>
                  <q-icon name="sym_r_group" color="purple-4" size="18px" />
                </template>
                <template #option="{ itemProps, opt, selected, toggleOption }">
                  <q-item v-bind="itemProps" dense dark class="cursor-pointer" @click="toggleOption(opt)">
                    <q-item-section side>
                      <q-checkbox :model-value="selected" dense dark @update:model-value="toggleOption(opt)" />
                    </q-item-section>
                    <q-item-section avatar style="min-width: 28px;">
                      <q-avatar size="24px" color="purple-9" text-color="white" class="text-bold text-caption">
                        {{ (opt.name || 'U').charAt(0).toUpperCase() }}
                      </q-avatar>
                    </q-item-section>
                    <q-item-section>
                      <q-item-label class="text-white text-bold">{{ opt.name }}</q-item-label>
                      <q-item-label caption class="text-grey-4">{{ opt.email }}</q-item-label>
                    </q-item-section>
                  </q-item>
                </template>
              </q-select>
            </div>

            <!-- 3. Categorías (Opcional) -->
            <div class="col-12 col-md-6 q-mt-xs">
              <div class="text-caption text-cyan-3 text-bold q-mb-xs">
                🏷️ CATEGORÍAS ASOCIADAS (OPCIONAL):
              </div>
              <q-select
                v-model="form.category_ids"
                :options="categoriesList"
                option-value="id"
                option-label="name"
                emit-value
                map-options
                multiple
                use-chips
                outlined
                dark
                dense
                placeholder="Todas las categorías (o filtra ej: Admisiones, Becas...)"
                hint="Dejar vacío para atender todas las temáticas de consulta"
              >
                <template #prepend>
                  <q-icon name="sym_r_category" color="cyan-4" size="18px" />
                </template>
                <template #option="{ itemProps, opt, selected, toggleOption }">
                  <q-item v-bind="itemProps" dense dark class="cursor-pointer" @click="toggleOption(opt)">
                    <q-item-section side>
                      <q-checkbox :model-value="selected" dense dark @update:model-value="toggleOption(opt)" />
                    </q-item-section>
                    <q-item-section>
                      <q-item-label class="text-white">{{ opt.name }}</q-item-label>
                    </q-item-section>
                  </q-item>
                </template>
              </q-select>
            </div>

            <!-- 4. Filas / Departamentos (Opcional) -->
            <div class="col-12 col-md-6 q-mt-xs">
              <div class="text-caption text-amber-3 text-bold q-mb-xs">
                🏢 FILAS / DEPARTAMENTOS ASOCIADOS (OPCIONAL):
              </div>
              <q-select
                v-model="form.queue_ids"
                :options="queuesList"
                option-value="id"
                option-label="name"
                emit-value
                map-options
                multiple
                use-chips
                outlined
                dark
                dense
                placeholder="Todas las filas (o filtra ej: Plataforma Central, Admisiones...)"
                hint="Dejar vacío para atender todas las colas de atención"
              >
                <template #prepend>
                  <q-icon name="sym_r_view_carousel" color="amber-4" size="18px" />
                </template>
                <template #option="{ itemProps, opt, selected, toggleOption }">
                  <q-item v-bind="itemProps" dense dark class="cursor-pointer" @click="toggleOption(opt)">
                    <q-item-section side>
                      <q-checkbox :model-value="selected" dense dark @update:model-value="toggleOption(opt)" />
                    </q-item-section>
                    <q-item-section>
                      <q-item-label class="text-white">{{ opt.name }}</q-item-label>
                    </q-item-section>
                  </q-item>
                </template>
              </q-select>
            </div>
          </div>
        </div>

        <!-- Vigencia del Horario (Iniciar Ya vs Rango de Fechas) -->
        <div class="row items-center justify-between q-mb-sm q-mt-md">
          <div class="text-caption text-grey-4 text-bold flex items-center q-gutter-x-xs">
            <q-icon name="sym_r_calendar_today" color="amber-4" size="18px" />
            <span>VIGENCIA TEMPORAL:</span>
          </div>

          <div class="row items-center q-gutter-x-xs">
            <span class="text-caption text-grey-4">Zona Horaria:</span>
            <q-select
              v-model="form.timezone"
              :options="timezoneOptions"
              dense
              outlined
              dark
              options-dense
              style="min-width: 180px"
            />
          </div>
        </div>

        <div class="row q-col-gutter-md q-mb-md">
          <div class="col-12 col-md-6">
            <div
              class="xf-validity-option cursor-pointer"
              :class="{ 'xf-validity-option--selected': form.validity_type === 'immediate' }"
              @click="form.validity_type = 'immediate'"
            >
              <div class="row items-start no-wrap q-gutter-x-sm">
                <q-radio v-model="form.validity_type" val="immediate" dark color="primary" dense />
                <div>
                  <div class="text-body2 text-bold text-white flex items-center q-gutter-x-xs">
                    <q-icon name="sym_r_bolt" color="amber-4" size="18px" />
                    <span>Continuo e Indefinido</span>
                  </div>
                  <div class="text-caption text-grey-4 q-mt-xs">
                    Rige permanentemente semana tras semana sin fecha de expiración.
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="col-12 col-md-6">
            <div
              class="xf-validity-option cursor-pointer"
              :class="{ 'xf-validity-option--selected': form.validity_type === 'date_range' }"
              @click="form.validity_type = 'date_range'"
            >
              <div class="row items-start no-wrap q-gutter-x-sm">
                <q-radio v-model="form.validity_type" val="date_range" dark color="primary" dense />
                <div>
                  <div class="text-body2 text-bold text-white flex items-center q-gutter-x-xs">
                    <q-icon name="sym_r_date_range" color="cyan-4" size="18px" />
                    <span>Temporada / Rango Programado (Campaña)</span>
                  </div>
                  <div class="text-caption text-grey-4 q-mt-xs">
                    Aplica para campañas específicas (admisiones, cursos de verano, receso).
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div v-if="form.validity_type === 'date_range'" class="row q-col-gutter-md bg-dark-subtle q-pa-md rounded-borders q-mb-md">
          <div class="col-12 col-sm-6">
            <q-input
              v-model="form.start_date"
              type="date"
              label="Fecha de Inicio *"
              outlined
              dark
              dense
            >
              <template #prepend>
                <q-icon name="sym_r_event" color="teal-4" size="18px" />
              </template>
            </q-input>
          </div>
          <div class="col-12 col-sm-6">
            <q-input
              v-model="form.end_date"
              type="date"
              label="Fecha de Fin *"
              outlined
              dark
              dense
            >
              <template #prepend>
                <q-icon name="sym_r_event_available" color="teal-4" size="18px" />
              </template>
            </q-input>
          </div>
        </div>

        <!-- 4. Días de la Semana & Horarios de Atención (con Soporte de Turnos Mañana / Tarde) -->
        <div class="row items-center justify-between q-my-md">
          <div>
            <div class="text-subtitle1 text-bold text-white flex items-center q-gutter-x-xs">
              <q-icon name="sym_r_schedule" color="teal-4" size="20px" />
              <span>Jornadas & Turnos de Atención (Mañana / Tarde)</span>
            </div>
            <div class="text-caption text-grey-4">
              Configura los rangos horarios de cada día. Puedes definir múltiples turnos divididos para cubrir mañana y tarde.
            </div>
          </div>

          <q-btn
            outline
            dense
            color="teal-4"
            icon="sym_r_content_copy"
            label="Copiar Horario Lun-Vie"
            no-caps
            class="q-px-sm"
            @click="copyMondayToWeekdays"
          >
            <q-tooltip>Replica los turnos del Lunes a Martes, Miércoles, Jueves y Viernes</q-tooltip>
          </q-btn>
        </div>

        <!-- Lista de los 7 Días -->
        <div class="q-gutter-y-sm">
          <div
            v-for="day in weekDays"
            :key="day.key"
            class="xf-day-row q-pa-md rounded-borders"
            :class="{ 'xf-day-row--disabled': !form.schedule_days[day.key]?.enabled }"
          >
            <div class="row items-center justify-between">
              <!-- Checkbox y Nombre del Día -->
              <div class="row items-center q-gutter-x-sm" style="min-width: 160px">
                <q-checkbox
                  v-model="form.schedule_days[day.key].enabled"
                  dark
                  color="primary"
                  dense
                />
                <span class="text-body2 text-bold" :class="form.schedule_days[day.key]?.enabled ? 'text-white' : 'text-grey-5'">
                  {{ day.label }}
                </span>
              </div>

              <!-- Si está deshabilitado: Cerrado -->
              <div v-if="!form.schedule_days[day.key]?.enabled" class="text-caption text-grey-5 italic">
                Cerrado / No laborable
              </div>

              <!-- Si está habilitado: Lista de Turnos / Rangos -->
              <div v-else class="col row items-center justify-end q-gutter-x-md">
                <div class="column q-gutter-y-xs">
                  <!-- Iterar sobre los slots del día -->
                  <div
                    v-for="(slot, sIdx) in (form.schedule_days[day.key].slots || [])"
                    :key="sIdx"
                    class="row items-center q-gutter-x-xs"
                  >
                    <q-badge color="teal-10" text-color="teal-2" class="text-caption q-mr-xs">
                      {{ slot.name || `Turno ${sIdx + 1}` }}
                    </q-badge>

                    <q-input
                      v-model="slot.start_time"
                      type="time"
                      dense
                      outlined
                      dark
                      style="width: 110px"
                    />
                    <span class="text-grey-5">—</span>
                    <q-input
                      v-model="slot.end_time"
                      type="time"
                      dense
                      outlined
                      dark
                      style="width: 110px"
                    />

                    <!-- Botón para eliminar turno si hay más de 1 -->
                    <q-btn
                      v-if="(form.schedule_days[day.key].slots || []).length > 1"
                      flat
                      round
                      dense
                      size="sm"
                      icon="sym_r_close"
                      color="grey-4"
                      @click="removeSlot(day.key, sIdx)"
                    >
                      <q-tooltip>Eliminar este turno</q-tooltip>
                    </q-btn>
                  </div>

                  <!-- Si no tiene slots, mostrar start_time y end_time directos -->
                  <div v-if="!form.schedule_days[day.key].slots || form.schedule_days[day.key].slots.length === 0" class="row items-center q-gutter-x-xs">
                    <q-input
                      v-model="form.schedule_days[day.key].start_time"
                      type="time"
                      dense
                      outlined
                      dark
                      style="width: 110px"
                    />
                    <span class="text-grey-5">—</span>
                    <q-input
                      v-model="form.schedule_days[day.key].end_time"
                      type="time"
                      dense
                      outlined
                      dark
                      style="width: 110px"
                    />
                  </div>
                </div>

                <!-- Botón para agregar turno (ej: Tarde) -->
                <q-btn
                  flat
                  dense
                  no-caps
                  size="sm"
                  color="cyan-3"
                  icon="sym_r_add"
                  label="+ Turno / Rango"
                  @click="addSlot(day.key)"
                >
                  <q-tooltip>Agregar otro rango horario para este día (ej: turno tarde)</q-tooltip>
                </q-btn>

                <!-- Badge con el total de horas -->
                <q-badge color="teal-9" text-color="teal-2" class="text-caption font-mono">
                  {{ calculateDayTotalHours(form.schedule_days[day.key]) }}
                </q-badge>
              </div>
            </div>
          </div>
        </div>

        <!-- 5. Respuesta Automática Fuera de Horario -->
        <q-separator dark class="q-my-lg" />

        <div class="row items-center justify-between q-mb-sm">
          <div class="row items-center q-gutter-x-sm">
            <q-avatar size="36px" color="cyan-9" text-color="cyan-2">
              <q-icon name="sym_r_forward_to_inbox" size="20px" />
            </q-avatar>
            <div>
              <div class="text-subtitle1 text-bold text-white">
                Respuesta Automática Fuera de Horario Laboral
              </div>
              <div class="text-caption text-grey-4">
                Envía un mensaje instantáneo a los prospectos o alumnos que escriban cuando el horario está cerrado.
              </div>
            </div>
          </div>

          <div class="row items-center q-gutter-x-xs">
            <span class="text-caption" :class="form.auto_reply_enabled ? 'text-cyan-3' : 'text-grey-5'">
              {{ form.auto_reply_enabled ? 'Activada' : 'Desactivada' }}
            </span>
            <q-toggle
              v-model="form.auto_reply_enabled"
              dense
              color="cyan-4"
            />
          </div>
        </div>

        <div v-if="form.auto_reply_enabled" class="row q-col-gutter-lg q-mt-xs">
          <!-- Editor del Mensaje -->
          <div class="col-12 col-md-7 q-gutter-y-sm">
            <div class="row items-center justify-between">
              <span class="text-caption text-grey-4 text-bold">MENSAJE AUTOMÁTICO PROGRAMADO:</span>
              <q-btn
                flat
                dense
                no-caps
                size="sm"
                color="cyan-3"
                icon="sym_r_restart_alt"
                label="Restablecer mensaje oficial UNITEPC"
                @click="resetDefaultMessage"
              />
            </div>

            <q-input
              v-model="form.auto_reply_message"
              type="textarea"
              rows="4"
              outlined
              dark
              counter
              maxlength="1000"
              placeholder="Escribe el mensaje que recibirá el cliente..."
            />
          </div>

          <!-- Vista Previa de Chat WhatsApp -->
          <div class="col-12 col-md-5">
            <div class="text-caption text-grey-4 text-bold q-mb-xs">VISTA PREVIA EN WHATSAPP:</div>
            <div class="xf-whatsapp-preview q-pa-md">
              <div class="xf-chat-bubble">
                <div class="text-caption text-white" style="white-space: pre-line; line-height: 1.45;">
                  {{ form.auto_reply_message || '¡Hola! Gracias por comunicarte con nosotros...' }}
                </div>
                <div class="row justify-end items-center q-gutter-x-xs q-mt-xs">
                  <span class="text-grey-4" style="font-size: 0.68rem;">{{ currentTime }}</span>
                  <q-icon name="sym_r_done_all" size="14px" color="teal-3" />
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Botones de Acción -->
        <div class="row items-center justify-end q-gutter-x-md q-mt-xl">
          <q-btn
            v-if="isCreatingNew"
            flat
            color="grey-4"
            label="Cancelar"
            no-caps
            @click="cancelCreate"
          />

          <q-btn
            unelevated
            color="primary"
            icon="sym_r_save"
            :label="isCreatingNew ? 'Crear Parametrización de Horario' : 'Guardar Cambios de Horario'"
            no-caps
            class="xf-btn-primary q-px-xl q-py-sm text-weight-bold"
            :loading="saving"
            @click="saveCurrentConfig"
          />
        </div>
      </q-card>
    </div>
  </div>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { http } from '@/shared/api/http'
import { useAppNotify } from '@/shared/composables/useAppNotify'

const notify = useAppNotify()

const loading = ref(false)
const saving = ref(false)
const isCurrentlyOpen = ref(true)
const currentTime = ref('08:30')
const currentDayName = ref('Lunes')

const configsList = ref<any[]>([])
const operatorsList = ref<any[]>([])
const channelsList = ref<any[]>([])
const categoriesList = ref<any[]>([])
const queuesList = ref<any[]>([])
const selectedConfig = ref<any | null>(null)
const isCreatingNew = ref(false)

const defaultMessageTemplate =
  '¡Hola! Gracias por comunicarte con nosotros. En este momento nos encontramos fuera de nuestro horario de atención habitual. Tu mensaje ha sido registrado y un asesor te responderá a la brevedad tan pronto retomemos actividades. ¡Agradecemos tu paciencia!'

const weekDays = [
  { key: 'monday', label: 'Lunes' },
  { key: 'tuesday', label: 'Martes' },
  { key: 'wednesday', label: 'Miércoles' },
  { key: 'thursday', label: 'Jueves' },
  { key: 'friday', label: 'Viernes' },
  { key: 'saturday', label: 'Sábado' },
  { key: 'sunday', label: 'Domingo' },
]

const timezoneOptions = [
  'America/La_Paz',
  'America/Lima',
  'America/Santiago',
  'America/Bogota',
  'America/Buenos_Aires',
  'America/Mexico_City',
  'America/Sao_Paulo',
  'UTC',
]

interface DayScheduleSlot {
  name: string
  start_time: string
  end_time: string
  enabled: boolean
}

interface DaySchedule {
  enabled: boolean
  start_time: string
  end_time: string
  slots: DayScheduleSlot[]
}

type WeekScheduleDays = Record<string, DaySchedule>

function defaultDaysStructure(): WeekScheduleDays {
  return {
    monday: {
      enabled: true,
      start_time: '07:45',
      end_time: '18:30',
      slots: [
        { name: 'Mañana', start_time: '07:45', end_time: '12:30', enabled: true },
        { name: 'Tarde', start_time: '14:00', end_time: '18:30', enabled: true },
      ],
    },
    tuesday: {
      enabled: true,
      start_time: '07:45',
      end_time: '18:30',
      slots: [
        { name: 'Mañana', start_time: '07:45', end_time: '12:30', enabled: true },
        { name: 'Tarde', start_time: '14:00', end_time: '18:30', enabled: true },
      ],
    },
    wednesday: {
      enabled: true,
      start_time: '07:45',
      end_time: '18:30',
      slots: [
        { name: 'Mañana', start_time: '07:45', end_time: '12:30', enabled: true },
        { name: 'Tarde', start_time: '14:00', end_time: '18:30', enabled: true },
      ],
    },
    thursday: {
      enabled: true,
      start_time: '07:45',
      end_time: '18:30',
      slots: [
        { name: 'Mañana', start_time: '07:45', end_time: '12:30', enabled: true },
        { name: 'Tarde', start_time: '14:00', end_time: '18:30', enabled: true },
      ],
    },
    friday: {
      enabled: true,
      start_time: '07:45',
      end_time: '18:30',
      slots: [
        { name: 'Mañana', start_time: '07:45', end_time: '12:30', enabled: true },
        { name: 'Tarde', start_time: '14:00', end_time: '18:30', enabled: true },
      ],
    },
    saturday: {
      enabled: true,
      start_time: '08:15',
      end_time: '12:30',
      slots: [{ name: 'Mañana', start_time: '08:15', end_time: '12:30', enabled: true }],
    },
    sunday: {
      enabled: false,
      start_time: '09:00',
      end_time: '13:00',
      slots: [] as DayScheduleSlot[],
    },
  }
}

const form = reactive({
  id: '' as string,
  name: 'Horario General UNITEPC',
  scope_type: 'general' as 'general' | 'operator',
  user_ids: [] as string[],
  channel_ids: [] as string[],
  category_ids: [] as string[],
  queue_ids: [] as string[],
  is_active: true,
  is_default: true,
  validity_type: 'immediate' as 'immediate' | 'date_range',
  start_date: null as string | null,
  end_date: null as string | null,
  timezone: 'America/La_Paz',
  schedule_days: defaultDaysStructure() as WeekScheduleDays,
  auto_reply_enabled: true,
  auto_reply_message: defaultMessageTemplate,
})

async function fetchConfigs() {
  loading.value = true
  try {
    const res = await http.get('/business-hours/configs')
    configsList.value = res.data.data || []
    operatorsList.value = res.data.operators || []
    channelsList.value = res.data.channels || []
    categoriesList.value = res.data.categories || []
    queuesList.value = res.data.queues || []

    if (res.data.meta) {
      currentTime.value = res.data.meta.current_time || '08:30'
      currentDayName.value = translateDay(res.data.meta.current_day)
    }

    if (configsList.value.length > 0) {
      // Si no hay seleccionado o el seleccionado ya no existe, elegir el primero (default)
      const target = configsList.value.find((c) => c.id === selectedConfig.value?.id) || configsList.value[0]
      selectConfig(target)
    }
  } catch {
    notify.error({ message: 'Error al cargar las configuraciones de horario.' })
  } finally {
    loading.value = false
  }
}

function selectConfig(cfg: any) {
  isCreatingNew.value = false
  selectedConfig.value = cfg

  form.id = cfg.id
  form.name = cfg.name
  form.scope_type = cfg.scope_type || 'general'
  form.user_ids = (cfg.users || []).map((u: any) => u.id)
  form.channel_ids = (cfg.channels || []).map((c: any) => c.id)
  form.category_ids = (cfg.categories || []).map((c: any) => c.id)
  form.queue_ids = (cfg.queues || []).map((q: any) => q.id)
  form.is_active = cfg.is_active ?? true
  form.is_default = cfg.is_default ?? false
  form.validity_type = cfg.validity_type || 'immediate'
  form.start_date = cfg.start_date ? cfg.start_date.split('T')[0] : null
  form.end_date = cfg.end_date ? cfg.end_date.split('T')[0] : null
  form.timezone = cfg.timezone || 'America/La_Paz'
  form.auto_reply_enabled = cfg.auto_reply_enabled ?? true
  form.auto_reply_message = cfg.auto_reply_message || defaultMessageTemplate

  // Normalizar schedule_days asegurando slots
  const mergedDays = { ...defaultDaysStructure(), ...(cfg.schedule_days || {}) }
  for (const dayKey of Object.keys(mergedDays)) {
    const dayObj = mergedDays[dayKey as keyof typeof mergedDays]
    if (dayObj && !dayObj.slots) {
      dayObj.slots = dayObj.enabled
        ? [{ name: 'Continuo', start_time: dayObj.start_time || '07:45', end_time: dayObj.end_time || '18:30', enabled: true }]
        : []
    }
  }
  form.schedule_days = mergedDays
}

function openNewConfigForm() {
  isCreatingNew.value = true
  selectedConfig.value = null

  form.id = ''
  form.name = 'Nuevo Turno / Guardia'
  form.scope_type = 'general'
  form.user_ids = []
  form.channel_ids = channelsList.value.map((c: any) => c.id)
  form.category_ids = []
  form.queue_ids = []
  form.is_active = true
  form.is_default = false
  form.validity_type = 'immediate'
  form.start_date = null
  form.end_date = null
  form.timezone = 'America/La_Paz'
  form.schedule_days = defaultDaysStructure()
  form.auto_reply_enabled = true
  form.auto_reply_message = defaultMessageTemplate
}

function cancelCreate() {
  if (configsList.value.length > 0) {
    selectConfig(configsList.value[0])
  }
}

function addSlot(dayKey: string) {
  const day = form.schedule_days[dayKey as keyof typeof form.schedule_days]
  if (!day) return
  if (!day.slots) day.slots = []

  const nextName = day.slots.length === 0 ? 'Mañana' : day.slots.length === 1 ? 'Tarde' : `Turno ${day.slots.length + 1}`
  const defaultStart = day.slots.length === 1 ? '14:00' : '07:45'
  const defaultEnd = day.slots.length === 1 ? '18:30' : '12:30'

  day.slots.push({
    name: nextName,
    start_time: defaultStart,
    end_time: defaultEnd,
    enabled: true,
  })
}

function removeSlot(dayKey: string, index: number) {
  const day = form.schedule_days[dayKey as keyof typeof form.schedule_days]
  if (day && day.slots) {
    day.slots.splice(index, 1)
  }
}

function calculateDayTotalHours(dayObj: any): string {
  if (!dayObj || !dayObj.enabled) return 'Cerrado'
  let totalMins = 0

  if (dayObj.slots && dayObj.slots.length > 0) {
    for (const slot of dayObj.slots) {
      if (slot.start_time && slot.end_time) {
        const [sh, sm] = slot.start_time.split(':').map(Number)
        const [eh, em] = slot.end_time.split(':').map(Number)
        let diff = eh * 60 + em - (sh * 60 + sm)
        if (diff > 0) totalMins += diff
      }
    }
  } else if (dayObj.start_time && dayObj.end_time) {
    const [sh, sm] = dayObj.start_time.split(':').map(Number)
    const [eh, em] = dayObj.end_time.split(':').map(Number)
    let diff = eh * 60 + em - (sh * 60 + sm)
    if (diff > 0) totalMins += diff
  }

  const h = Math.floor(totalMins / 60)
  const m = totalMins % 60
  return m > 0 ? `${h}h ${m}m atención` : `${h}h atención`
}

function copyMondayToWeekdays() {
  const mon = form.schedule_days.monday
  if (!mon) return
  ;['tuesday', 'wednesday', 'thursday', 'friday'].forEach((k) => {
    form.schedule_days[k as keyof typeof form.schedule_days] = JSON.parse(JSON.stringify(mon))
  })
  notify.info({ message: 'Horario y turnos de Lunes replicados de Martes a Viernes.' })
}

function resetDefaultMessage() {
  form.auto_reply_message = defaultMessageTemplate
  notify.info({ message: 'Mensaje restaurado al estándar institucional de UNITEPC.' })
}

function selectAllChannels() {
  form.channel_ids = channelsList.value.map((c) => c.id)
}

function clearChannels() {
  form.channel_ids = []
}

function selectAllOperators() {
  form.user_ids = operatorsList.value.map((o) => o.id)
}

function clearOperators() {
  form.user_ids = []
}

function getChannelIcon(channel: any): string {
  const type = channel?.session_type || channel?.provider || ''
  if (type === 'facebook') return 'sym_r_public'
  if (type === 'instagram') return 'sym_r_photo_camera'
  if (type === 'tiktok') return 'sym_r_play_circle'
  return 'sym_r_chat'
}

function getChannelIconColor(channel: any): string {
  const type = channel?.session_type || channel?.provider || ''
  if (type === 'facebook') return 'indigo-4'
  if (type === 'instagram') return 'pink-4'
  if (type === 'tiktok') return 'cyan-4'
  return 'teal-4'
}

async function saveCurrentConfig() {
  if (!form.name.trim()) {
    notify.warning({ message: 'Por favor ingresa un nombre para esta parametrización.' })
    return
  }

  if (form.validity_type === 'date_range' && (!form.start_date || !form.end_date)) {
    notify.warning({ message: 'Por favor define la fecha de inicio y fin para la campaña/temporada.' })
    return
  }

  saving.value = true
  try {
    const payload = {
      name: form.name.trim(),
      scope_type: form.scope_type,
      user_ids: form.user_ids,
      channel_ids: form.channel_ids,
      category_ids: form.category_ids,
      queue_ids: form.queue_ids,
      is_active: form.is_active,
      is_default: form.scope_type === 'general' ? form.is_default : false,
      validity_type: form.validity_type,
      start_date: form.validity_type === 'date_range' ? form.start_date : null,
      end_date: form.validity_type === 'date_range' ? form.end_date : null,
      timezone: form.timezone,
      schedule_days: form.schedule_days,
      auto_reply_enabled: form.auto_reply_enabled,
      auto_reply_message: form.auto_reply_message.trim(),
    }

    if (isCreatingNew.value) {
      const res = await http.post('/business-hours/configs', payload)
      notify.success({ message: 'Nueva parametrización de horario creada exitosamente.' })
      await fetchConfigs()
      selectConfig(res.data.data)
    } else {
      const res = await http.put(`/business-hours/configs/${form.id}`, payload)
      notify.success({ message: 'Parametrización de horario actualizada exitosamente.' })
      await fetchConfigs()
      selectConfig(res.data.data)
    }
  } catch (err: any) {
    notify.error({ message: err?.response?.data?.message || 'Error al guardar horario laboral.' })
  } finally {
    saving.value = false
  }
}

async function toggleCurrentActive() {
  if (!form.id) return
  try {
    const res = await http.patch(`/business-hours/configs/${form.id}/toggle`)
    form.is_active = res.data.data.is_active
    notify.success({ message: res.data.message })
    await fetchConfigs()
  } catch {
    notify.error({ message: 'No se pudo alternar el estado del horario.' })
  }
}

async function confirmDeleteConfig() {
  if (!confirm(`¿Estás seguro de eliminar la parametrización "${form.name}"?`)) {
    return
  }

  try {
    await http.delete(`/business-hours/configs/${form.id}`)
    notify.success({ message: 'Parametrización eliminada correctamente.' })
    selectedConfig.value = null
    await fetchConfigs()
  } catch (err: any) {
    notify.error({ message: err?.response?.data?.message || 'Error al eliminar configuración.' })
  }
}

function translateDay(day?: string): string {
  switch (day) {
    case 'monday': return 'Lunes'
    case 'tuesday': return 'Martes'
    case 'wednesday': return 'Miércoles'
    case 'thursday': return 'Jueves'
    case 'friday': return 'Viernes'
    case 'saturday': return 'Sábado'
    case 'sunday': return 'Domingo'
    default: return 'Hoy'
  }
}

onMounted(() => {
  fetchConfigs()
})
</script>

<style scoped lang="scss">
.business-hours-section {
  width: 100%;
}

.xf-status-banner {
  border-radius: 12px;
  transition: all 0.2s ease;

  &--open {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.12) 0%, rgba(17, 24, 39, 0.7) 100%);
    border: 1px solid rgba(16, 185, 129, 0.35);
  }

  &--closed {
    background: linear-gradient(135deg, rgba(245, 158, 11, 0.12) 0%, rgba(17, 24, 39, 0.7) 100%);
    border: 1px solid rgba(245, 158, 11, 0.35);
  }
}

.xf-panel-card {
  background: var(--crm-bg-card, #111827);
  border: 1px solid var(--crm-color-border, rgba(255, 255, 255, 0.08));
  border-radius: 14px;
}

.xf-config-pill {
  background: rgba(255, 255, 255, 0.04);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 9999px;
  padding: 6px 14px;
  transition: all 0.15s ease;

  &:hover {
    background: rgba(255, 255, 255, 0.08);
    border-color: rgba(16, 185, 129, 0.3);
  }

  &--active {
    background: rgba(16, 185, 129, 0.15);
    border-color: #10b981;
    box-shadow: 0 0 10px rgba(16, 185, 129, 0.2);
  }
}

.xf-validity-option {
  background: rgba(255, 255, 255, 0.03);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 10px;
  padding: 14px;
  transition: all 0.2s ease;

  &:hover {
    background: rgba(255, 255, 255, 0.05);
    border-color: rgba(16, 185, 129, 0.3);
  }

  &--selected {
    background: rgba(16, 185, 129, 0.08);
    border-color: rgba(16, 185, 129, 0.5);
  }
}

.bg-dark-subtle {
  background: rgba(0, 0, 0, 0.25);
}

.border-bottom-subtle {
  border-bottom: 1px solid rgba(255, 255, 255, 0.07);
}

.xf-day-row {
  background: rgba(255, 255, 255, 0.02);
  border: 1px solid rgba(255, 255, 255, 0.05);
  transition: background 0.15s ease;

  &:hover {
    background: rgba(255, 255, 255, 0.035);
  }

  &--disabled {
    opacity: 0.6;
  }
}

.xf-whatsapp-preview {
  background: #0b141a;
  border-radius: 12px;
  border: 1px solid rgba(255, 255, 255, 0.08);
  min-height: 150px;
  display: flex;
  align-items: center;
  justify-content: flex-end;
}

.xf-chat-bubble {
  background: #005c4b;
  border-radius: 8px 0px 8px 8px;
  padding: 10px 12px;
  max-width: 90%;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
}

.xf-btn-primary {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%) !important;
  border-radius: 8px;
}
</style>
