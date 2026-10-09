<template>
  <div class="xf-parameters-page">
    <!-- Header -->
    <div class="row items-center justify-between q-mb-lg">
      <div>
        <h1 class="text-h5 text-bold text-white q-my-none">Parametrización & Catálogos</h1>
        <p class="text-caption text-grey-4 q-mt-xs q-mb-none">
          Configuración dinámica de taxonomía de leads (categorías, facultades, carreras, sedes con dependencias cruzadas) y estados del sistema.
        </p>
      </div>

      <div class="row items-center q-gutter-sm">
        <q-btn
          v-if="activeTab === 'categories' && categories.length === 0"
          outline
          color="teal-4"
          icon="sym_r_school"
          label="Cargar Plantilla Universitaria"
          no-caps
          :loading="seeding"
          @click="seedAcademicTemplate"
        >
          <q-tooltip>Carga automática de ejemplo: Oferta Académica (Ingenierías, Salud) y Sedes con carreras compartidas</q-tooltip>
        </q-btn>

        <q-btn
          v-if="activeTab === 'categories'"
          label="+ Nueva Categoría Raíz"
          unelevated
          no-caps
          class="xf-btn-primary"
          @click="openCreateDialog(null)"
        />
      </div>
    </div>

    <!-- Pestañas Principales: Categorías | Estados -->
    <div class="xf-subnav-tabs q-mb-lg">
      <button
        class="xf-subnav-btn"
        :class="{ 'xf-subnav-btn--active': activeTab === 'categories' }"
        @click="activeTab = 'categories'"
      >
        <q-icon name="sym_r_account_tree" size="18px" />
        <span>Categorías & Subcategorías (Leads)</span>
      </button>

      <button
        class="xf-subnav-btn"
        :class="{ 'xf-subnav-btn--active': activeTab === 'statuses' }"
        @click="activeTab = 'statuses'"
      >
        <q-icon name="sym_r_toggle_on" size="18px" />
        <span>Estados Personalizados</span>
      </button>
    </div>

    <!-- TAB 1: CATEGORÍAS & SUBCATEGORÍAS -->
    <div v-if="activeTab === 'categories'">
      <!-- Barra de Filtros y Acción Rápida -->
      <div class="row items-center justify-between q-col-gutter-sm q-mb-md">
        <div class="col-12 col-sm-6 row items-center q-gutter-sm">
          <q-input
            v-model="search"
            dense
            outlined
            dark
            placeholder="Buscar por nombre, código o carrera..."
            class="xf-filter-input"
            clearable
          >
            <template #prepend>
              <q-icon name="sym_r_search" size="18px" color="grey-5" />
            </template>
          </q-input>
        </div>

        <div class="col-12 col-sm-6 row justify-end items-center q-gutter-sm">
          <q-btn
            flat
            dense
            no-caps
            color="grey-4"
            icon="sym_r_refresh"
            label="Actualizar"
            @click="fetchCategories"
          />
        </div>
      </div>

      <!-- Estado Vacío -->
      <q-card v-if="!loading && categories.length === 0" flat bordered class="xf-empty-card q-pa-xl text-center">
        <q-icon name="sym_r_account_tree" size="56px" color="teal-4" class="q-mb-md" />
        <div class="text-h6 text-white text-bold">No hay categorías configuradas</div>
        <p class="text-caption text-grey-4 q-mt-sm" style="max-width: 520px; margin: 8px auto 20px">
          Puedes crear una estructura multinivel a la medida (por ejemplo: Universidad &gt; Facultad &gt; Carrera, y Sedes &gt; Sede La Paz &gt; Carrera compartida), o cargar la plantilla académica base en 1 clic.
        </p>
        <div class="row justify-center q-gutter-sm">
          <q-btn
            unelevated
            class="xf-btn-primary"
            icon="sym_r_school"
            label="Cargar Plantilla Universitaria (UNITEPC)"
            no-caps
            :loading="seeding"
            @click="seedAcademicTemplate"
          />
          <q-btn
            outline
            color="teal-4"
            icon="sym_r_add"
            label="Crear Primera Categoría"
            no-caps
            @click="openCreateDialog(null)"
          />
        </div>
      </q-card>

      <!-- Vista de Árbol de Categorías -->
      <div v-else class="q-gutter-y-md">
        <div v-if="loading" class="text-center q-py-xl">
          <q-spinner color="primary" size="40px" />
        </div>

        <div v-else class="row q-col-gutter-md">
          <div
            v-for="rootNode in filteredTree"
            :key="rootNode.id"
            class="col-12 col-md-6"
          >
            <q-card flat bordered class="xf-tree-card">
              <!-- Cabecera de Categoría Raíz -->
              <div class="xf-tree-card-header q-pa-md row items-center justify-between">
                <div class="row items-center q-gutter-x-sm">
                  <q-avatar size="34px" :style="{ backgroundColor: rootNode.color || '#10b981' }" text-color="white">
                    <q-icon :name="rootNode.icon || 'sym_r_folder'" size="19px" />
                  </q-avatar>
                  <div>
                    <div class="row items-center q-gutter-x-xs">
                      <q-badge
                        v-if="rootNode.code"
                        outline
                        color="teal-3"
                        class="font-mono text-weight-bold q-px-xs text-caption"
                        style="font-size: 0.72rem"
                      >
                        {{ rootNode.code }}
                      </q-badge>
                      <span class="text-subtitle1 text-bold text-white">{{ rootNode.name }}</span>
                    </div>
                    <div class="text-caption text-grey-4">
                      {{ (rootNode.children || []).length }} subcategorías directas
                    </div>
                  </div>
                </div>

                <div class="row items-center q-gutter-xs">
                  <q-btn
                    flat
                    round
                    dense
                    icon="sym_r_add"
                    color="teal-4"
                    size="sm"
                    @click="openCreateDialog(rootNode.id)"
                  >
                    <q-tooltip>Agregar nueva subcategoría a {{ rootNode.name }}</q-tooltip>
                  </q-btn>
                  <q-btn
                    flat
                    round
                    dense
                    icon="sym_r_link"
                    color="purple-3"
                    size="sm"
                    @click="openLinkDialog(rootNode)"
                  >
                    <q-tooltip>Vincular / Reutilizar categoría existente bajo {{ rootNode.name }}</q-tooltip>
                  </q-btn>
                  <q-btn
                    flat
                    round
                    dense
                    icon="sym_r_edit"
                    color="grey-4"
                    size="sm"
                    @click="openEditDialog(rootNode)"
                  >
                    <q-tooltip>Editar categoría raíz</q-tooltip>
                  </q-btn>
                  <q-btn
                    flat
                    round
                    dense
                    icon="sym_r_delete"
                    color="negative"
                    size="sm"
                    @click="handleDeleteNode({ node: rootNode })"
                  >
                    <q-tooltip>Eliminar rama completa</q-tooltip>
                  </q-btn>
                </div>
              </div>

              <q-separator dark style="border-color: var(--crm-color-border)" />

              <!-- Lista de Nodos Hijos Recursivos -->
              <div class="q-pa-md q-gutter-y-xs">
                <div v-if="!rootNode.children || rootNode.children.length === 0" class="text-caption text-grey-5 italic q-pa-sm">
                  Sin subcategorías aún. Haz clic en '+' para agregar o '🔗' para reutilizar.
                </div>

                <CategoryTreeNode
                  v-for="subNode in rootNode.children"
                  :key="subNode.id"
                  :node="subNode"
                  :level="2"
                  :current-parent-id="rootNode.id"
                  @add-child="openCreateDialog($event)"
                  @link-existing="openLinkDialog($event)"
                  @edit="openEditDialog($event)"
                  @delete="handleDeleteNode($event)"
                />
              </div>
            </q-card>
          </div>
        </div>
      </div>
    </div>

    <!-- TAB 2: ESTADOS PERSONALIZADOS -->
    <div v-else-if="activeTab === 'statuses'" class="q-gutter-y-lg" style="max-width: 900px">
      <q-banner rounded class="xf-status-banner q-pa-md">
        <template #avatar>
          <q-icon name="sym_r_tune" color="teal-4" size="28px" />
        </template>
        <div class="text-subtitle2 text-bold text-white">
          🔧 Módulo de Estados Personalizados — Estructura Base Lista
        </div>
        <div class="text-caption text-grey-4 q-mt-xs">
          Esta pestaña aloja las especificaciones de estados del sistema para clasificar tickets y etapas de atención omnicanal.
        </div>
      </q-banner>

      <!-- Acciones de Cabecera Tab Estados -->
      <div class="row items-center justify-between q-mb-md">
        <div>
          <div class="text-subtitle1 text-bold text-white">Estados del Ciclo de Vida del Lead</div>
          <div class="text-caption text-grey-4">
            Define las etapas comerciales y qué estados son requisito previo para avanzar hacia otro.
          </div>
        </div>

        <div class="row items-center q-gutter-sm">
          <q-btn
            v-if="statuses.length === 0"
            outline
            color="teal-4"
            icon="sym_r_school"
            label="Cargar Flujo Universitario Simplificado"
            no-caps
            :loading="seedingStatus"
            @click="seedAcademicStatuses"
          >
            <q-tooltip>Carga: No Contactado ➔ Contactado ➔ Interesado ➔ Inscrito / Descartado</q-tooltip>
          </q-btn>

          <q-btn
            label="+ Nuevo Estado"
            unelevated
            no-caps
            class="xf-btn-primary"
            @click="openCreateStatusDialog"
          />
        </div>
      </div>

      <!-- Estado Vacío -->
      <q-card v-if="!loadingStatuses && statuses.length === 0" flat bordered class="xf-empty-card q-pa-xl text-center">
        <q-icon name="sym_r_toggle_on" size="56px" color="teal-4" class="q-mb-md" />
        <div class="text-h6 text-white text-bold">No hay estados personalizados configurados</div>
        <p class="text-caption text-grey-4 q-mt-sm" style="max-width: 520px; margin: 8px auto 20px">
          Puedes crear tus propios estados y definir sus reglas de dependencia (ej: "Inscrito" solo se permite si antes fue "Contactado"), o cargar el flujo base universitario con 1 clic.
        </p>
        <div class="row justify-center q-gutter-sm">
          <q-btn
            unelevated
            class="xf-btn-primary"
            icon="sym_r_school"
            label="Cargar Flujo Universitario Simplificado"
            no-caps
            :loading="seedingStatus"
            @click="seedAcademicStatuses"
          />
          <q-btn
            outline
            color="teal-4"
            icon="sym_r_add"
            label="Crear Primer Estado"
            no-caps
            @click="openCreateStatusDialog"
          />
        </div>
      </q-card>

      <!-- Grid de Estados Configurados -->
      <div v-else class="row q-col-gutter-md">
        <div
          v-for="st in statuses"
          :key="st.id"
          class="col-12 col-md-6"
        >
          <q-card flat bordered class="xf-status-node-card q-pa-md">
            <div class="row items-center justify-between q-mb-sm">
              <div class="row items-center q-gutter-x-sm">
                <span class="xf-dot-indicator-lg" :style="{ backgroundColor: st.color }"></span>
                <q-icon :name="st.icon || 'sym_r_flag'" size="20px" :style="{ color: st.color }" />
                <div>
                  <span class="text-subtitle1 text-bold text-white">{{ st.name }}</span>
                  <q-badge v-if="st.is_default" color="blue-9" class="q-ml-sm text-caption">
                    Por Defecto
                  </q-badge>
                </div>
              </div>

              <div class="row items-center q-gutter-xs">
                <q-badge :color="getStageBadgeColor(st.stage_type)" class="text-caption">
                  {{ formatStageType(st.stage_type) }}
                </q-badge>
                <q-btn
                  flat
                  round
                  dense
                  icon="sym_r_edit"
                  color="grey-4"
                  size="sm"
                  @click="openEditStatusDialog(st)"
                >
                  <q-tooltip>Editar propiedades y dependencias</q-tooltip>
                </q-btn>
                <q-btn
                  flat
                  round
                  dense
                  icon="sym_r_delete"
                  color="negative"
                  size="sm"
                  @click="deleteStatus(st)"
                >
                  <q-tooltip>Eliminar estado</q-tooltip>
                </q-btn>
              </div>
            </div>

            <q-separator dark class="q-my-sm" style="border-color: rgba(255,255,255,0.06)" />

            <!-- Regla de Dependencias -->
            <div class="q-mt-sm">
              <div class="text-caption text-grey-4 text-weight-medium q-mb-xs">
                Regla de Transición (Dependencia):
              </div>

              <div v-if="st.allowed_previous_statuses && st.allowed_previous_statuses.length > 0" class="row items-center q-gutter-xs">
                <span class="text-caption text-grey-5">Requiere provenir de:</span>
                <q-chip
                  v-for="prev in st.allowed_previous_statuses"
                  :key="prev.id"
                  dense
                  dark
                  size="sm"
                  :style="{ backgroundColor: prev.color + '22', borderColor: prev.color, border: '1px solid' }"
                >
                  <span class="xf-dot-indicator q-mr-xs" :style="{ backgroundColor: prev.color }"></span>
                  {{ prev.name }}
                </q-chip>
              </div>

              <div v-else class="text-caption text-teal-4 italic">
                ✓ Disponible desde cualquier estado (Sin restricciones previas)
              </div>
            </div>

            <!-- Footer: Chats en este estado -->
            <div class="row items-center justify-between q-mt-md pt-sm border-top-subtle">
              <span class="text-caption text-grey-5">
                Leads activos en esta etapa:
              </span>
              <q-badge color="grey-9" text-color="white" class="text-caption">
                {{ st.conversations_count || 0 }} chats
              </q-badge>
            </div>
          </q-card>
        </div>
      </div>
    </div>

    <!-- DIÁLOGO 1: CREAR / EDITAR CATEGORÍA -->
    <q-dialog v-model="isDialogOpen" persistent>
      <q-card class="xf-modal-card" style="min-width: 480px; max-width: 580px">
        <q-card-section>
          <div class="text-h6 text-white text-bold">
            {{ selectedCategory ? 'Editar Categoría / Carrera' : 'Nueva Categoría / Subcategoría' }}
          </div>
          <div class="text-caption text-grey-4">
            Configura el nombre, código identificador, dependencias jerárquicas y color único.
          </div>
        </q-card-section>

        <q-card-section class="q-gutter-y-md">
          <q-input
            v-model="form.name"
            label="Nombre de la Categoría o Carrera *"
            outlined
            dark
            dense
            placeholder="ej: Ingeniería de Sistemas, Sede La Paz..."
          />

          <!-- Código Identificador Parametrizable -->
          <q-input
            v-model="form.code"
            label="Código Identificador (opcional)"
            outlined
            dark
            dense
            placeholder="ej: SIS-101, FAC-ING, SEDE-LPZ"
            hint="Código corto para reconocer rápidamente la categoría en listados y chats"
            @update:model-value="form.code = (form.code || '').toUpperCase()"
          >
            <template #prepend>
              <q-icon name="sym_r_tag" size="18px" color="grey-5" />
            </template>
          </q-input>

          <!-- Categorías Padre (Multiselección para Grafo/Múltiples Dependencias Cruzadas) -->
          <q-select
            v-model="form.parent_ids"
            :options="parentCategoryOptions"
            emit-value
            map-options
            multiple
            use-chips
            clearable
            label="Categorías Padre (Dejar vacío para nivel raíz)"
            outlined
            dark
            dense
            hint="Puedes seleccionar múltiples ramas padre para compartir esta categoría en varias dependencias"
          >
            <template #prepend>
              <q-icon name="sym_r_subdirectory_arrow_right" size="18px" class="text-grey-5" />
            </template>
          </q-select>

          <!-- Selector de Color con Paleta Manual y Presets Disponibles -->
          <div class="q-pt-xs">
            <div class="row items-center justify-between q-mb-xs">
              <label class="text-caption text-grey-4 text-weight-medium">
                Color Identificador Único:
              </label>
              <div class="row items-center q-gutter-x-xs">
                <!-- Botón de Paleta Manual (QColor Popup) -->
                <q-btn
                  flat
                  dense
                  no-caps
                  size="sm"
                  color="teal-3"
                  icon="sym_r_palette"
                  label="Abrir Paleta Manual"
                  class="q-px-xs"
                >
                  <q-popup-proxy cover transition-show="scale" transition-hide="scale">
                    <q-color
                      v-model="form.color"
                      no-header
                      no-footer
                      default-view="palette"
                      style="width: 250px"
                    />
                  </q-popup-proxy>
                </q-btn>
              </div>
            </div>

            <!-- Fila de Presets Disponibles (Colores no registrados aún) -->
            <div class="row items-center q-gutter-xs q-mb-sm">
              <span
                v-for="c in displayedColorPresets"
                :key="c"
                class="xf-color-circle cursor-pointer"
                :style="{
                  backgroundColor: c,
                  outline: form.color.toLowerCase() === c.toLowerCase() ? '2px solid white' : 'none',
                }"
                @click="form.color = c"
              >
                <q-tooltip>{{ c }}</q-tooltip>
              </span>

              <!-- Indicador de color actual y campo Hexadecimal -->
              <div class="row items-center q-gutter-x-xs q-ml-sm">
                <span
                  class="xf-color-circle"
                  :style="{ backgroundColor: form.color, outline: '1px solid rgba(255,255,255,0.4)' }"
                ></span>
                <q-input
                  v-model="form.color"
                  dense
                  outlined
                  dark
                  style="width: 105px"
                  placeholder="#000000"
                />
              </div>
            </div>

            <!-- Advertencia / Validación de Color Único -->
            <div
              v-if="duplicateColorCategory"
              class="q-pa-xs rounded-borders bg-negative text-white text-caption row items-center q-gutter-x-xs q-mt-xs"
            >
              <q-icon name="sym_r_error" size="16px" />
              <span>
                El color <strong>{{ form.color }}</strong> ya está registrado en
                <strong>{{ duplicateColorCategory.name }}</strong>. Elige un color único.
              </span>
            </div>
            <div v-else class="text-caption text-grey-5">
              Sugerencias arriba muestran solo colores libres no registrados en la organización.
            </div>
          </div>

          <!-- Selector de Ícono -->
          <q-select
            v-model="form.icon"
            :options="iconOptions"
            emit-value
            map-options
            label="Ícono Ilustrativo"
            outlined
            dark
            dense
          >
            <template #option="scope">
              <q-item v-bind="scope.itemProps" dark dense>
                <q-item-section avatar>
                  <q-icon :name="scope.opt.value" size="18px" :style="{ color: form.color }" />
                </q-item-section>
                <q-item-section>
                  <q-item-label>{{ scope.opt.label }}</q-item-label>
                </q-item-section>
              </q-item>
            </template>
          </q-select>

          <q-toggle
            v-model="form.is_selectable"
            dark
            color="primary"
            label="Es seleccionable como etiqueta final para chats/leads"
            class="q-mt-xs"
          />
        </q-card-section>

        <q-card-actions align="right" class="q-pa-md">
          <q-btn flat label="Cancelar" color="grey-4" no-caps v-close-popup />
          <q-btn
            unelevated
            label="Guardar Categoría"
            color="primary"
            no-caps
            class="xf-btn-primary"
            :loading="saving"
            :disable="!!duplicateColorCategory"
            @click="saveCategory"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- DIÁLOGO 2: VINCULAR / REUTILIZAR CATEGORÍA EXISTENTE BAJO UNA RAMA -->
    <q-dialog v-model="isLinkDialogOpen">
      <q-card class="xf-modal-card" style="min-width: 440px">
        <q-card-section>
          <div class="row items-center q-gutter-x-sm">
            <q-icon name="sym_r_link" color="purple-3" size="24px" />
            <div class="text-h6 text-white text-bold">Vincular Categoría Existente</div>
          </div>
          <div class="text-caption text-grey-4 q-mt-xs">
            Reutiliza una categoría existente (ej: <strong>Ingeniería de Sistemas</strong>) bajo la rama
            <strong class="text-white">{{ targetParentNode?.name }}</strong> sin duplicarla en la base de datos.
          </div>
        </q-card-section>

        <q-card-section class="q-gutter-y-md">
          <q-select
            v-model="selectedCategoryToLink"
            :options="linkableCategoryOptions"
            emit-value
            map-options
            label="Selecciona la categoría a vincular"
            outlined
            dark
            dense
          >
            <template #option="scope">
              <q-item v-bind="scope.itemProps" dark dense>
                <q-item-section avatar>
                  <span class="xf-dot-indicator" :style="{ backgroundColor: scope.opt.color }"></span>
                </q-item-section>
                <q-item-section>
                  <q-item-label>
                    <q-badge v-if="scope.opt.code" outline color="teal-3" class="q-mr-xs text-caption font-mono">
                      {{ scope.opt.code }}
                    </q-badge>
                    {{ scope.opt.label }}
                  </q-item-label>
                </q-item-section>
              </q-item>
            </template>
          </q-select>
        </q-card-section>

        <q-card-actions align="right" class="q-pa-md">
          <q-btn flat label="Cancelar" color="grey-4" no-caps v-close-popup />
          <q-btn
            unelevated
            label="Vincular a esta Rama"
            color="purple"
            no-caps
            :loading="linking"
            :disable="!selectedCategoryToLink"
            @click="linkCategoryToParent"
          />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <!-- DIÁLOGO 3: CREAR / EDITAR ESTADO PERSONALIZADO -->
    <q-dialog v-model="isStatusDialogOpen" persistent>
      <q-card class="xf-modal-card" style="min-width: 480px; max-width: 580px">
        <q-card-section>
          <div class="text-h6 text-white text-bold">
            {{ selectedStatus ? 'Editar Estado de Lead' : 'Nuevo Estado de Lead' }}
          </div>
          <div class="text-caption text-grey-4">
            Configura el nombre, tipo de etapa, color y reglas de transición (dependencias).
          </div>
        </q-card-section>

        <q-card-section class="q-gutter-y-md">
          <q-input
            v-model="statusForm.name"
            label="Nombre del Estado *"
            outlined
            dark
            dense
            placeholder="ej: Contactado, Inscrito, Interesado..."
          />

          <div class="row q-col-gutter-sm">
            <div class="col-12 col-sm-6">
              <q-select
                v-model="statusForm.stage_type"
                :options="stageTypeOptions"
                emit-value
                map-options
                label="Tipo de Etapa *"
                outlined
                dark
                dense
              />
            </div>
            <div class="col-12 col-sm-6">
              <q-select
                v-model="statusForm.icon"
                :options="statusIconOptions"
                emit-value
                map-options
                label="Ícono"
                outlined
                dark
                dense
              >
                <template #option="scope">
                  <q-item v-bind="scope.itemProps" dark dense>
                    <q-item-section avatar>
                      <q-icon :name="scope.opt.value" size="18px" :style="{ color: statusForm.color }" />
                    </q-item-section>
                    <q-item-section>
                      <q-item-label>{{ scope.opt.label }}</q-item-label>
                    </q-item-section>
                  </q-item>
                </template>
              </q-select>
            </div>
          </div>

          <!-- Color Identificador -->
          <div>
            <label class="text-caption text-grey-4 q-mb-xs block">Color Identificador:</label>
            <div class="row items-center q-gutter-xs">
              <span
                v-for="c in curatedColorPresets.slice(0, 10)"
                :key="c"
                class="xf-color-circle cursor-pointer"
                :style="{ backgroundColor: c, outline: statusForm.color === c ? '2px solid white' : 'none' }"
                @click="statusForm.color = c"
              ></span>
              <q-input
                v-model="statusForm.color"
                dense
                outlined
                dark
                style="width: 110px"
                class="q-ml-sm"
              />
            </div>
          </div>

          <q-toggle
            v-model="statusForm.is_default"
            dark
            color="primary"
            label="Estado inicial por defecto al registrar o recibir un chat nuevo"
          />

          <!-- SECCIÓN: Reglas de Transición y Dependencias -->
          <div class="xf-dependency-card q-pa-md rounded-borders">
            <div class="row items-center justify-between">
              <div>
                <div class="text-subtitle2 text-bold text-white">Reglas de Dependencia</div>
                <div class="text-caption text-grey-4">¿Requiere que el lead haya pasado antes por estados específicos?</div>
              </div>
              <q-toggle
                v-model="statusForm.has_dependencies"
                dark
                color="secondary"
                dense
              />
            </div>

            <div v-if="statusForm.has_dependencies" class="q-mt-md">
              <div class="text-caption text-teal-3 text-weight-bold q-mb-xs">
                Selecciona los estados previos permitidos para avanzar a este:
              </div>
              <div v-if="otherStatusesForDependency.length === 0" class="text-caption text-grey-5 italic">
                No hay otros estados creados aún. Crea primero los estados previos.
              </div>
              <div v-else class="column q-gutter-xs">
                <q-checkbox
                  v-for="prevSt in otherStatusesForDependency"
                  :key="prevSt.id"
                  v-model="statusForm.allowed_previous_status_ids"
                  :val="prevSt.id"
                  dark
                  dense
                  class="q-py-xs"
                >
                  <span class="row items-center q-gutter-x-xs">
                    <span class="xf-dot-indicator" :style="{ backgroundColor: prevSt.color }"></span>
                    <span class="text-white">{{ prevSt.name }}</span>
                    <span class="text-caption text-grey-5">({{ formatStageType(prevSt.stage_type) }})</span>
                  </span>
                </q-checkbox>
              </div>
            </div>

            <div v-else class="text-caption text-grey-5 q-mt-sm">
              ✓ Libre: Se podrá asignar este estado desde cualquier etapa sin restricciones previas.
            </div>
          </div>
        </q-card-section>

        <q-card-actions align="right" class="q-pa-md">
          <q-btn flat label="Cancelar" color="grey-4" no-caps v-close-popup />
          <q-btn
            unelevated
            label="Guardar Estado"
            color="primary"
            no-caps
            class="xf-btn-primary"
            :loading="savingStatus"
            @click="saveStatus"
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
import CategoryTreeNode from '../components/CategoryTreeNode.vue'

