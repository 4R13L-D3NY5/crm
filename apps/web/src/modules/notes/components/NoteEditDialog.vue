<template>
  <q-dialog
    v-model="isOpen"
    persistent
    maximized-mobile
    transition-show="fade"
    transition-hide="fade"
  >
    <q-card class="xf-note-dialog-card column no-wrap" style="width: 720px; max-width: 95vw; max-height: 90vh;">
      <!-- Dialog Header -->
      <q-card-section class="xf-note-dialog__header row items-center justify-between q-py-sm q-px-md">
        <div class="row items-center q-gutter-x-sm">
          <q-avatar size="32px" :style="{ backgroundColor: form.color || '#1e293b' }">
            <q-icon name="sym_r_sticky_note_2" size="18px" color="white" />
          </q-avatar>
          <div>
            <div class="text-subtitle1 text-weight-bold text-white">
              {{ isEditing ? 'Editar Nota' : 'Nueva Nota de Conocimiento' }}
            </div>
            <div class="text-caption text-grey-4" style="font-size: 0.72rem">
              <span v-if="props.note?.user?.name || props.note?.author_name" class="text-grey-3">
                Registrado por: <strong class="text-teal-3">{{ props.note?.user?.name || props.note?.author_name }}</strong> • 
              </span>
              Organiza micro-conocimiento, guías y respuestas para el equipo
            </div>
          </div>
        </div>

        <div class="row items-center q-gutter-x-xs">
          <!-- Toggle Pin Rápido -->
          <q-btn
            flat
            round
            dense
            size="sm"
            :color="form.is_pinned ? 'amber-4' : 'grey-5'"
            icon="sym_r_push_pin"
            @click="form.is_pinned = !form.is_pinned"
          >
            <q-tooltip>{{ form.is_pinned ? 'Nota fijada en la parte superior' : 'Fijar en la parte superior' }}</q-tooltip>
          </q-btn>

          <!-- Cerrar Modal -->
          <q-btn
            flat
            round
            dense
            size="sm"
            color="grey-4"
            icon="sym_r_close"
            v-close-popup
          />
        </div>
      </q-card-section>

      <q-separator dark />

      <!-- Dialog Body con scroll garantizado -->
      <q-card-section class="xf-note-dialog__body col scroll q-pa-md q-gutter-y-md">
        <!-- Título -->
        <q-input
          v-model="form.title"
          outlined
          dense
          label="Título de la nota (opcional)"
          placeholder="Ej: Requisitos de Inscripción - Medicina 2026"
          class="xf-input-field"
        >
          <template #prepend>
            <q-icon name="sym_r_title" size="18px" color="teal-3" />
          </template>
        </q-input>

        <!-- Barra de Herramientas de Inteligencia Artificial y Detección -->
        <div class="ai-toolbar row items-center justify-between q-pa-sm rounded-borders">
          <div class="row items-center q-gutter-x-xs">
            <!-- Botón Asistente IA -->
            <q-btn
              unelevated
              dense
              size="sm"
              color="amber-8"
              text-color="dark"
              icon="sym_r_auto_awesome"
              label="Asistente IA"
              class="q-px-sm text-weight-bold"
              :loading="isRefining"
            >
              <q-tooltip>Revisar ortografía, sintetizar o adaptar el tono con IA</q-tooltip>
              <q-menu anchor="bottom start" self="top start" class="ai-menu-dropdown">
                <q-list dense style="min-width: 220px;">
                  <q-item-label header class="text-caption text-weight-bold text-amber-5">
                    ✨ Herramientas de IA
                  </q-item-label>

                  <q-item clickable v-close-popup @click="handleAiRefine('spellcheck')">
                    <q-item-section avatar style="min-width: 28px;">
                      <q-icon name="sym_r_spellcheck" size="18px" color="teal-4" />
                    </q-item-section>
                    <q-item-section>
                      <q-item-label>Corregir Ortografía</q-item-label>
                      <q-item-label caption>Puntuación y gramática en español</q-item-label>
                    </q-item-section>
                  </q-item>

                  <q-item clickable v-close-popup @click="handleAiRefine('summarize')">
                    <q-item-section avatar style="min-width: 28px;">
                      <q-icon name="sym_r_short_text" size="18px" color="light-blue-4" />
                    </q-item-section>
                    <q-item-section>
                      <q-item-label>Sintetizar / Resumir</q-item-label>
                      <q-item-label caption>Hacer más ágil y conciso</q-item-label>
                    </q-item-section>
                  </q-item>

                  <q-item clickable v-close-popup @click="handleAiRefine('tone_formal')">
                    <q-item-section avatar style="min-width: 28px;">
                      <q-icon name="sym_r_business_center" size="18px" color="indigo-4" />
                    </q-item-section>
                    <q-item-section>
                      <q-item-label>Tono Formal</q-item-label>
                      <q-item-label caption>Institucional y respetuoso</q-item-label>
                    </q-item-section>
                  </q-item>

                  <q-item clickable v-close-popup @click="handleAiRefine('tone_persuasive')">
                    <q-item-section avatar style="min-width: 28px;">
                      <q-icon name="sym_r_trending_up" size="18px" color="purple-4" />
                    </q-item-section>
                    <q-item-section>
                      <q-item-label>Tono Persuasivo</q-item-label>
                      <q-item-label caption>Orientado a ventas y captación</q-item-label>
                    </q-item-section>
                  </q-item>
                </q-list>
              </q-menu>
            </q-btn>

            <!-- Botón Detección de Entidades -->
            <q-btn
              unelevated
              dense
              size="sm"
              color="teal-8"
              icon="sym_r_troubleshoot"
              label="Escanear Entidades"
              class="q-px-sm"
              :loading="isAnalyzing"
              :disable="!form.content.trim()"
              @click="scanEntities"
            >
              <q-tooltip>Detectar si el texto menciona Categorías, Estados de Lead o Etiquetas</q-tooltip>
            </q-btn>

            <!-- Botón Buscar Similares con IA -->
            <q-btn
              flat
              dense
              size="sm"
              color="amber-4"
              icon="sym_r_saved_search"
              label="Buscar Similares"
              class="q-px-sm"
              :disable="!form.content.trim() || form.content.trim().length < 3"
              @click="isSimilarDraftOpen = true"
            >
              <q-tooltip>Compara este borrador con la base de conocimiento para detectar notas afines o duplicadas</q-tooltip>
            </q-btn>
          </div>

          <div class="text-caption text-grey-4 font-mono" style="font-size: 0.72rem">
            {{ form.content.length }} caracteres
          </div>
        </div>

        <!-- Alerta de Entidades Detectadas con Opción de Enlazar -->
        <div v-if="detectedResult && detectedResult.total_detected > 0" class="detected-box q-pa-sm rounded-borders">
          <div class="row items-center justify-between q-mb-xs">
            <span class="text-caption text-weight-bold text-teal-3 row items-center q-gutter-x-xs">
              <q-icon name="sym_r_check_circle" size="16px" color="teal-3" />
              <span>Entidades detectadas en el texto ({{ detectedResult.total_detected }}):</span>
            </span>
            <q-btn
              flat
              dense
              size="xs"
              color="teal-3"
              label="Vincular todo con 1 clic"
              @click="linkAllDetected"
            />
          </div>

          <div class="row items-center q-gutter-xs">
            <q-chip
              v-for="cat in detectedResult.detected_categories"
              :key="cat.id"
              clickable
              dense
              size="sm"
              :color="isCategorySelected(cat.id) ? 'positive' : 'grey-9'"
              :text-color="isCategorySelected(cat.id) ? 'white' : 'teal-3'"
              icon="sym_r_school"
              @click="toggleDetectedCategory(cat.id)"
            >
              {{ cat.name }}
              <q-tooltip>{{ isCategorySelected(cat.id) ? 'Ya vinculada' : 'Haz clic para vincular esta categoría' }}</q-tooltip>
            </q-chip>

            <q-chip
              v-for="st in detectedResult.detected_custom_statuses"
              :key="st.id"
              clickable
              dense
              size="sm"
              :color="isStatusSelected(st.id) ? 'positive' : 'grey-9'"
              :text-color="isStatusSelected(st.id) ? 'white' : 'blue-3'"
              icon="sym_r_flag"
              @click="toggleDetectedStatus(st.id)"
            >
              {{ st.name }}
              <q-tooltip>{{ isStatusSelected(st.id) ? 'Ya vinculado' : 'Haz clic para vincular este estado' }}</q-tooltip>
            </q-chip>

            <q-chip
              v-for="tg in detectedResult.detected_tags"
              :key="tg.id"
              clickable
              dense
              size="sm"
              :color="isTagSelected(tg.id) ? 'positive' : 'grey-9'"
              :text-color="isTagSelected(tg.id) ? 'white' : 'green-3'"
              icon="sym_r_label"
              @click="toggleDetectedTag(tg.id)"
            >
              {{ tg.name }}
              <q-tooltip>{{ isTagSelected(tg.id) ? 'Ya vinculada' : 'Haz clic para vincular esta etiqueta' }}</q-tooltip>
            </q-chip>
          </div>
        </div>

        <!-- Contenido de la Nota -->
        <q-input
          v-model="form.content"
          type="textarea"
          outlined
          autogrow
          :rows="6"
          label="Contenido de la nota *"
          placeholder="Escribe el apunte, instrucciones, costos, requisitos o respuesta predefinida..."
          class="xf-input-field"
        />

        <!-- Atajo & Visibilidad -->
        <div class="row q-col-gutter-sm">
          <div class="col-12 col-sm-6">
            <q-input
              v-model="form.shortcut"
              outlined
              dense
              label="Atajo rápido (#)"
              placeholder="Ej: medicina, requisitos, pagos"
              prefix="#"
              class="xf-input-field"
            >
              <template #append>
                <q-tooltip>Escribe #{{ form.shortcut || 'atajo' }} en el chat para insertarla al instante</q-tooltip>
              </template>
            </q-input>
          </div>

          <div class="col-12 col-sm-6">
            <q-select
              v-model="form.visibility"
              outlined
              dense
              emit-value
              map-options
              :options="visibilityOptions"
              label="Visibilidad de la Nota"
              class="xf-input-field"
            >
              <template #prepend>
                <q-icon name="sym_r_visibility" size="18px" color="teal-3" />
              </template>
            </q-select>
          </div>
        </div>

        <!-- Paleta de Colores Keep -->
        <div>
          <div class="text-caption text-weight-bold text-grey-4 q-mb-xs">Color de fondo</div>
          <div class="row items-center q-gutter-sm">
            <button
              v-for="color in colorPalette"
              :key="color.value"
              type="button"
              class="color-circle-btn"
              :style="{ backgroundColor: color.value }"
              :class="{ 'color-circle-btn--active': form.color === color.value }"
              @click="form.color = color.value"
            >
              <q-icon
                v-if="form.color === color.value"
                name="sym_r_check"
                size="14px"
                color="white"
              />
              <q-tooltip>{{ color.label }}</q-tooltip>
            </button>
          </div>
        </div>

        <!-- Sección de Clasificación: Sugerencias Inteligentes & Selectores Manuales -->
        <div class="q-pt-xs">
          <!-- Cabecera de Clasificación con Botones Sin IA e IA -->
          <div class="row items-center justify-between q-mb-xs">
            <div class="row items-center q-gutter-x-xs">
              <q-icon name="sym_r_loyalty" size="16px" color="teal-3" />
              <span class="text-caption text-weight-bold text-grey-3">Categorías y Etiquetas de la Nota</span>
              <span class="text-caption text-grey-5 font-mono hidden-xs" style="font-size: 0.72rem">(Selección manual y asistida)</span>
            </div>

            <!-- Botones de Detección Sin IA y Con IA -->
            <div class="row items-center q-gutter-x-xs">
              <q-btn
                outline
                dense
                no-caps
                size="xs"
                color="teal-3"
                icon="sym_r_manage_search"
                label="Detectar (Sin IA)"
                class="q-px-sm"
                :loading="isDetectingHeuristic"
                :disable="!hasContentToDetect"
                @click="handleDetectEntities('heuristic')"
              >
                <q-tooltip class="bg-dark text-white">
                  Buscar coincidencias léxicas exactas de categorías y etiquetas en el texto (sin usar créditos de IA)
                </q-tooltip>
              </q-btn>

              <q-btn
                unelevated
                dense
                no-caps
                size="xs"
                color="purple-8"
                icon="sym_r_auto_awesome"
                label="Sugerir con IA"
                class="q-px-sm text-weight-bold"
                :loading="isSuggestingAi"
                :disable="!hasContentToDetect"
                @click="handleDetectEntities('ai')"
              >
                <q-tooltip class="bg-dark text-white">
                  Clasificar semánticamente con IA y sugerir las categorías y etiquetas más idóneas
                </q-tooltip>
              </q-btn>
            </div>
          </div>

          <!-- Panel de Sugerencias Detectadas (Checkboxes individuales + 1-clic) -->
          <div
            v-if="suggestedEntities && suggestedEntities.total > 0"
            class="suggestions-panel q-pa-sm q-mb-sm rounded-borders"
            :class="suggestedEntities.source === 'ia' ? 'suggestions-panel--ia' : 'suggestions-panel--heuristic'"
          >
            <div class="row items-center justify-between q-mb-xs">
              <div class="row items-center q-gutter-x-xs text-caption">
                <q-icon
                  :name="suggestedEntities.source === 'ia' ? 'sym_r_auto_awesome' : 'sym_r_manage_search'"
                  size="15px"
                  :color="suggestedEntities.source === 'ia' ? 'purple-3' : 'teal-3'"
                />
                <span class="text-weight-bold" :class="suggestedEntities.source === 'ia' ? 'text-purple-3' : 'text-teal-3'">
                  {{ suggestedEntities.source === 'ia' ? 'Sugerencias con IA' : 'Entidades Detectadas sin IA' }} ({{ suggestedEntities.total }}):
                </span>
                <span v-if="suggestedEntities.reason" class="text-grey-4 text-caption italic hidden-xs" style="font-size: 0.72rem">
                  — {{ suggestedEntities.reason }}
                </span>
              </div>

              <!-- Botón 1-clic para vincular todo o badge de completado -->
              <div class="row items-center q-gutter-x-xs">
                <q-btn
                  v-if="!areAllSuggestionsLinked"
                  unelevated
                  dense
                  size="xs"
                  color="teal-8"
                  text-color="white"
                  icon="sym_r_done_all"
                  label="Vincular todo con 1 clic"
                  class="text-weight-bold q-px-xs"
                  @click="linkAllSuggestions"
                />
                <q-badge v-else color="positive" class="text-caption font-mono q-px-xs">
                  <q-icon name="sym_r_check" size="12px" class="q-mr-xs" />
                  Todas vinculadas
                </q-badge>

                <q-btn
                  flat
                  dense
                  round
                  size="xs"
                  color="grey-4"
                  icon="sym_r_close"
                  @click="suggestedEntities = null"
                >
                  <q-tooltip>Cerrar sugerencias</q-tooltip>
                </q-btn>
              </div>
            </div>

            <!-- Checkable Chips / Checkboxes interactivos -->
            <div class="row items-center q-gutter-xs wrap q-pt-xs">
              <!-- Categorías sugeridas -->
              <q-chip
                v-for="cat in suggestedEntities.categories"
                :key="'sug-cat-' + cat.id"
                clickable
                dense
                size="sm"
                :color="isCategorySelected(cat.id) ? 'teal-9' : 'dark'"
                :text-color="isCategorySelected(cat.id) ? 'teal-1' : 'grey-3'"
                :style="isCategorySelected(cat.id) ? 'border: 1px solid #10b981;' : 'border: 1px solid rgba(255,255,255,0.15);'"
                class="cursor-pointer transition-all q-mr-none"
                @click="toggleDetectedCategory(cat.id)"
              >
                <q-icon
                  :name="isCategorySelected(cat.id) ? 'sym_r_check_box' : 'sym_r_check_box_outline_blank'"
                  size="14px"
                  :color="isCategorySelected(cat.id) ? 'teal-3' : 'grey-5'"
                  class="q-mr-xs"
                />
                <span class="text-caption text-weight-medium">{{ cat.display_path || cat.name }}</span>
                <q-tooltip class="bg-dark text-white font-mono text-caption">{{ cat.full_path || cat.display_path || cat.name }}</q-tooltip>
              </q-chip>

              <!-- Estados sugeridos -->
              <q-chip
                v-for="st in suggestedEntities.custom_statuses"
                :key="'sug-st-' + st.id"
                clickable
                dense
                size="sm"
                :color="isStatusSelected(st.id) ? 'blue-9' : 'dark'"
                :text-color="isStatusSelected(st.id) ? 'blue-1' : 'grey-3'"
                :style="isStatusSelected(st.id) ? 'border: 1px solid #3b82f6;' : 'border: 1px solid rgba(255,255,255,0.15);'"
                class="cursor-pointer transition-all q-mr-none"
                @click="toggleDetectedStatus(st.id)"
              >
                <q-icon
                  :name="isStatusSelected(st.id) ? 'sym_r_check_box' : 'sym_r_check_box_outline_blank'"
                  size="14px"
                  :color="isStatusSelected(st.id) ? 'blue-3' : 'grey-5'"
                  class="q-mr-xs"
                />
                <span class="text-caption text-weight-medium">{{ st.name }}</span>
                <q-tooltip>{{ isStatusSelected(st.id) ? 'Estado vinculado (haz clic para desmarcar)' : 'Haz clic para marcar y vincular este estado' }}</q-tooltip>
              </q-chip>

              <!-- Etiquetas sugeridas -->
              <q-chip
                v-for="tg in suggestedEntities.tags"
                :key="'sug-tg-' + tg.id"
                clickable
                dense
                size="sm"
                :color="isTagSelected(tg.id) ? 'green-9' : 'dark'"
                :text-color="isTagSelected(tg.id) ? 'green-1' : 'grey-3'"
                :style="isTagSelected(tg.id) ? 'border: 1px solid #22c55e;' : 'border: 1px solid rgba(255,255,255,0.15);'"
                class="cursor-pointer transition-all q-mr-none"
                @click="toggleDetectedTag(tg.id)"
              >
                <q-icon
                  :name="isTagSelected(tg.id) ? 'sym_r_check_box' : 'sym_r_check_box_outline_blank'"
                  size="14px"
                  :color="isTagSelected(tg.id) ? 'green-3' : 'grey-5'"
                  class="q-mr-xs"
                />
                <span class="text-caption text-weight-medium">#{{ tg.name }}</span>
                <q-tooltip>{{ isTagSelected(tg.id) ? 'Etiqueta vinculada (haz clic para desmarcar)' : 'Haz clic para marcar y vincular esta etiqueta' }}</q-tooltip>
              </q-chip>
            </div>
          </div>
        </div>

        <!-- Selectores de Metadatos de Enlace (Apartado Manual Preservado) -->
        <div class="row q-col-gutter-sm">
          <!-- Selector de Categorías -->
          <div class="col-12 col-sm-4">
            <q-select
              v-model="form.category_ids"
              multiple
              dense
              outlined
              clearable
              emit-value
              map-options
              use-input
              input-debounce="0"
              option-value="id"
              option-label="name"
              :options="filteredCategoriesOptions"
              label="Categorías / Subcategorías"
              class="xf-input-field"
              @filter="filterCategories"
            >
              <template #selected>
                <div v-if="selectedCategoriesList.length > 0" class="row items-center no-wrap ellipsis q-gutter-x-xs full-width" style="line-height: normal;">
                  <q-badge color="teal-8" text-color="white" class="font-mono text-weight-bold q-px-xs" style="font-size: 0.72rem;">
                    {{ selectedCategoriesList.length }}
                  </q-badge>
                  <span class="text-caption text-white ellipsis text-weight-medium">
                    {{ selectedCategoriesList[0]?.name }}
                    <span v-if="selectedCategoriesList.length > 1" class="text-teal-3 text-weight-bold q-ml-xs">
                      (+{{ selectedCategoriesList.length - 1 }} más)
                    </span>
                  </span>
                  <q-tooltip class="bg-dark text-white font-mono text-caption">
                    <div class="text-weight-bold q-mb-xs">Categorías seleccionadas ({{ selectedCategoriesList.length }}):</div>
                    <div v-for="c in selectedCategoriesList" :key="c.id" class="q-py-xs">
                      • {{ c.display_path || c.name }}
                    </div>
                  </q-tooltip>
                </div>
              </template>
              <template #option="{ itemProps, opt, selected, toggleOption }">
                <q-item v-bind="itemProps" dense dark class="cursor-pointer q-py-xs" @click="toggleOption(opt)">
                  <q-item-section side class="q-pr-xs">
                    <q-checkbox :model-value="selected" dense dark @update:model-value="toggleOption(opt)" />
                  </q-item-section>
                  <q-item-section avatar style="min-width: 22px" class="q-pr-xs">
                    <span class="filter-dot" :style="{ backgroundColor: opt.color || '#10b981' }"></span>
                  </q-item-section>
                  <q-item-section>
                    <q-item-label class="text-body2 text-white">{{ opt.name }}</q-item-label>
                    <q-item-label caption class="text-teal-3 font-mono text-caption">{{ opt.display_path }}</q-item-label>
                  </q-item-section>
                </q-item>
              </template>
              <template #no-option>
                <q-item dark>
                  <q-item-section class="text-grey-5 text-caption text-center q-pa-sm">
                    No se encontraron categorías
                  </q-item-section>
                </q-item>
              </template>
            </q-select>
          </div>

          <!-- Estados Personalizados -->
          <div class="col-12 col-sm-4">
            <q-select
              v-model="form.custom_status_ids"
              multiple
              dense
              outlined
              clearable
              emit-value
              map-options
              option-value="id"
              option-label="name"
              :options="statusesOptions"
              label="Estados de Lead"
              class="xf-input-field"
            >
              <template #selected>
                <div v-if="selectedStatusesList.length > 0" class="row items-center no-wrap ellipsis q-gutter-x-xs full-width" style="line-height: normal;">
                  <q-badge color="blue-8" text-color="white" class="font-mono text-weight-bold q-px-xs" style="font-size: 0.72rem;">
                    {{ selectedStatusesList.length }}
                  </q-badge>
                  <span class="text-caption text-white ellipsis text-weight-medium">
                    {{ selectedStatusesList[0]?.name }}
                    <span v-if="selectedStatusesList.length > 1" class="text-blue-3 text-weight-bold q-ml-xs">
                      (+{{ selectedStatusesList.length - 1 }} más)
                    </span>
                  </span>
                </div>
              </template>
              <template #option="{ itemProps, opt, selected, toggleOption }">
                <q-item v-bind="itemProps" dense dark class="cursor-pointer q-py-xs" @click="toggleOption(opt)">
                  <q-item-section side class="q-pr-xs">
                    <q-checkbox :model-value="selected" dense dark @update:model-value="toggleOption(opt)" />
                  </q-item-section>
                  <q-item-section avatar style="min-width: 22px" class="q-pr-xs">
                    <span class="filter-dot" :style="{ backgroundColor: opt.color || '#3b82f6' }"></span>
                  </q-item-section>
                  <q-item-section>
                    <q-item-label class="text-body2 text-white">{{ opt.name }}</q-item-label>
                  </q-item-section>
                </q-item>
              </template>
            </q-select>
          </div>

          <!-- Etiquetas -->
          <div class="col-12 col-sm-4">
            <q-select
              v-model="form.tag_ids"
              multiple
              dense
              outlined
              clearable
              emit-value
              map-options
              option-value="id"
              option-label="name"
              :options="tagsOptions"
              label="Etiquetas"
              class="xf-input-field"
            >
              <template #selected>
                <div v-if="selectedTagsList.length > 0" class="row items-center no-wrap ellipsis q-gutter-x-xs full-width" style="line-height: normal;">
                  <q-badge color="green-8" text-color="white" class="font-mono text-weight-bold q-px-xs" style="font-size: 0.72rem;">
                    {{ selectedTagsList.length }}
                  </q-badge>
                  <span class="text-caption text-white ellipsis text-weight-medium">
                    #{{ selectedTagsList[0]?.name }}
                    <span v-if="selectedTagsList.length > 1" class="text-green-3 text-weight-bold q-ml-xs">
                      (+{{ selectedTagsList.length - 1 }} más)
                    </span>
                  </span>
                </div>
              </template>
              <template #option="{ itemProps, opt, selected, toggleOption }">
                <q-item v-bind="itemProps" dense dark class="cursor-pointer q-py-xs" @click="toggleOption(opt)">
                  <q-item-section side class="q-pr-xs">
                    <q-checkbox :model-value="selected" dense dark @update:model-value="toggleOption(opt)" />
                  </q-item-section>
                  <q-item-section>
                    <q-item-label class="text-body2 text-white">#{{ opt.name }}</q-item-label>
                  </q-item-section>
                </q-item>
              </template>
            </q-select>
          </div>
        </div>

        <!-- Panel de Entidades Vinculadas a la Nota (Chips elegantes con ancho completo y sin desbordes) -->
        <div
          v-if="hasAnyLinkedEntities"
          class="linked-entities-panel q-mt-sm q-pa-sm rounded-borders"
        >
          <!-- Categorías Vinculadas -->
          <div v-if="selectedCategoriesList.length > 0" class="q-mb-xs">
            <div class="row items-center justify-between q-mb-xs">
              <span class="text-caption text-weight-bold text-teal-3 row items-center">
                <q-icon name="sym_r_school" size="14px" class="q-mr-xs" />
                Categorías vinculadas ({{ selectedCategoriesList.length }}):
              </span>
              <q-btn
                v-if="selectedCategoriesList.length > 1"
                flat
                dense
                size="xs"
                color="grey-4"
                label="Desvincular todas"
                class="text-caption text-weight-regular"
                @click="form.category_ids = []"
              >
                <q-tooltip>Quitar todas las categorías seleccionadas</q-tooltip>
              </q-btn>
            </div>
            <div class="row items-center q-gutter-xs wrap linked-chips-scroll">
              <q-chip
                v-for="cat in selectedCategoriesList"
                :key="'active-cat-' + cat.id"
                removable
                dense
                size="sm"
                class="linked-chip q-mr-none"
                :style="{ backgroundColor: cat.color ? cat.color + '22' : 'rgba(16,185,129,0.15)', borderColor: cat.color || '#10b981' }"
                @remove="toggleDetectedCategory(cat.id)"
              >
                <q-avatar size="14px" :style="{ backgroundColor: cat.color || '#10b981' }">
                  <q-icon name="sym_r_school" size="10px" color="white" />
                </q-avatar>
                <span class="text-caption text-white text-weight-medium q-mx-xs linked-chip__text">
                  {{ cat.display_path || cat.name }}
                </span>
                <q-tooltip class="bg-dark text-white font-mono text-caption">{{ cat.full_path || cat.display_path || cat.name }}</q-tooltip>
              </q-chip>
            </div>
          </div>

          <!-- Estados Vinculados -->
          <div v-if="selectedStatusesList.length > 0" class="q-mt-xs q-mb-xs">
            <div class="row items-center justify-between q-mb-xs">
              <span class="text-caption text-weight-bold text-blue-3 row items-center">
                <q-icon name="sym_r_flag" size="14px" class="q-mr-xs" />
                Estados vinculados ({{ selectedStatusesList.length }}):
              </span>
              <q-btn
                v-if="selectedStatusesList.length > 1"
                flat
                dense
                size="xs"
                color="grey-4"
                label="Desvincular todos"
                class="text-caption text-weight-regular"
                @click="form.custom_status_ids = []"
              />
            </div>
            <div class="row items-center q-gutter-xs wrap">
              <q-chip
                v-for="st in selectedStatusesList"
                :key="'active-st-' + st.id"
                removable
                dense
                size="sm"
                class="linked-chip q-mr-none"
                :style="{ backgroundColor: st.color ? st.color + '22' : 'rgba(59,130,246,0.15)', borderColor: st.color || '#3b82f6' }"
                @remove="toggleDetectedStatus(st.id)"
              >
                <q-avatar size="14px" :style="{ backgroundColor: st.color || '#3b82f6' }">
                  <q-icon name="sym_r_flag" size="10px" color="white" />
                </q-avatar>
                <span class="text-caption text-white text-weight-medium q-mx-xs">
                  {{ st.name }}
                </span>
              </q-chip>
            </div>
          </div>

          <!-- Etiquetas Vinculadas -->
          <div v-if="selectedTagsList.length > 0" class="q-mt-xs">
            <div class="row items-center justify-between q-mb-xs">
              <span class="text-caption text-weight-bold text-green-3 row items-center">
                <q-icon name="sym_r_tag" size="14px" class="q-mr-xs" />
                Etiquetas vinculadas ({{ selectedTagsList.length }}):
              </span>
              <q-btn
                v-if="selectedTagsList.length > 1"
                flat
                dense
                size="xs"
                color="grey-4"
                label="Desvincular todas"
                class="text-caption text-weight-regular"
                @click="form.tag_ids = []"
              />
            </div>
            <div class="row items-center q-gutter-xs wrap">
              <q-chip
                v-for="tg in selectedTagsList"
                :key="'active-tg-' + tg.id"
                removable
                dense
                size="sm"
                class="linked-chip q-mr-none"
                :style="{ backgroundColor: tg.color ? tg.color + '22' : 'rgba(34,197,94,0.15)', borderColor: tg.color || '#22c55e' }"
                @remove="toggleDetectedTag(tg.id)"
              >
                <q-icon name="sym_r_tag" size="12px" color="green-4" class="q-mr-xs" />
                <span class="text-caption text-white text-weight-medium">
                  #{{ tg.name }}
                </span>
              </q-chip>
            </div>
          </div>
        </div>
      </q-card-section>

      <q-separator dark />

      <!-- Dialog Footer anclado -->
      <q-card-actions align="between" class="xf-note-dialog__footer q-px-md q-py-sm">
        <div class="row items-center q-gutter-x-sm">
          <q-checkbox
            v-model="form.is_archived"
            dense
            label="Archivar nota"
            class="text-caption text-grey-4"
          />
        </div>

        <div class="row items-center q-gutter-x-sm">
          <q-btn
            flat
            dense
            color="grey-4"
            label="Cancelar"
            v-close-popup
          />

          <q-btn
            unelevated
            dense
            color="primary"
            icon="sym_r_save"
            :label="isEditing ? 'Actualizar Nota' : 'Guardar Nota'"
            class="q-px-md text-weight-bold"
            :loading="isSaving"
            :disable="!form.content.trim()"
            @click="handleSubmit"
          />
        </div>
      </q-card-actions>
    </q-card>

    <!-- Submodal de Confirmación de Cambios de IA (Antes vs Después) -->
    <q-dialog v-model="showAiDiffModal" persistent>
      <q-card style="width: 600px; max-width: 95vw;" class="ai-diff-card">
        <q-card-section class="row items-center justify-between q-py-sm">
          <div class="row items-center q-gutter-x-sm">
            <q-avatar size="28px" color="amber-8" text-color="dark">
              <q-icon name="sym_r_auto_awesome" size="16px" />
            </q-avatar>
            <div class="text-subtitle2 text-weight-bold text-white">
              Sugerencia de IA — {{ aiRefineTitle }}
            </div>
          </div>
          <q-btn flat round dense size="sm" icon="sym_r_close" color="grey-4" v-close-popup />
        </q-card-section>

        <q-separator dark />

        <q-card-section class="q-gutter-y-sm scroll" style="max-height: 60vh;">
          <div class="text-caption text-grey-4">
            Revisa el texto propuesto antes de aplicar los cambios a la nota:
          </div>

          <div class="diff-block">
            <div class="text-caption text-weight-bold text-grey-5 q-mb-xs">Texto Actual:</div>
            <div class="diff-text original-text">{{ form.content }}</div>
          </div>

          <div class="diff-block">
            <div class="text-caption text-weight-bold text-amber-4 q-mb-xs">Propuesta Mejorada con IA:</div>
            <div class="diff-text refined-text">{{ aiRefinedCandidate }}</div>
          </div>
        </q-card-section>

        <q-separator dark />

        <q-card-actions align="right" class="q-pa-sm">
          <q-btn flat dense color="grey-4" label="Descartar" v-close-popup />
          <q-btn
            unelevated
            dense
            color="amber-8"
            text-color="dark"
            icon="sym_r_check"
            label="Aplicar al Contenido"
            class="q-px-md text-weight-bold"
            @click="applyAiChanges"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- Modal de Notas Similares con IA -->
    <SimilarNotesDialog
      v-model="isSimilarDraftOpen"
      :note="props.note"
      :draft-content="form.content"
      :draft-title="form.title || undefined"
      @select-note="onSelectSimilarNote"
    />
  </q-dialog>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useQuasar } from 'quasar'