const notify = useAppNotify()

const activeTab = ref<'categories' | 'statuses'>('categories')
const loading = ref(false)
const saving = ref(false)
const linking = ref(false)
const seeding = ref(false)
const search = ref('')

const categories = ref<any[]>([])
const tree = ref<any[]>([])
const registeredColors = ref<string[]>([])

const isDialogOpen = ref(false)
const selectedCategory = ref<any>(null)

// Estado para Vincular Existente
const isLinkDialogOpen = ref(false)
const targetParentNode = ref<any>(null)
const selectedCategoryToLink = ref<string | null>(null)

const form = reactive({
  name: '',
  code: '',
  parent_ids: [] as string[],
  color: '#10b981',
  icon: 'sym_r_school',
  is_selectable: true,
  sort_order: 0,
})

// Paleta base de presets vibrantes y profesionales
const curatedColorPresets = [
  '#10b981', '#06b6d4', '#3b82f6', '#6366f1', '#8b5cf6',
  '#d946ef', '#ec4899', '#f43f5e', '#f97316', '#f59e0b',
  '#eab308', '#84cc16', '#14b8a6', '#0ea5e9', '#6d28d9',
  '#be185d', '#b91c1c', '#c2410c', '#4d7c0f', '#0f766e',
]

// Opciones de colores que aún no estén registrados en la organización
const displayedColorPresets = computed(() => {
  const currentCategoryColor = selectedCategory.value?.color?.toLowerCase()
  const used = new Set(registeredColors.value.map((c) => c.toLowerCase()))

  return curatedColorPresets.filter((preset) => {
    const p = preset.toLowerCase()
    // Mostrar si no está usado, o si es el color de la categoría actual siendo editada
    return !used.has(p) || (currentCategoryColor && p === currentCategoryColor)
  })
})