import { http } from '@/shared/api/http'
import { formatCategoryDisplayPath, matchesCategorySearch } from '@/modules/parameters/utils/category-formatter'
import { suggestEntities as apiSuggestEntities } from '../api/notes.api'
import { useNotes } from '../composables/useNotes'
import type { AnalyzeResult, Note, NotePayload, SuggestEntitiesResponse } from '../types/note.types'
import SimilarNotesDialog from './SimilarNotesDialog.vue'

const props = defineProps<{
  modelValue: boolean
  note?: Note | null
}>()

const emit = defineEmits<{
  'update:modelValue': [val: boolean]
  saved: [note: Note]
}>()

const $q = useQuasar()
const { saveNote, analyzeContent, refineWithAi, isSaving, isAnalyzing, isRefining } = useNotes()

const isOpen = computed({
  get: () => props.modelValue,
  set: (val) => emit('update:modelValue', val),
})

const isEditing = computed(() => !!props.note?.id)
const isSimilarDraftOpen = ref(false)

function onSelectSimilarNote(selected: Note) {
  $q.dialog({
    title: 'Nota Similar Seleccionada',
    message: `¿Deseas reemplazar el borrador actual con el contenido de "${selected.title || 'Nota seleccionada'}"?`,
    cancel: true,
    persistent: true,
    ok: {
      color: 'primary',
      label: 'Cargar Contenido',
    },
  }).onOk(() => {
    form.content = selected.content
    if (selected.title && !form.title) {
      form.title = selected.title
    }
  })
}

const colorPalette = [
  { label: 'Pizarra Oscura (Default)', value: '#1e293b' },
  { label: 'Azul Marino', value: '#1e3a5f' },
  { label: 'Verde Esmeralda', value: '#064e3b' },
  { label: 'Púrpura Místico', value: '#4c1d95' },
  { label: 'Bronce Ámbar', value: '#78350f' },
  { label: 'Rosa Carmesí', value: '#831843' },
  { label: 'Verde Azulado', value: '#134e4a' },
  { label: 'Índigo Profundo', value: '#312e81' },
]

const visibilityOptions = [
  { label: 'Visibilidad Total (Todo el equipo)', value: 'all' },
  { label: 'Solo Categorías Asignadas al Asesor', value: 'categories' },
  { label: 'Privada (Solo Administradores)', value: 'private' },
]

const form = reactive<NotePayload>({
  title: '',
  content: '',
  color: '#1e293b',
  is_pinned: false,
  is_archived: false,
  shortcut: '',
  visibility: 'all',
  category_ids: [],
  custom_status_ids: [],
  tag_ids: [],
})

const categoriesOptions = ref<Array<{ id: string; name: string }>>([])
const statusesOptions = ref<Array<{ id: string; name: string }>>([])
const tagsOptions = ref<Array<{ id: string; name: string }>>([])