// Detección de duplicado de color en tiempo real
const duplicateColorCategory = computed(() => {
  if (!form.color) return null
  const target = form.color.trim().toLowerCase()
  return categories.value.find((c) => {
    return c.color?.toLowerCase() === target && (!selectedCategory.value || c.id !== selectedCategory.value.id)
  })
})

const iconOptions = [
  { label: 'Universidad / Educación', value: 'sym_r_school' },
  { label: 'Ingeniería', value: 'sym_r_engineering' },
  { label: 'Sistemas / Código', value: 'sym_r_terminal' },
  { label: 'Electrónica / Hardware', value: 'sym_r_memory' },
  { label: 'Salud / Médico', value: 'sym_r_medical_services' },
  { label: 'Medicina / Corazón', value: 'sym_r_cardiology' },
  { label: 'Odontología', value: 'sym_r_dentistry' },
  { label: 'Sede / Ciudad', value: 'sym_r_location_city' },
  { label: 'Edificio / Campus', value: 'sym_r_apartment' },
  { label: 'Categoría General', value: 'sym_r_category' },
  { label: 'Etiqueta Comercial', value: 'sym_r_sell' },
]

const parentCategoryOptions = computed(() => {
  return categories.value
    .filter((c) => !selectedCategory.value || c.id !== selectedCategory.value.id)
    .map((c) => ({
      label: c.code ? `[${c.code}] ${c.full_path || c.name}` : (c.full_path || c.name),
      value: c.id,
    }))
})