const categorySearchQuery = ref('')

const formattedCategoriesOptions = computed(() => {
  return categoriesOptions.value.map((c: any) => {
    const displayPath = formatCategoryDisplayPath(c, categoriesOptions.value)
    return {
      ...c,
      label: displayPath,
      name: c.name,
      display_path: displayPath,
    }
  })
})

const filteredCategoriesOptions = computed(() => {
  const q = categorySearchQuery.value.toLowerCase().trim()
  if (!q) return formattedCategoriesOptions.value
  return formattedCategoriesOptions.value.filter((c: any) =>
    matchesCategorySearch(c, q, c.display_path)
  )
})

function filterCategories(val: string, update: (fn: () => void) => void) {
  update(() => {
    categorySearchQuery.value = val
  })
}

const selectedCategoriesList = computed(() => {
  if (!form.category_ids || form.category_ids.length === 0) return []
  const map = new Map(formattedCategoriesOptions.value.map((c: any) => [c.id, c]))
  const noteCatsMap = new Map((props.note?.categories || []).map((c: any) => [c.id, c]))
  return form.category_ids
    .map((id) => map.get(id) || noteCatsMap.get(id) || { id, name: id })
    .filter(Boolean) as Array<any>
})

const selectedStatusesList = computed(() => {
  if (!form.custom_status_ids || form.custom_status_ids.length === 0) return []
  const map = new Map(statusesOptions.value.map((s: any) => [s.id, s]))
  const noteStMap = new Map((props.note?.custom_statuses || []).map((s: any) => [s.id, s]))
  return form.custom_status_ids
    .map((id) => map.get(id) || noteStMap.get(id) || { id, name: id })
    .filter(Boolean) as Array<any>
})

const selectedTagsList = computed(() => {
  if (!form.tag_ids || form.tag_ids.length === 0) return []
  const map = new Map(tagsOptions.value.map((t: any) => [t.id, t]))
  const noteTgMap = new Map((props.note?.tags || []).map((t: any) => [t.id, t]))
  return form.tag_ids
    .map((id) => map.get(id) || noteTgMap.get(id) || { id, name: id })
    .filter(Boolean) as Array<any>
})

const hasAnyLinkedEntities = computed(() => {
  return selectedCategoriesList.value.length > 0 ||
    selectedStatusesList.value.length > 0 ||
    selectedTagsList.value.length > 0
})

const detectedResult = ref<AnalyzeResult | null>(null)
const isDetectingHeuristic = ref(false)
const isSuggestingAi = ref(false)
const suggestedEntities = ref<SuggestEntitiesResponse | null>(null)

const hasContentToDetect = computed(() => {
  const t = (form.title || '').trim()
  const c = (form.content || '').trim()
  return (t.length + c.length) >= 2
})

const areAllSuggestionsLinked = computed(() => {
  if (!suggestedEntities.value || suggestedEntities.value.total === 0) return false
  const catsOk = suggestedEntities.value.categories.every((c) => isCategorySelected(c.id))
  const stOk = suggestedEntities.value.custom_statuses.every((s) => isStatusSelected(s.id))
  const tgOk = suggestedEntities.value.tags.every((t) => isTagSelected(t.id))
  return catsOk && stOk && tgOk
})
const showAiDiffModal = ref(false)
const aiRefinedCandidate = ref('')
const aiRefineTitle = ref('')