// Opciones para vincular una categoría existente a targetParentNode
const linkableCategoryOptions = computed(() => {
  if (!targetParentNode.value) return []
  const parentId = targetParentNode.value.id

  return categories.value
    .filter((c) => {
      // No puede ser el mismo padre ni ya ser hija de este padre
      if (c.id === parentId) return false
      if (c.parent_ids?.includes(parentId)) return false
      return true
    })
    .map((c) => ({
      label: c.name,
      code: c.code,
      color: c.color,
      value: c.id,
    }))
})

const filteredTree = computed(() => {
  if (!search.value) return tree.value
  const q = search.value.toLowerCase()

  function matchNode(node: any): boolean {
    if (node.name.toLowerCase().includes(q)) return true
    if (node.code && node.code.toLowerCase().includes(q)) return true
    if (node.children?.some((child: any) => matchNode(child))) return true
    return false
  }

  return tree.value.filter((node) => matchNode(node))
})

async function fetchCategories() {
  loading.value = true
  try {
    const res = await http.get('/categories')
    categories.value = res.data.data || []
    tree.value = res.data.tree || []
    registeredColors.value = res.data.used_colors || categories.value.map((c) => c.color).filter(Boolean)
  } catch (error) {
    notify.error('Error al cargar categorías')
  } finally {
    loading.value = false
  }
}

function openCreateDialog(parentId: string | null = null) {
  selectedCategory.value = null
  form.name = ''
  form.code = ''
  form.parent_ids = parentId ? [parentId] : []

  // Sugerir primer color disponible que no esté en uso
  const firstAvailable = displayedColorPresets.value[0] || '#10b981'
  form.color = firstAvailable
  form.icon = parentId ? 'sym_r_subdirectory_arrow_right' : 'sym_r_school'
  form.is_selectable = true
  form.sort_order = 0
  isDialogOpen.value = true
}

function openEditDialog(cat: any) {
  selectedCategory.value = cat
  form.name = cat.name
  form.code = cat.code || ''
  form.parent_ids = cat.parent_ids ? [...cat.parent_ids] : (cat.parent_id ? [cat.parent_id] : [])
  form.color = cat.color || '#10b981'
  form.icon = cat.icon || 'sym_r_school'
  form.is_selectable = cat.is_selectable ?? true
  form.sort_order = cat.sort_order || 0
  isDialogOpen.value = true
}

function openLinkDialog(parentNode: any) {
  targetParentNode.value = parentNode
  selectedCategoryToLink.value = null
  isLinkDialogOpen.value = true
}

async function linkCategoryToParent() {
  if (!selectedCategoryToLink.value || !targetParentNode.value) return

  linking.value = true
  try {
    await http.post(`/categories/${selectedCategoryToLink.value}/link-parent`, {
      parent_id: targetParentNode.value.id,
    })
    notify.success('Categoría vinculada exitosamente a la rama')
    isLinkDialogOpen.value = false
    await fetchCategories()
  } catch (error: any) {
    notify.error(error.response?.data?.message || 'Error al vincular categoría')
  } finally {
    linking.value = false
  }
}