watch(
  () => props.modelValue,
  async (open) => {
    if (open) {
      loadCatalogOptions()
      detectedResult.value = null
      suggestedEntities.value = null

      if (props.note) {
        form.title = props.note.title || ''
        form.content = props.note.content || ''
        form.color = props.note.color || '#1e293b'
        form.is_pinned = props.note.is_pinned || false
        form.is_archived = props.note.is_archived || false
        form.shortcut = props.note.shortcut || ''
        form.visibility = props.note.visibility || 'all'
        form.category_ids = props.note.categories?.map((c) => c.id) || []
        form.custom_status_ids = props.note.custom_statuses?.map((s) => s.id) || []
        form.tag_ids = props.note.tags?.map((t) => t.id) || []
      } else {
        form.title = ''
        form.content = ''
        form.color = '#1e293b'
        form.is_pinned = false
        form.is_archived = false
        form.shortcut = ''
        form.visibility = 'all'
        form.category_ids = []
        form.custom_status_ids = []
        form.tag_ids = []
      }
    }
  },
)

async function loadCatalogOptions() {
  try {
    const [catRes, stRes, tgRes] = await Promise.all([
      http.get('/categories'),
      http.get('/custom-statuses'),
      http.get('/tags'),
    ])
    categoriesOptions.value = catRes.data?.data || []
    statusesOptions.value = stRes.data?.data || []
    tagsOptions.value = tgRes.data?.data || []
  } catch (err) {
    console.error('Error cargando catálogo para notas:', err)
  }
}