async function saveCategory() {
  if (!form.name.trim()) {
    notify.warning('El nombre es obligatorio')
    return
  }

  if (duplicateColorCategory.value) {
    notify.error('Debes elegir un color único. Este color ya pertenece a otra categoría.')
    return
  }

  saving.value = true
  try {
    const payload = {
      name: form.name.trim(),
      code: form.code ? form.code.trim().toUpperCase() : null,
      parent_ids: form.parent_ids,
      color: form.color.trim().toLowerCase(),
      icon: form.icon,
      is_selectable: form.is_selectable,
      sort_order: form.sort_order,
    }

    if (selectedCategory.value) {
      await http.put(`/categories/${selectedCategory.value.id}`, payload)
      notify.success('Categoría actualizada exitosamente')
    } else {
      await http.post('/categories', payload)
      notify.success('Categoría creada exitosamente')
    }
    isDialogOpen.value = false
    await fetchCategories()
  } catch (error: any) {
    notify.error(error.response?.data?.message || 'Error al guardar la categoría')
  } finally {
    saving.value = false
  }
}

async function handleDeleteNode(payload: { node: any; currentParentId?: string | null }) {
  const { node, currentParentId } = payload

  // Si la categoría está compartida en más de una rama
  if (node.is_shared && currentParentId) {
    const choice = confirm(
      `"${node.name}" está vinculada a ${node.parents_count} ramas diferentes.\n\n` +
      `¿Deseas desvincularla ÚNICAMENTE de esta rama?\n\n` +
      `(Pulsa ACEPTAR para desvincular solo de aquí, o CANCELAR para evaluar eliminarla completamente).`
    )

    if (choice) {
      try {
        await http.delete(`/categories/${node.id}/unlink-parent/${currentParentId}`)
        notify.success(`"${node.name}" desvinculada de esta rama`)
        await fetchCategories()
        return
      } catch (error) {
        notify.error('Error al desvincular categoría')
        return
      }
    }
  }

  // Confirmación de eliminación total
  if (!confirm(`¿Estás seguro de eliminar completamente "${node.name}" de todo el sistema?`)) {
    return
  }

  try {
    await http.delete(`/categories/${node.id}`)
    notify.success('Categoría eliminada')
    await fetchCategories()
  } catch (error) {
    notify.error('Error al eliminar categoría')
  }
}

async function seedAcademicTemplate() {
  seeding.value = true
  try {
    await http.post('/categories/seed-template')
    notify.success('Plantilla académica con carreras compartidas cargada exitosamente')
    await fetchCategories()
  } catch (error: any) {
    notify.error(error.response?.data?.message || 'Error al cargar plantilla')
  } finally {
    seeding.value = false
  }
}

// ==========================================
// GESTIÓN DE ESTADOS PERSONALIZADOS (MÁQUINA DE ESTADOS)
// ==========================================
const statuses = ref<any[]>([])
const loadingStatuses = ref(false)
const savingStatus = ref(false)
const seedingStatus = ref(false)
const isStatusDialogOpen = ref(false)
const selectedStatus = ref<any>(null)

const statusForm = reactive({
  name: '',
  color: '#10b981',
  icon: 'sym_r_flag',
  stage_type: 'in_progress',
  is_default: false,
  has_dependencies: false,
  allowed_previous_status_ids: [] as string[],
})

const stageTypeOptions = [
  { label: 'Inicial (Entrada / Nuevo)', value: 'initial' },
  { label: 'En Proceso (Atención / Seguimiento)', value: 'in_progress' },
  { label: 'Ganado (Inscrito / Éxito)', value: 'won' },
  { label: 'Perdido (Descartado / Rechazo)', value: 'lost' },
]

const statusIconOptions = [
  { label: 'No leído / Nuevo', value: 'sym_r_mark_chat_unread' },
  { label: 'Conversación / Chat', value: 'sym_r_forum' },
  { label: 'Educación / Universidad', value: 'sym_r_school' },
  { label: 'Check / Éxito', value: 'sym_r_check_circle' },
  { label: 'Cancelado / Descarte', value: 'sym_r_cancel' },
  { label: 'Bandera', value: 'sym_r_flag' },
  { label: 'Estrella', value: 'sym_r_star' },
  { label: 'Pausa / Espera', value: 'sym_r_schedule' },
]

const otherStatusesForDependency = computed(() => {
  return statuses.value.filter((s) => !selectedStatus.value || s.id !== selectedStatus.value.id)
})

function formatStageType(type: string): string {
  switch (type) {
    case 'initial': return 'Inicial'
    case 'in_progress': return 'En Proceso'
    case 'won': return 'Ganado'
    case 'lost': return 'Perdido'
    default: return type
  }
}

function getStageBadgeColor(type: string): string {
  switch (type) {
    case 'initial': return 'blue-9'
    case 'in_progress': return 'cyan-9'
    case 'won': return 'positive'
    case 'lost': return 'negative'
    default: return 'grey-8'
  }
}

async function fetchStatuses() {
  loadingStatuses.value = true
  try {
    const res = await http.get('/custom-statuses')
    statuses.value = res.data.data || []
  } catch (error) {
    notify.error('Error al cargar estados personalizados')
  } finally {
    loadingStatuses.value = false
  }
}

function openCreateStatusDialog() {
  selectedStatus.value = null
  statusForm.name = ''
  statusForm.color = '#10b981'
  statusForm.icon = 'sym_r_flag'
  statusForm.stage_type = 'in_progress'
  statusForm.is_default = false
  statusForm.has_dependencies = false
  statusForm.allowed_previous_status_ids = []
  isStatusDialogOpen.value = true
}