async function scanEntities() {
  if (!form.content.trim()) return
  try {
    const result = await analyzeContent(form.content)
    detectedResult.value = result
    if (result.total_detected === 0) {
      $q.notify({
        type: 'info',
        message: 'No se detectaron menciones de categorías, estados o etiquetas en el texto.',
        position: 'bottom-right',
      })
    } else {
      $q.notify({
        type: 'positive',
        message: `¡Se detectaron ${result.total_detected} entidades mencionadas en la nota!`,
        position: 'bottom-right',
      })
    }
  } catch (err: any) {
    $q.notify({
      type: 'negative',
      message: err?.response?.data?.message || 'Error al analizar el contenido',
      position: 'bottom-right',
    })
  }
}

function isCategorySelected(id: string): boolean {
  return form.category_ids?.includes(id) ?? false
}

function isStatusSelected(id: string): boolean {
  return form.custom_status_ids?.includes(id) ?? false
}

function isTagSelected(id: string): boolean {
  return form.tag_ids?.includes(id) ?? false
}

function toggleDetectedCategory(id: string) {
  if (!form.category_ids) form.category_ids = []
  const idx = form.category_ids.indexOf(id)
  if (idx === -1) {
    form.category_ids.push(id)
  } else {
    form.category_ids.splice(idx, 1)
  }
}

function toggleDetectedStatus(id: string) {
  if (!form.custom_status_ids) form.custom_status_ids = []
  const idx = form.custom_status_ids.indexOf(id)
  if (idx === -1) {
    form.custom_status_ids.push(id)
  } else {
    form.custom_status_ids.splice(idx, 1)
  }
}

function toggleDetectedTag(id: string) {
  if (!form.tag_ids) form.tag_ids = []
  const idx = form.tag_ids.indexOf(id)
  if (idx === -1) {
    form.tag_ids.push(id)
  } else {
    form.tag_ids.splice(idx, 1)
  }
}

function linkAllDetected() {
  if (!detectedResult.value) return
  if (!form.category_ids) form.category_ids = []
  if (!form.custom_status_ids) form.custom_status_ids = []
  if (!form.tag_ids) form.tag_ids = []

  detectedResult.value.detected_categories.forEach((cat) => {
    if (!form.category_ids!.includes(cat.id)) form.category_ids!.push(cat.id)
  })
  detectedResult.value.detected_custom_statuses.forEach((st) => {
    if (!form.custom_status_ids!.includes(st.id)) form.custom_status_ids!.push(st.id)
  })
  detectedResult.value.detected_tags.forEach((tg) => {
    if (!form.tag_ids!.includes(tg.id)) form.tag_ids!.push(tg.id)
  })

  $q.notify({
    type: 'positive',
    message: 'Todas las entidades detectadas han sido vinculadas a la nota.',
    position: 'bottom-right',
  })
}