function openEditStatusDialog(st: any) {
  selectedStatus.value = st
  statusForm.name = st.name
  statusForm.color = st.color || '#10b981'
  statusForm.icon = st.icon || 'sym_r_flag'
  statusForm.stage_type = st.stage_type || 'in_progress'
  statusForm.is_default = !!st.is_default
  statusForm.has_dependencies = (st.allowed_previous_status_ids || []).length > 0
  statusForm.allowed_previous_status_ids = [...(st.allowed_previous_status_ids || [])]
  isStatusDialogOpen.value = true
}

async function saveStatus() {
  if (!statusForm.name.trim()) {
    notify.warning('El nombre del estado es obligatorio')
    return
  }

  savingStatus.value = true
  try {
    const payload = {
      name: statusForm.name.trim(),
      color: statusForm.color,
      icon: statusForm.icon,
      stage_type: statusForm.stage_type,
      is_default: statusForm.is_default,
      allowed_previous_status_ids: statusForm.has_dependencies ? statusForm.allowed_previous_status_ids : [],
    }

    if (selectedStatus.value) {
      await http.put(`/custom-statuses/${selectedStatus.value.id}`, payload)
      notify.success('Estado actualizado exitosamente')
    } else {
      await http.post('/custom-statuses', payload)
      notify.success('Estado creado exitosamente')
    }

    isStatusDialogOpen.value = false
    await fetchStatuses()
  } catch (error: any) {
    notify.error(error.response?.data?.message || 'Error al guardar estado')
  } finally {
    savingStatus.value = false
  }
}

async function deleteStatus(st: any) {
  if (!confirm(`¿Estás seguro de eliminar el estado "${st.name}"?`)) return
  try {
    await http.delete(`/custom-statuses/${st.id}`)
    notify.success('Estado eliminado')
    await fetchStatuses()
  } catch (error: any) {
    notify.error(error.response?.data?.message || 'Error al eliminar estado')
  }
}

async function seedAcademicStatuses() {
  seedingStatus.value = true
  try {
    await http.post('/custom-statuses/seed-academic')
    notify.success('Flujo de estados universitarios cargado exitosamente')
    await fetchStatuses()
  } catch (error: any) {
    notify.error(error.response?.data?.message || 'Error al cargar flujo universitario')
  } finally {
    seedingStatus.value = false
  }
}

onMounted(() => {
  fetchCategories()
  fetchStatuses()
})
</script>

<style scoped lang="scss">
.xf-parameters-page {
  padding: 8px;
}

.xf-subnav-tabs {
  display: flex;
  gap: 8px;
  border-bottom: 1px solid var(--crm-color-border);
  padding-bottom: 12px;
}

.xf-subnav-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: transparent;
  border: 1px solid transparent;
  color: var(--crm-color-muted);
  padding: 8px 16px;
  border-radius: 8px;
  font-size: 0.88rem;
  font-weight: 500;
  cursor: pointer;
  transition: all var(--crm-transition-fast);

  &:hover {
    color: #ffffff;
    background: rgba(255, 255, 255, 0.04);
  }

  &--active {
    color: #ffffff;
    background: var(--crm-color-primary-soft);
    border-color: var(--crm-color-primary);
  }
}

.xf-tree-card {
  background: var(--crm-bg-card);
  border: 1px solid var(--crm-color-border);
  border-radius: 12px;
  overflow: hidden;
}

.xf-tree-card-header {
  background: rgba(255, 255, 255, 0.02);
}

.xf-dot-indicator {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  display: inline-block;
}

.xf-color-circle {
  width: 22px;
  height: 22px;
  border-radius: 50%;
  display: inline-block;
  transition: transform 0.15s ease;
  &:hover {
    transform: scale(1.15);
  }
}

.xf-empty-card {
  background: var(--crm-bg-card);
  border: 1px dashed var(--crm-color-border);
  border-radius: 16px;
}

.xf-status-banner {
  background: rgba(16, 185, 129, 0.08);
  border: 1px solid rgba(16, 185, 129, 0.25);
  color: #ffffff;
}

.xf-status-card {
  background: var(--crm-bg-card);
  border: 1px solid var(--crm-color-border);
  border-radius: 10px;
}

.xf-status-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  display: inline-block;

  &--pending {
    background-color: #f59e0b;
    box-shadow: 0 0 8px rgba(245, 158, 11, 0.4);
  }

  &--open {
    background-color: #10b981;
    box-shadow: 0 0 8px rgba(16, 185, 129, 0.4);
  }

  &--closed {
    background-color: #64748b;
  }
}

.xf-btn-primary {
  background: var(--crm-color-primary) !important;
  color: #ffffff !important;
  font-weight: 600;
  border-radius: 8px;
}

.xf-modal-card {
  background: var(--crm-bg-card);
  border: 1px solid var(--crm-color-border);
  border-radius: 12px;
}

.xf-status-node-card {
  background: var(--crm-bg-card);
  border: 1px solid var(--crm-color-border);
  border-radius: 12px;
  transition: all var(--crm-transition-fast);

  &:hover {
    border-color: rgba(255, 255, 255, 0.15);
  }
}

.xf-dot-indicator-lg {
  width: 12px;
  height: 12px;
  border-radius: 50%;
  display: inline-block;
}

.xf-dependency-card {
  background: rgba(255, 255, 255, 0.02);
  border: 1px dashed rgba(255, 255, 255, 0.1);
}

.border-top-subtle {
  border-top: 1px solid rgba(255, 255, 255, 0.05);
  padding-top: 8px;
}
</style>