async function handleDetectEntities(mode: 'heuristic' | 'ai') {
  if (!hasContentToDetect.value) return
  if (mode === 'ai') {
    isSuggestingAi.value = true
  } else {
    isDetectingHeuristic.value = true
  }

  try {
    const res = await apiSuggestEntities({
      title: form.title || undefined,
      content: form.content,
      mode,
    })
    suggestedEntities.value = res
    if (res.total === 0) {
      $q.notify({
        type: 'info',
        message: mode === 'ai'
          ? 'La IA no encontró categorías ni etiquetas afines en el catálogo disponible.'
          : 'No se detectaron menciones exactas de categorías o etiquetas en el texto.',
        position: 'bottom-right',
      })
    } else {
      $q.notify({
        type: 'positive',
        message: mode === 'ai'
          ? `¡La IA sugirió ${res.total} clasificaciones para tu nota!`
          : `¡Se detectaron ${res.total} entidades por coincidencia de texto!`,
        position: 'bottom-right',
      })
    }
  } catch (err: any) {
    console.error('Error al detectar entidades:', err)
    $q.notify({
      type: 'negative',
      message: err?.response?.data?.message || 'Error al sugerir clasificaciones',
      position: 'bottom-right',
    })
  } finally {
    isDetectingHeuristic.value = false
    isSuggestingAi.value = false
  }
}

function linkAllSuggestions() {
  if (!suggestedEntities.value) return
  if (!form.category_ids) form.category_ids = []
  if (!form.custom_status_ids) form.custom_status_ids = []
  if (!form.tag_ids) form.tag_ids = []

  suggestedEntities.value.categories.forEach((cat) => {
    if (!form.category_ids!.includes(cat.id)) form.category_ids!.push(cat.id)
  })
  suggestedEntities.value.custom_statuses.forEach((st) => {
    if (!form.custom_status_ids!.includes(st.id)) form.custom_status_ids!.push(st.id)
  })
  suggestedEntities.value.tags.forEach((tg) => {
    if (!form.tag_ids!.includes(tg.id)) form.tag_ids!.push(tg.id)
  })

  $q.notify({
    type: 'positive',
    message: '¡Todas las entidades sugeridas han sido vinculadas a la nota!',
    position: 'bottom-right',
  })
}

async function handleAiRefine(action: 'spellcheck' | 'summarize' | 'tone_formal' | 'tone_persuasive') {
  if (!form.content.trim()) return

  const actionTitles: Record<string, string> = {
    spellcheck: 'Corrección Ortográfica y Estilo',
    summarize: 'Síntesis y Resumen',
    tone_formal: 'Adaptación a Tono Formal',
    tone_persuasive: 'Adaptación a Tono Persuasivo',
  }
  aiRefineTitle.value = actionTitles[action] || 'Ajuste con IA'

  try {
    const res = await refineWithAi({
      content: form.content,
      action,
    })
    aiRefinedCandidate.value = res.refined_content
    showAiDiffModal.value = true
  } catch (err: any) {
    $q.notify({
      type: 'negative',
      message: err?.response?.data?.message || 'Error al comunicarse con el motor de IA',
      position: 'bottom-right',
    })
  }
}

function applyAiChanges() {
  form.content = aiRefinedCandidate.value
  showAiDiffModal.value = false
  $q.notify({
    type: 'positive',
    message: 'Cambios de IA aplicados al contenido de la nota.',
    position: 'bottom-right',
  })
}

async function handleSubmit() {
  if (!form.content.trim()) return
  try {
    const saved = await saveNote(form, props.note?.id)
    if (saved) {
      $q.notify({
        type: 'positive',
        message: isEditing.value ? 'Nota actualizada correctamente' : 'Nota guardada con éxito',
        position: 'bottom-right',
      })
      emit('saved', saved)
      isOpen.value = false
    }
  } catch (err: any) {
    $q.notify({
      type: 'negative',
      message: err?.response?.data?.message || 'Error al guardar la nota',
      position: 'bottom-right',
    })
  }
}
</script>

<style scoped lang="scss">
.xf-note-dialog-card {
  background: #0f172a;
  border: 1px solid rgba(255, 255, 255, 0.12);
  border-radius: 16px;
  overflow: hidden;
}

.xf-note-dialog__header {
  background: rgba(15, 23, 42, 0.95);
}

.ai-toolbar {
  background: rgba(30, 41, 59, 0.7);
  border: 1px solid rgba(255, 255, 255, 0.08);
}

.detected-box {
  background: rgba(6, 78, 59, 0.25);
  border: 1px solid rgba(16, 185, 129, 0.3);
}

.color-circle-btn {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  border: 2px solid transparent;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s ease;

  &:hover {
    transform: scale(1.15);
  }

  &--active {
    border-color: #ffffff;
    box-shadow: 0 0 10px rgba(255, 255, 255, 0.5);
  }
}

.ai-diff-card {
  background: #0f172a;
  border: 1px solid rgba(251, 191, 36, 0.3);
  border-radius: 14px;
}

.diff-block {
  background: rgba(30, 41, 59, 0.6);
  border-radius: 8px;
  padding: 10px 12px;
  border: 1px solid rgba(255, 255, 255, 0.08);
}

.diff-text {
  font-size: 0.88rem;
  line-height: 1.5;
  white-space: pre-wrap;
  word-break: break-word;

  &.original-text {
    color: #94a3b8;
  }

  &.refined-text {
    color: #fef08a;
  }
}

.suggestions-panel {
  border-radius: 10px;
  transition: all 0.2s ease;
  
  &--heuristic {
    background: rgba(13, 148, 136, 0.12);
    border: 1px solid rgba(20, 184, 166, 0.35);
  }

  &--ia {
    background: rgba(147, 51, 234, 0.12);
    border: 1px solid rgba(168, 85, 247, 0.35);
  }
}

.chip--checked {
  box-shadow: 0 0 6px rgba(16, 185, 129, 0.4);
}

.linked-entities-panel {
  background: rgba(15, 23, 42, 0.65);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: 10px;
}

.linked-chips-scroll {
  max-height: 140px;
  overflow-y: auto;
  padding-right: 4px;
}

.linked-chip {
  background: rgba(15, 23, 42, 0.8) !important;
  border: 1px solid;
  transition: all 0.15s ease;

  &:hover {
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.4);
    transform: translateY(-1px);
  }

  &__text {
    max-width: 360px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    display: inline-block;
    vertical-align: middle;
  }
}
</style>
