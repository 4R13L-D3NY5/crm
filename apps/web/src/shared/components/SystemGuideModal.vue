<template>
  <q-dialog
    :model-value="modelValue"
    position="right"
    full-height
    transition-show="slide-left"
    transition-hide="slide-right"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <q-card class="column no-wrap xf-guide-slide-card">
      <!-- HEADER CONTEXTUAL SUPERIOR -->
      <q-card-section class="q-py-md q-px-lg xf-guide-header border-bottom-subtle">
        <div class="row items-center justify-between no-wrap q-mb-xs">
          <div class="row items-center q-gutter-x-sm">
            <q-avatar size="36px" class="xf-bulb-avatar shadow-1">
              <q-icon name="sym_r_lightbulb" size="22px" color="amber-4" />
            </q-avatar>
            <div>
              <div class="text-subtitle1 text-bold text-ink leading-tight">
                Guía de Ayuda en Pantalla
              </div>
              <div class="text-caption text-muted" style="font-size: 11px;">
                Manual contextual &bull; {{ currentModuleData.label }}
              </div>
            </div>
          </div>

          <div class="row items-center q-gutter-x-xs">
            <q-btn
              flat
              round
              dense
              icon="sym_r_close"
              color="grey-4"
              v-close-popup
              class="xf-btn-icon"
            >
              <q-tooltip>Cerrar Guía</q-tooltip>
            </q-btn>
          </div>
        </div>

        <!-- Selector Rápido de Módulo & Filtro de Búsqueda -->
        <div class="row q-col-gutter-xs q-mt-xs items-center">
          <div class="col-12 col-sm-7">
            <q-select
              v-model="activeModuleKey"
              :options="availableModuleOptions"
              emit-value
              map-options
              dense
              outlined
              class="xf-guide-select"
            >
              <template #prepend>
                <q-icon :name="currentModuleData.icon" size="18px" color="primary" />
              </template>
              <template #selected-item="scope">
                <span class="text-caption text-bold text-ink ellipsis">{{ scope.opt.label }}</span>
              </template>
              <template #option="scope">
                <q-item v-bind="scope.itemProps" dense class="xf-select-item">
                  <q-item-section avatar style="min-width: 28px;">
                    <q-icon :name="scope.opt.icon" size="18px" color="primary" />
                  </q-item-section>
                  <q-item-section>
                    <q-item-label class="text-caption text-weight-medium text-ink">{{ scope.opt.label }}</q-item-label>
                    <q-item-label caption class="text-muted" style="font-size: 10px;">{{ scope.opt.category }}</q-item-label>
                  </q-item-section>
                </q-item>
              </template>
            </q-select>
          </div>

          <div class="col-12 col-sm-5">
            <q-input
              v-model="searchQuery"
              dense
              outlined
              placeholder="Buscar en la guía..."
              class="xf-guide-search-input"
              clearable
            >
              <template #prepend>
                <q-icon name="sym_r_search" size="16px" color="grey-5" />
              </template>
            </q-input>
          </div>
        </div>
      </q-card-section>

      <!-- BARRA DE METADATOS DEL MÓDULO ACTUAL -->
      <div class="q-px-lg q-py-xs bg-dark-soft row items-center justify-between border-bottom-subtle">
        <div class="row items-center q-gutter-x-xs">
          <q-badge color="primary" rounded :label="`Ruta: ${currentModuleData.route}`" />
          <q-badge
            :color="currentModuleData.roleColor"
            rounded
            :label="currentModuleData.roleLabel"
          />
        </div>
        <div class="text-caption text-muted" style="font-size: 11px;">
          XpertiFlow V2 Suite Pro
        </div>
      </div>

      <!-- CUERPO PRINCIPAL CON CONTENIDO DEL MÓDULO ACTIVO -->
      <q-card-section class="col q-pa-none overflow-hidden bg-surface-dark">
        <q-scroll-area class="full-height q-px-lg q-py-md">
          <div class="column q-gutter-y-lg text-ink">

            <!-- 1. RESUMEN: ¿QUÉ PUEDES HACER EN ESTA PANTALLA? -->
            <div class="xf-guide-section-box">
              <div class="row items-center q-gutter-x-xs q-mb-xs">
                <q-icon :name="currentModuleData.icon" color="primary" size="22px" />
                <span class="text-subtitle1 text-bold text-ink">
                  ¿Qué puedes hacer en esta pantalla?
                </span>
              </div>
              <p class="text-caption text-grey-3 q-mb-md">
                {{ currentModuleData.description }}
              </p>

              <!-- Lista de Capacidades Clave -->
              <div class="column q-gutter-y-xs">
                <div
                  v-for="(cap, idx) in filteredCapabilities"
                  :key="idx"
                  class="row items-start q-gutter-x-sm xf-cap-item q-pa-xs rounded-borders"
                >
                  <q-icon name="sym_r_check_circle" color="positive" size="18px" class="q-mt-xs" />
                  <div class="col">
                    <div class="text-caption text-bold text-ink">{{ cap.title }}</div>
                    <div class="text-caption text-grey-4" style="font-size: 11.5px;">{{ cap.detail }}</div>
                  </div>
                </div>
              </div>
            </div>

            <!-- 2. FIGURA VISUAL: ANATOMÍA DE LA PANTALLA -->
            <div v-if="currentModuleData.figure" class="xf-guide-section-box">
              <div class="row items-center q-gutter-x-xs q-mb-xs">
                <q-icon name="sym_r_aspect_ratio" color="amber-4" size="20px" />
                <span class="text-subtitle2 text-bold text-ink">
                  {{ currentModuleData.figure.title }}
                </span>
              </div>
              <div class="text-caption text-muted q-mb-sm">
                {{ currentModuleData.figure.subtitle }}
              </div>

              <!-- Maqueta Visual Interactiva / Esquema Estructurado -->
              <div class="xf-visual-mockup q-pa-sm rounded-borders border-subtle">
                <div class="row q-col-gutter-xs">
                  <div
                    v-for="(zone, zIndex) in currentModuleData.figure.zones"
                    :key="zIndex"
                    :class="zone.colClass || 'col-12 col-md-4'"
                  >
                    <div :class="['xf-mockup-zone q-pa-sm rounded-borders full-height', zone.bgClass]">
                      <div class="row items-center q-gutter-x-xs q-mb-xs">
                        <q-badge :color="zone.badgeColor || 'primary'" :label="zone.number" rounded class="text-bold" />
                        <span class="text-caption text-bold text-ink">{{ zone.name }}</span>
                      </div>
                      <div class="text-caption text-grey-4" style="font-size: 11px; line-height: 1.3;">
                        {{ zone.desc }}
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- 3. PASO A PASO CON CASO REAL Y EJEMPLOS CONCRETOS -->
            <div v-if="currentModuleData.steps && currentModuleData.steps.length > 0" class="xf-guide-section-box">
              <div class="row items-center q-gutter-x-xs q-mb-xs">
                <q-icon name="sym_r_format_list_numbered" color="teal-4" size="20px" />
                <span class="text-subtitle2 text-bold text-ink">
                  Paso a Paso de Operación
                </span>
              </div>
              <div class="text-caption text-muted q-mb-sm">
                Sigue esta secuencia para completar la tarea con máxima efectividad:
              </div>

              <div class="column q-gutter-y-sm">
                <div
                  v-for="(step, sIndex) in currentModuleData.steps"
                  :key="sIndex"
                  class="xf-step-card q-pa-sm rounded-borders bg-dark-soft border-subtle"
                >
                  <div class="row items-center q-gutter-x-sm q-mb-xs">
                    <q-badge color="teal-7" :label="sIndex + 1" rounded class="text-bold" />
                    <span class="text-caption text-bold text-ink">{{ step.title }}</span>
                  </div>
                  <div class="text-caption text-grey-3" style="font-size: 12px; line-height: 1.35;">
                    {{ step.instruction }}
                  </div>
                  <div v-if="step.tip" class="xf-step-tip q-mt-xs q-pa-xs rounded-borders text-caption text-amber-2" style="font-size: 11px;">
                    💡 <b>Tip:</b> {{ step.tip }}
                  </div>
                </div>
              </div>
            </div>

            <!-- 4. CASO DE EJEMPLO REAL (UNITEPC / CRM) -->
            <div v-if="currentModuleData.example" class="xf-guide-section-box">
              <div class="row items-center q-gutter-x-xs q-mb-xs">
                <q-icon name="sym_r_school" color="indigo-4" size="20px" />
                <span class="text-subtitle2 text-bold text-ink">
                  Ejemplo Práctico en Situación Real
                </span>
              </div>
              <q-card class="xf-example-card border-subtle" flat>
                <q-card-section class="q-pa-sm">
                  <div class="text-caption text-bold text-primary q-mb-xs">
                    {{ currentModuleData.example.title }}
                  </div>
                  <div class="text-caption text-grey-3" style="font-size: 11.5px; line-height: 1.4;">
                    {{ currentModuleData.example.scenario }}
                  </div>
                  <q-separator class="q-my-xs" />
                  <div class="text-caption text-grey-4" style="font-size: 11px;">
                    <b>Resultado esperado:</b> {{ currentModuleData.example.outcome }}
                  </div>
                </q-card-section>
              </q-card>
            </div>

            <!-- 5. ERRORES FRECUENTES Y CÓMO EVITARLOS -->
            <div v-if="currentModuleData.pitfalls && currentModuleData.pitfalls.length > 0" class="xf-guide-section-box">
              <div class="row items-center q-gutter-x-xs q-mb-xs">
                <q-icon name="sym_r_warning" color="warning" size="20px" />
                <span class="text-subtitle2 text-bold text-ink">
                  Buenas Prácticas & Errores Frecuentes
                </span>
              </div>
              <ul class="text-caption text-grey-3 q-pl-md q-my-none q-gutter-y-xs" style="font-size: 11.5px;">
                <li v-for="(pit, pIndex) in currentModuleData.pitfalls" :key="pIndex">
                  <b>{{ pit.title }}:</b> {{ pit.advice }}
                </li>
              </ul>
            </div>

            <!-- 6. ATAJOS DE TECLADO PERTINENTES -->
            <div v-if="currentModuleData.shortcuts && currentModuleData.shortcuts.length > 0" class="xf-guide-section-box">
              <div class="row items-center q-gutter-x-xs q-mb-xs">
                <q-icon name="sym_r_keyboard" color="grey-4" size="20px" />
                <span class="text-subtitle2 text-bold text-ink">
                  Atajos Rápidos para este Módulo
                </span>
              </div>
              <div class="row q-gutter-xs">
                <div
                  v-for="(shortcut, scIndex) in currentModuleData.shortcuts"
                  :key="scIndex"
                  class="row items-center q-gutter-x-xs bg-dark-soft q-px-sm q-py-xs rounded-borders border-subtle"
                >
                  <kbd class="xf-kbd">{{ shortcut.key }}</kbd>
                  <span class="text-caption text-grey-3" style="font-size: 11px;">{{ shortcut.action }}</span>
                </div>
              </div>
            </div>

          </div>
        </q-scroll-area>
      </q-card-section>

      <!-- FOOTER DE NAVEGACIÓN Y SOPORTE -->
      <q-card-actions class="q-px-lg q-py-sm border-top-subtle bg-surface-elevated row items-center justify-between">
        <div class="row items-center q-gutter-x-xs text-caption text-muted" style="font-size: 11px;">
          <q-icon name="sym_r_support_agent" size="14px" color="primary" />
          <span>Soporte Central: admisiones@unitepc.com.bo</span>
        </div>
        <q-btn
          flat
          dense
          color="primary"
          label="Entendido"
          v-close-popup
          class="text-weight-bold"
        />
      </q-card-actions>
    </q-card>
  </q-dialog>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute } from 'vue-router'

interface Props {
  modelValue: boolean
}

const props = defineProps<Props>()
const emit = defineEmits<{
  (e: 'update:modelValue', value: boolean): void
}>()

const route = useRoute()
const searchQuery = ref('')

// Función estricta y segura para mapear cualquier ruta o nombre de ruta del CRM a su respectivo módulo de ayuda
function resolveModuleFromPath(path: string, routeName?: string | symbol | null): string {
  const p = (path || '').toLowerCase()
  const n = String(routeName || '').toLowerCase()

  if (p.includes('/tags') || n.includes('tag')) return 'tags'
  if (p.includes('/deals') || n.includes('deal')) return 'deals'
  if (p.includes('/quick-messages') || n.includes('quick-message') || n.includes('quickmessage')) return 'quick_messages'
  if (p.includes('/notes') || n.includes('note')) return 'notes'
  if (p.includes('/contacts') || n.includes('contact')) return 'contacts'
  if (p.includes('/conversations') || n.includes('conversation')) return 'conversations'
  if (p.includes('/whatsapp') || n.includes('whatsapp')) return 'whatsapp'
  if (p.includes('/parameters') || n.includes('parameter')) return 'parameters'
  if (p.includes('/users') || n.includes('user')) return 'users'
  if (p.includes('/departments') || n.includes('department')) return 'departments'
  if (p.includes('/campaigns') || n.includes('campaign')) return 'campaigns'
  if (p.includes('/scheduled-messages') || n.includes('scheduled')) return 'scheduled_messages'
  if (p.includes('/companies') || n.includes('compan')) return 'companies'
  if (p.includes('/reports') || n.includes('report')) return 'reports'
  if (p.includes('/wabot') || p.includes('/ai') || n.includes('wabot') || n.includes('ai')) return 'wabot'
  if (p.includes('/pricing') || p.includes('/planes') || n.includes('pricing')) return 'pricing'
  if (p.includes('/superadmin') || n.includes('superadmin')) return 'superadmin'
  if (p.includes('/settings') || n.includes('setting')) return 'settings'
  if (p.includes('/dashboard') || n.includes('dashboard') || p === '/app' || p === '/app/') return 'dashboard'

  return 'dashboard'
}

// Inicializar el módulo activo según la ruta actual
const activeModuleKey = ref(resolveModuleFromPath(route.path, route.name))

// Actualizar automáticamente cuando se abre el modal o cambia la ruta
watch(
  () => props.modelValue,
  (isOpen) => {
    if (isOpen) {
      activeModuleKey.value = resolveModuleFromPath(route.path, route.name)
    }
  },
  { immediate: true },
)

watch(
  () => [route.path, route.name],
  ([newPath, newName]) => {
    activeModuleKey.value = resolveModuleFromPath(String(newPath), newName as string)
  },
)

// Interfaz para definir el contenido estructurado de ayuda
interface ModuleGuideContent {
  key: string
  route: string
  label: string
  category: string
  icon: string
  roleLabel: string
  roleColor: string
  description: string
  capabilities: Array<{ title: string; detail: string }>
  figure?: {
    title: string
    subtitle: string
    zones: Array<{
      number: string
      name: string
      desc: string
      badgeColor?: string
      bgClass?: string
      colClass?: string
    }>
  }
  steps: Array<{ title: string; instruction: string; tip?: string }>
  example?: {
    title: string
    scenario: string
    outcome: string
  }
  pitfalls?: Array<{ title: string; advice: string }>
  shortcuts?: Array<{ key: string; action: string }>
}

// BASE DE CONOCIMIENTO INTEGRAL PARA TODOS LOS MENÚS DEL SISTEMA (19 MÓDULOS)
const MODULES_KNOWLEDGE_BASE: Record<string, ModuleGuideContent> = {
  // 1. DASHBOARD & MÉTRICAS
  dashboard: {
    key: 'dashboard',
    route: '/app/dashboard',
    label: 'Dashboard & Métricas en Vivo',
    category: 'Operación Diaria',
    icon: 'sym_r_dashboard',
    roleLabel: 'Operadores & Admins',
    roleColor: 'positive',
    description:
      'Panel de control central con indicadores en tiempo real: volumen de conversaciones activas, tiempos de respuesta SLA, distribución de prospectos por carrera y rendimiento por asesor.',
    capabilities: [
      { title: 'KPIs en Tiempo Real', detail: 'Conversaciones activas hoy, tasa de resolución, tiempo medio de primera atención y satisfacción CSAT.' },
      { title: 'Gráfico de Demanda por Hora', detail: 'Curva de actividad diaria que permite identificar los momentos pico de mensajes en la jornada.' },
      { title: 'Distribución por Sede y Carrera', detail: 'Conteo visual de postulantes interesados en Medicina, Odontología, Sistemas, etc.' },
      { title: 'Tabla de Productividad', detail: 'Ranking de asesores con cantidad de chats atendidos y velocidad de cierre.' },
    ],
    figure: {
      title: 'Figura: Distribución del Panel de Control (Dashboard)',
      subtitle: 'Organización de métricas en 3 bloques estratégicos:',
      zones: [
        { number: '1', name: 'Tarjetas de KPIs Superiores', desc: '4 métricas clave: Total Chats Hoy, Tiempo Promedio de Respuesta (<3 min), En Espera y Calificación CSAT.', badgeColor: 'primary', bgClass: 'bg-dark-soft', colClass: 'col-12 col-md-4' },
        { number: '2', name: 'Curva de Actividad Horaria', desc: 'Gráfica interactiva con los picos de atención de la jornada (ej. 10:00 - 12:00 y 16:00 - 18:00).', badgeColor: 'teal-6', bgClass: 'bg-dark-soft', colClass: 'col-12 col-md-4' },
        { number: '3', name: 'Rendimiento por Asesor y Carrera', desc: 'Tabla comparativa con el desempeño de los operadores y las carreras más consultadas.', badgeColor: 'amber-6', bgClass: 'bg-dark-soft', colClass: 'col-12 col-md-4' },
      ],
    },
    steps: [
      { title: 'Seleccionar periodo de análisis', instruction: 'Usa el selector de fecha superior para visualizar Hoy, Esta Semana o el Mes actual.' },
      { title: 'Identificar horas de sobrecarga', instruction: 'Si la curva supera los 50 chats/hora, redistribuye asesores a la bandeja.', tip: 'El SLA óptimo es responder en menos de 3 minutos.' },
      { title: 'Evaluar carreras con mayor demanda', instruction: 'Revisa qué facultad concentra más interés para reforzar las plantillas de respuestas rápidas.' },
    ],
    example: {
      title: 'Caso Real: Supervisión de Campaña de Admisiones',
      scenario: 'Durante el inicio de inscripciones en Cochabamba, el coordinador detecta que a las 10:30 AM hay 45 chats en espera. Reasigna 2 asesores de apoyo y reduce la cola en 15 minutos.',
      outcome: 'Atención fluida sin prospectos perdidos y con un promedio de respuesta de 2.1 minutos.',
    },
    pitfalls: [
      { title: 'No confundir chats abiertos con cerrados', advice: 'Revisa periódicamente la pestaña de cerrados para verificar que los prospectos realmente fueron atendidos.' },
    ],
    shortcuts: [
      { key: 'F5', action: 'Recargar métricas en vivo' },
    ],
  },

  // 2. BANDEJA MULTICANAL
  conversations: {
    key: 'conversations',
    route: '/app/conversations',
    label: 'Bandeja Multicanal (Live Chat)',
    category: 'Operación Diaria',
    icon: 'sym_r_forum',
    roleLabel: 'Operadores & Admins',
    roleColor: 'positive',
    description:
      'Tu espacio central de atención en tiempo real. Recibe mensajes de WhatsApp, responde con plantillas (/), envía audios, clasifica la Sede/Carrera del postulante y actualiza su estado comercial.',
    capabilities: [
      { title: 'Atención en Tiempo Real', detail: 'Recibe y contesta mensajes de WhatsApp al instante con indicador de entrega.' },
      { title: 'Respuestas Rápidas con Barra (/)', detail: 'Escribe "/" para abrir el buscador de plantillas frecuentes de requisitos o aranceles.' },
      { title: 'Clasificación por Sede y Carrera', detail: 'Cajón lateral derecho para asignar Sede (CBA, LPZ, etc.) y Carrera (Medicina, etc.).' },
      { title: 'Cambio de Estado del Lead', detail: 'Pasa el contacto de Nuevo Prospecto a Contactado, Interesado o Matriculado.' },
      { title: 'Reasignación a otro Asesor', detail: 'Transfiere el chat a un colega regional con un solo clic.' },
    ],
    figure: {
      title: 'Figura: Anatomía de la Bandeja Multicanal',
      subtitle: 'Tres áreas de trabajo esenciales para operar:',
      zones: [
        { number: '1', name: 'Lista de Chats & Filtros', desc: 'Pestañas Mis Chats, En Espera y Cerrados. Muestra nombre, último mensaje, canal y hora.', badgeColor: 'primary', bgClass: 'bg-dark-soft', colClass: 'col-12 col-md-4' },
        { number: '2', name: 'Área Central de Mensajería', desc: 'Historial activo, caja de texto, disparador de plantillas (/), grabadora de voz y adjuntos.', badgeColor: 'teal-6', bgClass: 'bg-dark-soft', colClass: 'col-12 col-md-4' },
        { number: '3', name: 'Ficha Lateral de Calificación', desc: 'Asignación de Sede, Carrera, Estado del lead, etiquetas de colores y notas internas privadas.', badgeColor: 'indigo-5', bgClass: 'bg-dark-soft', colClass: 'col-12 col-md-4' },
      ],
    },
    steps: [
      { title: 'Tomar la conversación', instruction: 'Haz clic en el chat en espera para asignártelo e iniciar el diálogo.', tip: 'Responde antes de 3 minutos.' },
      { title: 'Usar respuestas rápidas', instruction: 'Escribe "/" y el nombre del snippet (ej. /requisitos) para insertar texto predefinido.' },
      { title: 'Clasificar la consulta', instruction: 'En el cajón derecho, elige la Sede Regional y la Carrera de interés del postulante.' },
      { title: 'Actualizar el estado', instruction: 'Cambia el estado de "Nuevo Prospecto" a "Contactado" o "Interesado Calificado".' },
    ],
    example: {
      title: 'Caso Real: Postulante a Medicina en Cochabamba',
      scenario: 'Un joven escribe pidiendo aranceles. El asesor responde con "/aranceles-medicina", abre la ficha lateral, marca Sede: Cochabamba, Carrera: Medicina y actualiza el estado a "Interesado Calificado".',
      outcome: 'El estudiante recibe la información en segundos y la institución tiene el lead categorizado para admisiones.',
    },
    pitfalls: [
      { title: 'No dejar chats en "Nuevo Prospecto"', advice: 'Si ya conversaste con el lead, actualízalo a "Contactado" o "Interesado".' },
      { title: 'Ponerte "En Pausa" al ausentarte', advice: 'Si sales a refrigerio, cambia tu presencia para que no se te acumulen chats.' },
    ],
    shortcuts: [
      { key: 'Enter', action: 'Enviar mensaje' },
      { key: 'Shift + Enter', action: 'Salto de línea' },
      { key: '/', action: 'Buscar respuesta rápida' },
    ],
  },

  // 3. PIPELINE COMERCIAL & KANBAN
  deals: {
    key: 'deals',
    route: '/app/deals',
    label: 'Pipeline Comercial & Kanban',
    category: 'Operación Diaria',
    icon: 'sym_r_view_kanban',
    roleLabel: 'Operadores & Admins',
    roleColor: 'positive',
    description:
      'Tablero visual donde cada contacto es una tarjeta que avanza a través del embudo de admisión: desde el primer contacto hasta la matrícula exitosa.',
    capabilities: [
      { title: 'Arrastrar y Soltar (Drag & Drop)', detail: 'Mueve tarjetas entre columnas para actualizar el estado del prospecto al instante.' },
      { title: 'Apertura Directa del Chat', detail: 'Haz clic en el ícono de WhatsApp en cualquier tarjeta para abrir directamente la conversación.' },
      { title: 'Filtros por Asesor y Sede', detail: 'Revisa tu propio embudo o visualiza el avance general de toda la sede.' },
      { title: 'Totales por Etapa', detail: 'Conteo visual de cuántos prospectos se encuentran en cada fase del embudo comercial.' },
    ],
    figure: {
      title: 'Figura: Columnas del Ciclo de Admisión',
      subtitle: 'Secuencia recomendada de izquierda a derecha:',
      zones: [
        { number: '1', name: 'Etapas de Entrada', desc: 'Nuevo Prospecto ➔ Contactado. Proceso de primer acercamiento e información básica.', badgeColor: 'blue-5', bgClass: 'bg-dark-soft', colClass: 'col-12 col-md-4' },
        { number: '2', name: 'Etapas de Calificación', desc: 'Interesado Calificado ➔ Visita a Sede Agendada. Alta probabilidad de cierre.', badgeColor: 'amber-6', bgClass: 'bg-dark-soft', colClass: 'col-12 col-md-4' },
        { number: '3', name: 'Etapas de Cierre', desc: 'Matriculado (Verde - Éxito institucional) o Perdido / Descartado (Gris/Rojo).', badgeColor: 'positive', bgClass: 'bg-dark-soft', colClass: 'col-12 col-md-4' },
      ],
    },
    steps: [
      { title: 'Revisar prospectos nuevos', instruction: 'Examina la columna "Nuevo Prospecto" y haz clic en el ícono de chat para saludarlos.' },
      { title: 'Avanzar etapa tras acordar cita', instruction: 'Arrastra la tarjeta del lead desde "Interesado Calificado" hacia "Visita a Sede Agendada".' },
      { title: 'Confirmar matrícula', instruction: 'Cuando el estudiante entregue su comprobante de pago, arrastra su tarjeta a "Matriculado".' },
    ],
    example: {
      title: 'Caso Real: Seguimiento a Prospecto de Odontología',
      scenario: 'Yerko en El Alto atiende a una bachiller interesada en Odontología. Tras enviarle la malla, ella confirma que irá el jueves al campus. Yerko mueve su tarjeta a "Visita a Sede Agendada".',
      outcome: 'Todo el equipo sabe que esa persona asistirá a la sede y se le prepara su carpeta de admisión.',
    },
    pitfalls: [
      { title: 'No dejar prospectos estancados', advice: 'Si un contacto lleva más de 5 días sin respuesta en una etapa, envíale un mensaje de reactivación.' },
    ],
    shortcuts: [
      { key: 'Drag & Drop', action: 'Mover tarjeta de estado' },
    ],
  },

  // 4. RESPUESTAS RÁPIDAS
  quick_messages: {
    key: 'quick_messages',
    route: '/app/quick-messages',
    label: 'Respuestas Rápidas (Snippets)',
    category: 'Operación Diaria',
    icon: 'sym_r_bolt',
    roleLabel: 'Operadores & Admins',
    roleColor: 'positive',
    description:
      'Catálogo de plantillas de texto frecuente para contestar dudas de requisitos, cuentas de depósito o saludo inicial en menos de 2 segundos mediante atajos con barra (/).',
    capabilities: [
      { title: 'Atajos con Barra (/clave)', detail: 'Asigna un atajo memorable (ej. /requisitos, /bienvenida, /medicina, /cuentas).' },
      { title: 'Variables Dinámicas', detail: 'Usa {name} para que el sistema reemplace automáticamente el nombre real del cliente.' },
      { title: 'Brochures y PDFs Adjuntos', detail: 'Vincula archivos PDF de mallas curriculares para enviarlos adjuntos con el texto.' },
      { title: 'Categorización por Carrera', detail: 'Agrupa respuestas por facultades para facilitar su búsqueda rápida en vivo.' },
    ],
    figure: {
      title: 'Figura: Directorio y Editor de Respuestas Rápidas',
      subtitle: 'Componentes del gestor de plantillas:',
      zones: [
        { number: '1', name: 'Buscador & Filtros', desc: 'Búsqueda por texto o disparador de atajo (ej. "/req") para localizar plantillas.', badgeColor: 'primary', bgClass: 'bg-dark-soft', colClass: 'col-12 col-md-4' },
        { number: '2', name: 'Tabla de Respuestas', desc: 'Listado con el atajo (/ejemplo), fragmento de texto, variables y botón de editar.', badgeColor: 'teal-6', bgClass: 'bg-dark-soft', colClass: 'col-12 col-md-4' },
        { number: '3', name: 'Botón "+ Nueva Respuesta"', desc: 'Abre el formulario para redactar el texto, insertar {name} y adjuntar archivos.', badgeColor: 'positive', bgClass: 'bg-dark-soft', colClass: 'col-12 col-md-4' },
      ],
    },
    steps: [
      { title: 'Crear nueva plantilla', instruction: 'Presiona "+ Nueva Respuesta Rápida", define el atajo (ej. "requisitos") sin espacios ni símbolos.' },
      { title: 'Personalizar el mensaje', instruction: 'Escribe el texto e inserta "{name}" para que salude por su nombre al estudiante.' },
      { title: 'Usar en la conversación', instruction: 'En la bandeja de chat, teclea "/" seguido del atajo y presiona Enter para autocompletar.', tip: 'No necesitas memorizar todo el atajo, el menú desplegable te sugiere opciones mientras escribes.' },
    ],
    example: {
      title: 'Caso Real: Plantilla de Cuentas Bancarias',
      scenario: 'El equipo crea el atajo `/cuentas`: "Estimado(a) {name}, las cuentas oficiales de UNITEPC son: Banco Unión Cta Cte... Banco Mercantil Santa Cruz...".',
      outcome: 'Cero errores de digitación de números de cuenta y respuesta en 1 segundo.',
    },
    pitfalls: [
      { title: 'No crear atajos demasiado largos', advice: 'Prefiere `/medicina` antes que `/informacion-completa-de-carrera-de-medicina`.' },
    ],
    shortcuts: [
      { key: '/', action: 'Desplegar snippets en el chat' },
    ],
  },

  // 5. NOTAS & BASE DE CONOCIMIENTO
  notes: {
    key: 'notes',
    route: '/app/notes',
    label: 'Notas & Base de Conocimiento',
    category: 'Operación Diaria',
    icon: 'sym_r_sticky_note_2',
    roleLabel: 'Operadores & Admins',
    roleColor: 'positive',
    description:
      'Tablero de notas colaborativo tipo Google Keep. Registra recordatorios, circulares internas, instructivos de becas y fechas límites de matrícula para todo el equipo.',
    capabilities: [
      { title: 'Notas Fijadas con Chincheta 📌', detail: 'Fija las directivas más urgentes para que permanezcan siempre en la parte superior.' },
      { title: 'Códigos de Color Temáticos', detail: 'Asigna colores (amarillo, verde, celeste, rosa) según el tema o la sede.' },
      { title: 'Búsqueda Instantánea', detail: 'Encuentra cualquier circular o dato numérico por palabras clave al instante.' },
      { title: 'Edición Rápida en Tarjeta', detail: 'Haz clic en cualquier nota para actualizarla o añadir viñetas de seguimiento.' },
    ],
    figure: {
      title: 'Figura: Tablero de Notas Estilo Keep',
      subtitle: 'Organización visual de información compartida:',
      zones: [
        { number: '1', name: 'Barra de Búsqueda Superior', desc: 'Filtra notas al vuelo por título, palabras del cuerpo o etiquetas temáticas.', badgeColor: 'primary', bgClass: 'bg-dark-soft', colClass: 'col-12 col-md-4' },
        { number: '2', name: 'Sección "Fijadas" 📌', desc: 'Notas prioritarias de rectoría o jefatura que nunca bajan de posición.', badgeColor: 'amber-6', bgClass: 'bg-dark-soft', colClass: 'col-12 col-md-4' },
        { number: '3', name: 'Tablero General de Notas', desc: 'Cuadrícula dinámica de notas clasificadas por colores con fecha de última edición.', badgeColor: 'teal-6', bgClass: 'bg-dark-soft', colClass: 'col-12 col-md-4' },
      ],
    },
    steps: [
      { title: 'Crear nota', instruction: 'Haz clic en el recuadro "+ Crear nota...", escribe el título y el contenido detallado.' },
      { title: 'Elegir color temático', instruction: 'Haz clic en la paleta de colores para clasificarla (ej. Rojo para Fechas Límites).' },
      { title: 'Fijar si es prioritaria', instruction: 'Presiona el ícono de la chincheta 📌 para que aparezca arriba de todo.' },
    ],
    example: {
      title: 'Caso Real: Circular de Beca Bachiller de Excelencia',
      scenario: 'La jefatura publica una nota titulada "Requisitos Beca Bachiller 100%" con color violeta y chincheta. Describe promedio mínimo de 90 y fecha límite del 28 de febrero.',
      outcome: 'Los 8 asesores de todas las sedes tienen el dato exacto a la mano sin necesidad de interrumpir al supervisor.',
    },
    pitfalls: [
      { title: 'No almacenar credenciales confidenciales', advice: 'No uses las notas para guardar contraseñas maestras o datos bancarios sensibles.' },
    ],
    shortcuts: [
      { key: 'Ctrl + S', action: 'Guardar nota' },
    ],
  },

  // 6. CONTACTOS & AUDIENCIA
  contacts: {
    key: 'contacts',
    route: '/app/contacts',
    label: 'Contactos & Audiencia (Exportación)',
    category: 'Operación Diaria',
    icon: 'sym_r_contacts',
    roleLabel: 'Operadores & Admins',
    roleColor: 'positive',
    description:
      'Directorio de contactos de WhatsApp. Filtra por Sede, Carrera y Estado, y descarga selecciones en planillas Excel (.xlsx) estructuradas o Reportes PDF formales con membrete y KPIs.',
    capabilities: [
      { title: 'Selección Masiva o Individual', detail: 'Marca casillas en contactos específicos o presiona "Seleccionar Todos" con un clic.' },
      { title: 'Descarga en Excel (.xlsx)', detail: 'Planilla con columnas limpias: Nombre, Teléfono, Sede, Carrera, Estado y Fecha.' },
      { title: 'Reporte Ejecutivo en PDF', detail: 'Documento formal con membrete UNITEPC, resumen de métricas y tabla paginada.' },
      { title: 'Filtros Combinados', detail: 'Filtra de inmediato por Sede Regional (CBA, LPZ, STC...), Carrera y Estado comercial.' },
    ],
    figure: {
      title: 'Figura: Selector y Barra Flotante de Descarga',
      subtitle: 'Flujo de exportación en 3 zonas coordinadas:',
      zones: [
        { number: '1', name: 'Barra Superior de Filtros', desc: 'Buscador de texto + desplegables de Sede Regional, Carrera y Estado del lead.', badgeColor: 'primary', bgClass: 'bg-dark-soft', colClass: 'col-12 col-md-4' },
        { number: '2', name: 'Tabla con Casillas (Checkboxes)', desc: 'Marca casillas individuales o la casilla maestra superior para seleccionar todo.', badgeColor: 'teal-6', bgClass: 'bg-dark-soft', colClass: 'col-12 col-md-4' },
        { number: '3', name: 'Barra Flotante de Exportación', desc: 'Botón "Descargar Selección" con opciones de Excel (.xlsx) o Reporte PDF formal.', badgeColor: 'positive', bgClass: 'bg-dark-soft', colClass: 'col-12 col-md-4' },
      ],
    },
    steps: [
      { title: 'Aplicar filtros', instruction: 'Filtra por Sede (ej. Cochabamba) y Estado (ej. Interesado Calificado).' },
      { title: 'Marcar los prospectos', instruction: 'Marca la casilla superior para seleccionar los contactos filtrados.' },
      { title: 'Descargar reporte', instruction: 'Haz clic en "Descargar Selección" en la barra inferior y elige Excel o PDF.', tip: 'El PDF incluye conteos por sede y estado en su portada.' },
    ],
    example: {
      title: 'Caso Real: Informe de Odontología en Santa Cruz',
      scenario: 'La coordinación solicita los interesados en Odontología de Santa Cruz. El usuario filtra Sede: Santa Cruz, Carrera: Odontología, selecciona los 40 prospectos y descarga el PDF listo para imprimir.',
      outcome: 'Reporte profesional generado en 5 segundos sin necesidad de editar hojas manualmente.',
    },
    pitfalls: [
      { title: 'Verificar filtros activos antes de exportar', advice: 'Asegúrate de no tener un filtro residual para que la descarga contenga exactamente la población requerida.' },
    ],
  },

  // 7. ETIQUETAS & SEGMENTOS
  tags: {
    key: 'tags',
    route: '/app/tags',
    label: 'Etiquetas & Segmentos',
    category: 'Operación Diaria',
    icon: 'sym_r_label',
    roleLabel: 'Operadores & Admins',
    roleColor: 'positive',
    description:
      'Organiza y clasifica a tus contactos mediante etiquetas de colores. Permiten segmentar audiencias (ej. "Postulante 2026", "Pregrado", "Prioridad Alta") para filtrar en la bandeja multicanal o enviar campañas masivas.',
    capabilities: [
      { title: 'Creación de Etiquetas', detail: 'Define nombres descriptivos y colores identificadores (hexadecimales o paleta rápida).' },
      { title: 'Conteo de Contactos en Vivo', detail: 'Visualiza en tiempo real cuántos contactos y conversaciones tienen asignada cada etiqueta.' },
      { title: 'Segmentación para Campañas', detail: 'Permite seleccionar audiencias específicas al momento de lanzar envíos masivos.' },
      { title: 'Filtro en Bandeja de Chats', detail: 'Filtra las conversaciones activas para atender únicamente los contactos con cierta etiqueta.' },
    ],
    figure: {
      title: 'Figura: Directorio y Gestión de Etiquetas',
      subtitle: 'Componentes principales del módulo:',
      zones: [
        { number: '1', name: 'Buscador de Etiquetas', desc: 'Filtra etiquetas existentes por nombre para localizar rápidamente la requerida.', badgeColor: 'primary', bgClass: 'bg-dark-soft', colClass: 'col-12 col-md-4' },
        { number: '2', name: 'Listado con Insignia de Color', desc: 'Muestra cada etiqueta con su círculo de color, nombre y cantidad de contactos vinculados.', badgeColor: 'teal-6', bgClass: 'bg-dark-soft', colClass: 'col-12 col-md-4' },
        { number: '3', name: 'Botón "+ Nueva Etiqueta"', desc: 'Abre el formulario emergente para definir el nombre y seleccionar el color deseado.', badgeColor: 'positive', bgClass: 'bg-dark-soft', colClass: 'col-12 col-md-4' },
      ],
    },
    steps: [
      { title: 'Crear una nueva etiqueta', instruction: 'Haz clic en el botón superior "+ Nueva Etiqueta", escribe el nombre y selecciona un color representativo.', tip: 'Usa nombres estandarizados en mayúsculas/minúsculas.' },
      { title: 'Vincularla a un contacto en el chat', instruction: 'Dentro de cualquier conversación, abre el cajón derecho de etiquetas y marca la casilla correspondiente.' },
      { title: 'Filtrar por etiqueta', instruction: 'En la lista de chats o en Contactos, usa el filtro de etiquetas para ver solo ese grupo.' },
    ],
    example: {
      title: 'Caso Real: Segmentar asistentes a la Feria Vocacional',
      scenario: 'El equipo de admisiones crea la etiqueta "Feria Vocacional 2026" con color verde. Todos los contactos que escribieron desde la feria son etiquetados para enviarles la invitación a la visita al campus.',
      outcome: '150 contactos agrupados bajo una misma categoría listos para seguimiento comercial.',
    },
    pitfalls: [
      { title: 'Evitar duplicidad de nombres', advice: 'No crees "Feria", "feria" y "Feria 2026" por separado; unifica criterios de nombres.' },
    ],
  },

  // 8. CANALES & CONEXIONES WHATSAPP
  whatsapp: {
    key: 'whatsapp',
    route: '/app/whatsapp',
    label: 'Canales & Conexiones WhatsApp',
    category: 'Gestión & Configuración',
    icon: 'sym_r_hub',
    roleLabel: 'Solo Administradores',
    roleColor: 'indigo-5',
    description:
      'Gestión de líneas telefónicas institucionales. Conecta números de WhatsApp mediante código QR (motor Baileys), supervisa la estabilidad del socket y asigna asesores regionales a cada canal.',
    capabilities: [
      { title: 'Emparejamiento QR Rápido', detail: 'Generación instantánea de código QR para escanear desde WhatsApp móvil.' },
      { title: 'Semáforo de Conexión en Vivo', detail: 'Insignias visuales: Conectado (Verde), Conectando (Amarillo) o Desconectado (Rojo).' },
      { title: 'Multi-Línea Institucional', detail: 'Administra líneas independientes para Cochabamba, La Paz, Santa Cruz, etc.' },
      { title: 'Asignación de Canales', detail: 'Define qué asesores regionales tienen acceso a responder las conversaciones de cada línea.' },
    ],
    figure: {
      title: 'Figura: Panel de Canales y Emparejamiento QR',
      subtitle: 'Componentes del gestor de conexiones WhatsApp:',
      zones: [
        { number: '1', name: 'Tarjetas de Canales Activos', desc: 'Muestra el nombre del canal (ej. Admisiones CBA), número de teléfono y estado del socket.', badgeColor: 'primary', bgClass: 'bg-dark-soft', colClass: 'col-12 col-md-4' },
        { number: '2', name: 'Ventana de Código QR', desc: 'Contenedor con el QR generado por Baileys listo para ser escaneado con la app móvil.', badgeColor: 'teal-6', bgClass: 'bg-dark-soft', colClass: 'col-12 col-md-4' },
        { number: '3', name: 'Ajustes & Asignación', desc: 'Opciones de desconexión, saludo inicial y asignación de operadores que atienden la línea.', badgeColor: 'amber-6', bgClass: 'bg-dark-soft', colClass: 'col-12 col-md-4' },
      ],
    },
    steps: [
      { title: 'Vincular número', instruction: 'Haz clic en "Generar QR". Abre WhatsApp en el móvil > Dispositivos Vinculados > Escanear.' },
      { title: 'Confirmar conexión', instruction: 'La insignia cambiará a "Conectado" en color verde.' },
      { title: 'Asignar operadores', instruction: 'Define en los ajustes de la línea qué asesores atenderán los mensajes de ese número.', tip: 'No cierres la sesión desde el teléfono móvil para evitar desconexiones.' },
    ],
    example: {
      title: 'Caso Real: Vinculación de Línea Oficial de La Paz',
      scenario: 'La sede La Paz recibe un nuevo chip corporativo. El administrador entra a Canales, genera el QR y Juan Pablo lo escanea desde el teléfono oficial de la sede.',
      outcome: 'La línea queda enlazada en 5 segundos y los mensajes entran directamente al CRM.',
    },
    pitfalls: [
      { title: 'Mantener el teléfono con internet estable', advice: 'Aunque el CRM usa servidores en la nube, el teléfono principal debe sincronizar periódicamente.' },
    ],
  },

  // 9. PARAMETRIZACIÓN & HORARIOS
  parameters: {
    key: 'parameters',
    route: '/app/parameters',
    label: 'Parametrización & Horarios',
    category: 'Gestión & Configuración',
    icon: 'sym_r_tune',
    roleLabel: 'Solo Administradores',
    roleColor: 'indigo-5',
    description:
      'Configuración estructural: catálogo de Sedes Regionales y Carreras con sus códigos oficiales, horarios laborales con turnos múltiples (mañana/tarde), horarios por campaña, auto-respuesta y reglas de inactividad SLA.',
    capabilities: [
      { title: 'Catálogo de 9 Sedes Regionales', detail: 'Cochabamba (CBA), La Paz (LPZ), El Alto (EAL), Santa Cruz (STC), Ivirgarzama (IVI), Cobija (CBJ), Puerto Quijarro (PTO), Guayaramerín (GUA) y Caranavi (CVI).' },
      { title: 'Carreras con Códigos Oficiales', detail: 'CARMED (Medicina), CARSIS (Sistemas), CARODO (Odontología), CARCPU (Contaduría), etc.' },
      { title: 'Horarios Multi-Turno', detail: 'Turno Mañana (07:45 - 12:30) y Turno Tarde (14:00 - 18:30). Sábados: 08:15 - 12:30.' },
      { title: 'Mensaje Fuera de Horario', detail: 'Auto-respuesta institucional que avisa al cliente que su mensaje fue registrado fuera de turno.' },
      { title: 'Reglas de Tiempo SLA', detail: 'Alertas si un asesor tarda más de 20 min en responder o si el cliente está inactivo más de 120 min.' },
    ],
    figure: {
      title: 'Figura: Estructura de Horarios y Turnos',
      subtitle: 'Configuración semanal institucional:',
      zones: [
        { number: '1', name: 'Turno Mañana (Lun-Vie)', desc: 'Rango: 07:45 a 12:30. Atención activa para consultas matutinas.', badgeColor: 'teal-5', bgClass: 'bg-dark-soft', colClass: 'col-12 col-md-4' },
        { number: '2', name: 'Turno Tarde (Lun-Vie)', desc: 'Rango: 14:00 a 18:30 (Sábados: 08:15 a 12:30).', badgeColor: 'amber-5', bgClass: 'bg-dark-soft', colClass: 'col-12 col-md-4' },
        { number: '3', name: 'Auto-Respuesta Fuera de Turno', desc: 'Si alguien escribe en domingo o noche, el bot envía el mensaje de cortesía.', badgeColor: 'primary', bgClass: 'bg-dark-soft', colClass: 'col-12 col-md-4' },
      ],
    },
    steps: [
      { title: 'Activar mensaje fuera de horario', instruction: 'En la pestaña "Horario Laboral", activa el switch "Respuesta Automática Fuera de Horario".' },
      { title: 'Ajustar turnos mañana y tarde', instruction: 'En la tabla semanal, define la hora de inicio y fin para el Turno 1 y el Turno 2.' },
      { title: 'Guardar configuración', instruction: 'Presiona "Guardar Parametrización" para aplicar los cambios a nivel de toda la organización.' },
    ],
    example: {
      title: 'Caso Real: Consulta fuera de hora en fin de semana',
      scenario: 'Un postulante escribe el sábado a las 18:00. El sistema detecta que el turno de sábado cerró a las 12:30 y envía el mensaje institucional con los horarios de apertura del lunes.',
      outcome: 'El estudiante no siente que fue ignorado y el mensaje queda archivado para la primera hora del lunes.',
    },
    pitfalls: [
      { title: 'No dejar vacíos los horarios de sábado', advice: 'Recuerda que los sábados tienen horario matutino de 08:15 a 12:30.' },
    ],
  },

  // 10. OPERADORES & EQUIPO
  users: {
    key: 'users',
    route: '/app/users',
    label: 'Operadores & Equipo',
    category: 'Gestión & Configuración',
    icon: 'sym_r_group',
    roleLabel: 'Solo Administradores',
    roleColor: 'indigo-5',
    description:
      'Gestión de cuentas de asesores comerciales: creación con correos oficiales (@unitepc.com.bo), asignación de canales regionales y supervisión de estados de presencia en vivo.',
    capabilities: [
      { title: 'Cuentas Oficiales por Sede', detail: 'Anet en Cochabamba, Juan Pablo en La Paz, Yerko en El Alto, Johana en Santa Cruz, Mauricio en Cobija, etc.' },
      { title: 'Semáforo de Presencia en Vivo', detail: 'Monitorea en tiempo real quiénes están En línea 🟢, En pausa 🟡 o Fuera de turno ⚫.' },
      { title: 'Asignación de Canales', detail: 'Determina qué líneas de WhatsApp atiende cada asesor regional.' },
      { title: 'Control de Roles', detail: 'Diferenciación estricta entre Operadores (atención comercial) y Administradores (jefatura).' },
    ],
    figure: {
      title: 'Figura: Directorio y Semáforo de Operadores',
      subtitle: 'Componentes del panel de equipo:',
      zones: [
        { number: '1', name: 'Buscador & Filtro de Roles', desc: 'Filtra operadores por nombre, correo institucional (@unitepc.com.bo) o rol.', badgeColor: 'primary', bgClass: 'bg-dark-soft', colClass: 'col-12 col-md-4' },
        { number: '2', name: 'Lista de Asesores con Presencia', desc: 'Tabla con avatar, sede asignada, estado de presencia en vivo y canales vinculados.', badgeColor: 'teal-6', bgClass: 'bg-dark-soft', colClass: 'col-12 col-md-4' },
        { number: '3', name: 'Modal "+ Nuevo Operador"', desc: 'Formulario de registro con nombre, correo corporativo, contraseña y selección de rol.', badgeColor: 'positive', bgClass: 'bg-dark-soft', colClass: 'col-12 col-md-4' },
      ],
    },
    steps: [
      { title: 'Crear nuevo operador', instruction: 'Haz clic en "Nuevo Operador", ingresa su nombre, correo @unitepc.com.bo y contraseña provisional.' },
      { title: 'Asignar sede y canales', instruction: 'Marca la línea de WhatsApp correspondiente a su ciudad.' },
      { title: 'Supervisar estado', instruction: 'Observa el semáforo de presencia en vivo durante la jornada para asegurar cobertura de turnos.' },
    ],
    example: {
      title: 'Caso Real: Alta de Operador para El Alto',
      scenario: 'Se crea el usuario `yerko@unitepc.com.bo` con rol de operador asignado al canal de WhatsApp de El Alto.',
      outcome: 'Yerko atiende únicamente los chats de su jurisdicción sin interferir con las otras sedes.',
    },
    pitfalls: [
      { title: 'No compartir contraseñas entre operadores', advice: 'Cada asesor debe contar con su propio usuario para trazabilidad en auditorías.' },
    ],
  },

  // 11. FILAS & DEPARTAMENTOS
  departments: {
    key: 'departments',
    route: '/app/departments',
    label: 'Filas & Departamentos',
    category: 'Gestión & Configuración',
    icon: 'sym_r_alt_route',
    roleLabel: 'Solo Administradores',
    roleColor: 'indigo-5',
    description:
      'Enrutamiento automático de conversaciones: creación de áreas (Admisiones, Caja, Consultorio, Soporte) y chatbot de menú interactivo para guiar al cliente.',
    capabilities: [
      { title: 'Creación de Departamentos', detail: 'Define áreas de atención con color identificador y mensaje de bienvenida.' },
      { title: 'Chatbot de Menú Interactivo', detail: 'Configura opciones numéricas ("Marca 1 para Carreras, 2 para Cajas...").' },
      { title: 'Distribución Equitativa', detail: 'Asigna chats automáticamente entre los asesores miembros de la fila.' },
    ],
    figure: {
      title: 'Figura: Estructura de Departamentos y Menú de Bot',
      subtitle: 'Componentes del enrutamiento conversacional:',
      zones: [
        { number: '1', name: 'Lista de Áreas / Filas', desc: 'Departamentos registrados (Admisiones, Cajas, Registros) con color y operadores miembros.', badgeColor: 'primary', bgClass: 'bg-dark-soft', colClass: 'col-12 col-md-4' },
        { number: '2', name: 'Configuración del Chatbot', desc: 'Mensaje de saludo inicial y opciones numéricas ("1. Medicina, 2. Aranceles...").', badgeColor: 'teal-6', bgClass: 'bg-dark-soft', colClass: 'col-12 col-md-4' },
        { number: '3', name: 'Reglas de Derivación', desc: 'Acciones al marcar cada opción: transferir a departamento o responder texto.', badgeColor: 'amber-6', bgClass: 'bg-dark-soft', colClass: 'col-12 col-md-4' },
      ],
    },
    steps: [
      { title: 'Crear departamento', instruction: 'Haz clic en "+ Nuevo Departamento", asigna nombre (ej. Admisiones) y elige un color identificador.' },
      { title: 'Configurar opciones del bot', instruction: 'Define el saludo inicial y las opciones de derivación a cada departamento.' },
      { title: 'Asignar asesores', instruction: 'Agrega a los operadores que formarán parte de la fila de atención.' },
    ],
    example: {
      title: 'Caso Real: Chatbot de Derivación de Carreras',
      scenario: 'El bot saluda: "Bienvenido a UNITEPC. Elige una opción: 1. Información de Carreras, 2. Cajas y Aranceles". Al marcar 1, transfiere a la fila de admisiones.',
      outcome: 'Filtrado previo 100% automático sin intervención de una operadora telefónica.',
    },
    pitfalls: [
      { title: 'Asegurar asesores en cada fila', advice: 'No crees un departamento sin asesores asignados para no dejar clientes sin respuesta.' },
    ],
  },

  // 12. CAMPAÑAS MASIVAS
  campaigns: {
    key: 'campaigns',
    route: '/app/campaigns',
    label: 'Campañas Masivas',
    category: 'Gestión & Configuración',
    icon: 'sym_r_campaign',
    roleLabel: 'Solo Administradores',
    roleColor: 'indigo-5',
    description:
      'Envío de comunicados y promociones a listas segmentadas de WhatsApp con protección anti-ban (intervalos aleatorios) y monitoreo de entrega en vivo.',
    capabilities: [
      { title: 'Segmentación por Etiquetas', detail: 'Selecciona audiencias completas (ej. todos los contactos con la etiqueta "Postulante 2026").' },
      { title: 'Protección Anti-Ban', detail: 'Aplica intervalos aleatorios de 5 a 15 segundos entre envíos para proteger tu línea de WhatsApp.' },
      { title: 'Monitoreo de Entrega', detail: 'Barra de progreso en tiempo real con conteo de mensajes enviados, entregados y fallidos.' },
      { title: 'Variables Dinámicas', detail: 'Personaliza cada mensaje con `{name}` para mayor cercanía y efectividad.' },
    ],
    figure: {
      title: 'Figura: Asistente de Envío Masivo y Monitor Anti-Ban',
      subtitle: 'Componentes del módulo de campañas:',
      zones: [
        { number: '1', name: 'Configuración de Audiencia', desc: 'Selector de la línea de WhatsApp emisora y etiqueta de destinatarios (ej. 400 contactos).', badgeColor: 'primary', bgClass: 'bg-dark-soft', colClass: 'col-12 col-md-4' },
        { number: '2', name: 'Editor de Mensaje', desc: 'Cuerpo del texto con variable {name}, enlaces institucionales y adjuntos multimedia.', badgeColor: 'teal-6', bgClass: 'bg-dark-soft', colClass: 'col-12 col-md-4' },
        { number: '3', name: 'Barra de Progreso en Vivo', desc: 'Indicador visual con porcentaje, contador de enviados y pausas aleatorias de seguridad.', badgeColor: 'positive', bgClass: 'bg-dark-soft', colClass: 'col-12 col-md-4' },
      ],
    },
    steps: [
      { title: 'Crear campaña', instruction: 'Elige el canal de WhatsApp emisor, redacta el texto con {name} y selecciona la etiqueta de audiencia.' },
      { title: 'Iniciar envío', instruction: 'Presiona "Lanzar Campaña" y supervisa el progreso en vivo en pantalla.', tip: 'No cierres la pestaña durante el despacho para garantizar la monitorización continua.' },
    ],
    example: {
      title: 'Caso Real: Anuncio de Apertura de Inscripciones',
      scenario: 'La jefatura lanza un comunicado a 350 contactos con la etiqueta "Feria Vocacional". El despachador envía un mensaje cada 9 a 14 segundos.',
      outcome: '350 mensajes entregados sin incidentes de bloqueo de WhatsApp y con un 40% de respuestas inmediatas.',
    },
    pitfalls: [
      { title: 'No enviar a bases de datos no autorizadas', advice: 'Dirige las campañas únicamente a contactos que hayan solicitado previamente información de la universidad.' },
    ],
  },

  // 13. ENVÍOS PROGRAMADOS
  scheduled_messages: {
    key: 'scheduled_messages',
    route: '/app/scheduled-messages',
    label: 'Envíos Programados',
    category: 'Gestión & Configuración',
    icon: 'sym_r_schedule_send',
    roleLabel: 'Operadores & Admins',
    roleColor: 'indigo-5',
    description:
      'Programa mensajes para que se envíen en una fecha y hora exactas en el futuro. Ideal para recordatorios de exámenes de admisión o seguimiento a postulantes.',
    capabilities: [
      { title: 'Agendamiento Preciso', detail: 'Selecciona día y hora en el calendario para el disparo automático del mensaje.' },
      { title: 'Recordatorios de Admisión', detail: 'Envía avisos previos al día de examen o recordatorios de vencimiento de matrícula.' },
      { title: 'Historial de Salida', detail: 'Revisa qué mensajes están pendientes, completados o cancelados.' },
    ],
    figure: {
      title: 'Figura: Calendario y Gestor de Envíos Futuros',
      subtitle: 'Componentes del agendamiento automático:',
      zones: [
        { number: '1', name: 'Selector de Contacto & Canal', desc: 'Búsqueda del lead destinatario y línea de WhatsApp por la que saldrá el aviso.', badgeColor: 'primary', bgClass: 'bg-dark-soft', colClass: 'col-12 col-md-4' },
        { number: '2', name: 'Reloj y Calendario', desc: 'Definición del día y la hora exacta de ejecución con confirmación de zona horaria.', badgeColor: 'teal-6', bgClass: 'bg-dark-soft', colClass: 'col-12 col-md-4' },
        { number: '3', name: 'Tabla de Mensajes Programados', desc: 'Listado con estado Pendiente / Enviado / Cancelado y opción de editar antes del envío.', badgeColor: 'amber-6', bgClass: 'bg-dark-soft', colClass: 'col-12 col-md-4' },
      ],
    },
    steps: [
      { title: 'Agendar mensaje', instruction: 'Selecciona el contacto, escribe el mensaje y define la fecha y hora de despacho.' },
      { title: 'Verificar en la tabla', instruction: 'Asegúrate de que figure con estado "Pendiente" y la fecha correcta.' },
    ],
    example: {
      title: 'Caso Real: Recordatorio de Examen de Suficiencia',
      scenario: 'Un postulante rendirá su examen el viernes a las 09:00 AM. El asesor programa un mensaje para el viernes a las 07:30 AM recordando traer su cédula de identidad.',
      outcome: 'El estudiante asiste puntualmente y el asesor no tuvo que recordar el envío manualmente a primera hora.',
    },
    pitfalls: [
      { title: 'Verificar la zona horaria del servidor', advice: 'Asegúrate de que la hora programada corresponda a la hora local boliviana (BOT).' },
    ],
  },

  // 14. EMPRESAS & CUENTAS B2B
  companies: {
    key: 'companies',
    route: '/app/companies',
    label: 'Empresas & Cuentas B2B',
    category: 'Gestión & Configuración',
    icon: 'sym_r_domain',
    roleLabel: 'Operadores & Admins',
    roleColor: 'indigo-5',
    description:
      'Gestión de cuentas institucionales y colegios secundarios: agrupa múltiples contactos individuales bajo una misma entidad jurídica o convenio educativo.',
    capabilities: [
      { title: 'Agrupación Corporativa', detail: 'Vincula directores, profesores y alumnos bajo el nombre de su colegio o institución.' },
      { title: 'Convenios Institucionales', detail: 'Registra acuerdos de becas o descuentos empresariales vigentes.' },
      { title: 'Directorio de Instituciones', detail: 'Listado de colegios con NIT, dirección, teléfono y persona de enlace.' },
    ],
    figure: {
      title: 'Figura: Directorio de Colegios e Instituciones Aliadas',
      subtitle: 'Componentes de la gestión corporativa:',
      zones: [
        { number: '1', name: 'Directorio de Empresas', desc: 'Lista de colegios secundarios y empresas con convenios de becas activos.', badgeColor: 'primary', bgClass: 'bg-dark-soft', colClass: 'col-12 col-md-4' },
        { number: '2', name: 'Ficha de Convenio', desc: 'Datos del acuerdo institucional: porcentaje de descuento, vigencia y contacto del director.', badgeColor: 'teal-6', bgClass: 'bg-dark-soft', colClass: 'col-12 col-md-4' },
        { number: '3', name: 'Contactos Vinculados', desc: 'Bachilleres o empleados asociados a la entidad para seguimiento grupal.', badgeColor: 'positive', bgClass: 'bg-dark-soft', colClass: 'col-12 col-md-4' },
      ],
    },
    steps: [
      { title: 'Registrar institución', instruction: 'Crea la empresa con su NIT o código y asóciale los contactos correspondientes.' },
      { title: 'Vincular bachilleres', instruction: 'Dentro del perfil de la empresa, agrega a los estudiantes pertenecientes al colegio.' },
    ],
    example: {
      title: 'Caso Real: Convenio con Colegio Don Bosco',
      scenario: 'Se firma convenio de descuento para 25 bachilleres. El asesor registra el Colegio Don Bosco y vincula las 25 fichas de prospectos.',
      outcome: 'Reportes específicos del convenio para presentar al rectorado.',
    },
    pitfalls: [
      { title: 'Mantener actualizado el contacto de enlace', advice: 'Verifica anualmente si cambió la dirección o regencia del colegio secundario.' },
    ],
  },

  // 15. REPORTES & MÉTRICAS
  reports: {
    key: 'reports',
    route: '/app/reports',
    label: 'Reportes, Métricas & CSAT',
    category: 'Gestión & Configuración',
    icon: 'sym_r_monitoring',
    roleLabel: 'Solo Administradores',
    roleColor: 'indigo-5',
    description:
      'Informes gerenciales y analítica avanzada: rendimiento por asesor, tiempos medios de respuesta, volumen por canal de WhatsApp y encuestas de satisfacción CSAT.',
    capabilities: [
      { title: 'Rendimiento por Asesor', detail: 'Cantidad de chats cerrados, tiempo medio de primera respuesta y tasa de conversión.' },
      { title: 'Auditoría de Encuestas CSAT', detail: 'Calificaciones de 1 a 5 estrellas otorgadas por los postulantes al finalizar el chat.' },
      { title: 'Descarga en CSV', detail: 'Exporta todas las tablas para reuniones de directorio o análisis estadístico.' },
    ],
    figure: {
      title: 'Figura: Analítica Gerencial y Auditoría de Calidad',
      subtitle: 'Componentes del centro de reportes:',
      zones: [
        { number: '1', name: 'Filtro Temporal', desc: 'Selector de rango de fechas (Semana, Mes, Trimestre) y filtro por sede.', badgeColor: 'primary', bgClass: 'bg-dark-soft', colClass: 'col-12 col-md-4' },
        { number: '2', name: 'Gráficos Comparativos', desc: 'Barras y curvas de volumen de conversaciones por canal y tiempos de resolución.', badgeColor: 'teal-6', bgClass: 'bg-dark-soft', colClass: 'col-12 col-md-4' },
        { number: '3', name: 'Tabla CSAT & Exportación', desc: 'Puntuación promedio de satisfacción y botón "Exportar CSV" de auditoría.', badgeColor: 'positive', bgClass: 'bg-dark-soft', colClass: 'col-12 col-md-4' },
      ],
    },
    steps: [
      { title: 'Consultar métricas', instruction: 'Selecciona el rango de fechas (ej. Últimos 30 días) y revisa los gráficos comparativos.' },
      { title: 'Exportar informe', instruction: 'Presiona "Exportar CSV" para abrir las tablas en Excel o Google Sheets.' },
    ],
    example: {
      title: 'Caso Real: Evaluación Semestral de Calidad',
      scenario: 'La vicerrectoría revisa el informe semestral: tiempo de respuesta promedio de 2.2 minutos y 94% de satisfacción CSAT de 5 estrellas.',
      outcome: 'Reconocimiento al equipo de admisiones y optimización de turnos en sedes con mayor volumen.',
    },
    pitfalls: [
      { title: 'Interpretar tiempos de respuesta considerando horarios', advice: 'Recuerda que las consultas nocturnas quedan pendientes hasta el turno de la mañana.' },
    ],
  },

  // 16. INTELIGENCIA ARTIFICIAL (HENTLE-AI)
  wabot: {
    key: 'wabot',
    route: '/app/wabot',
    label: 'Inteligencia Artificial (Hentle-AI)',
    category: 'Gestión & Configuración',
    icon: 'sym_r_psychology',
    roleLabel: 'Solo Administradores',
    roleColor: 'indigo-5',
    description:
      'Copiloto cognitivo con RAG (embeddings vectoriales de 768 dimensiones). Almacena catálogos y normativas para sugerir respuestas inteligentes en tiempo real a los asesores.',
    capabilities: [
      { title: 'Base de Conocimiento RAG', detail: 'Indexa documentos y preguntas frecuentes en vectores para búsqueda semántica.' },
      { title: 'Sugerencias en Vivo', detail: 'El copiloto sugiere la respuesta óptima mientras el asesor atiende la conversación.' },
      { title: 'Resumen Ejecutivo', detail: 'Genera un resumen de 2 líneas de chats largos con un solo clic.' },
    ],
    figure: {
      title: 'Figura: Motor de Inteligencia Artificial y Embeddings',
      subtitle: 'Componentes del módulo cognitivo:',
      zones: [
        { number: '1', name: 'Carga de Documentos (RAG)', desc: 'Subida de PDFs con mallas curriculares, reglamentos y guías de costos.', badgeColor: 'primary', bgClass: 'bg-dark-soft', colClass: 'col-12 col-md-4' },
        { number: '2', name: 'Estado de Indexación Vectorial', desc: 'Visualizador de fragmentos vectorizados en PostgreSQL (pgvector de 768 dimensiones).', badgeColor: 'teal-6', bgClass: 'bg-dark-soft', colClass: 'col-12 col-md-4' },
        { number: '3', name: 'Simulador de Preguntas', desc: 'Consola de pruebas para validar cómo responde la IA antes de habilitarla a los operadores.', badgeColor: 'amber-6', bgClass: 'bg-dark-soft', colClass: 'col-12 col-md-4' },
      ],
    },
    steps: [
      { title: 'Subir conocimiento', instruction: 'Carga documentos de preguntas frecuentes o guías de carreras para alimentar la IA.' },
      { title: 'Probar en simulador', instruction: 'Hazle una consulta de prueba en el panel para comprobar la precisión de la respuesta.' },
    ],
    example: {
      title: 'Caso Real: Copiloto sugiriendo aranceles de Medicina',
      scenario: 'Un postulante extranjero pregunta si se aceptan pagos en dólares. La IA analiza el reglamento indexado y le sugiere al asesor la respuesta con el tipo de cambio oficial.',
      outcome: 'Respuesta precisa entregada en 2 segundos sin consultar a contabilidad.',
    },
    pitfalls: [
      { title: 'Evitar subir documentos con información obsoleta', advice: 'Elimina las mallas de años pasados para que la IA no confunda datos.' },
    ],
  },

  // 17. PLANES & PRECIOS
  pricing: {
    key: 'pricing',
    route: '/app/pricing',
    label: 'Planes & Precios',
    category: 'Gestión & Configuración',
    icon: 'sym_r_loyalty',
    roleLabel: 'Solo Administradores',
    roleColor: 'indigo-5',
    description:
      'Matriz oficial de suscripciones y recursos del sistema: planes Starter, Pro Growth y Enterprise, con ampliación de canales WhatsApp y operadores adicionales.',
    capabilities: [
      { title: 'Consulta de Cuotas', detail: 'Revisa cuántas líneas y agentes tienes contratados versus los utilizados.' },
      { title: 'Comparativa de Planes', detail: 'Conoce los beneficios de cada nivel de suscripción.' },
      { title: 'Ampliación Inmediata', detail: 'Opciones para solicitar nuevos canales telefónicos para sedes en expansión.' },
    ],
    figure: {
      title: 'Figura: Matriz de Recursos y Licencias',
      subtitle: 'Componentes del panel de suscripción:',
      zones: [
        { number: '1', name: 'Cuotas Actuales de la Empresa', desc: 'Barra de uso: Canales conectados vs contratados y operadores activos.', badgeColor: 'primary', bgClass: 'bg-dark-soft', colClass: 'col-12 col-md-4' },
        { number: '2', name: 'Comparativa de Planes', desc: 'Detalle de funcionalidades entre los planes Starter, Pro Growth y Enterprise.', badgeColor: 'teal-6', bgClass: 'bg-dark-soft', colClass: 'col-12 col-md-4' },
        { number: '3', name: 'Botón de Solicitud de Upgrade', desc: 'Enlace directo con el equipo comercial para ampliaciones de cuota.', badgeColor: 'positive', bgClass: 'bg-dark-soft', colClass: 'col-12 col-md-4' },
      ],
    },
    steps: [
      { title: 'Revisar tu suscripción', instruction: 'Comprueba el estado de tu licencia y los recursos asignados a tu institución.' },
      { title: 'Solicitar más canales', instruction: 'Si vas a inaugurar una nueva sede, solicita la ampliación de licencias de WhatsApp.' },
    ],
    example: {
      title: 'Caso Real: Expansión de Sedes',
      scenario: 'UNITEPC amplía su cobertura regional y solicita la adición de 2 canales de WhatsApp para Caranavi y Riberalta.',
      outcome: 'Nuevos canales habilitados de inmediato en la infraestructura existente.',
    },
    pitfalls: [
      { title: 'Solicitar ampliaciones con anticipación', advice: 'No esperes a agotar el 100% de la capacidad de operadores para solicitar licencias.' },
    ],
  },

  // 18. PORTAL SUPERADMIN
  superadmin: {
    key: 'superadmin',
    route: '/app/superadmin',
    label: 'Portal SuperAdmin SaaS',
    category: 'Master SaaS',
    icon: 'sym_r_admin_panel_settings',
    roleLabel: 'Solo Master SuperAdmin',
    roleColor: 'deep-purple-5',
    description:
      'Panel de control maestro global para el propietario del SaaS (admin@crm.local): facturación MRR, matriz de clientes corporativos, concesión de licencias y días de prueba.',
    capabilities: [
      { title: 'Monitoreo de MRR/ARR', detail: 'Control de ingresos mensuales y anuales consolidados.' },
      { title: 'Gestión de Períodos de Prueba', detail: 'Ampliación de días (+3, +7, +14, +30) y cuotas con 1 clic.' },
      { title: 'Generador de Licencias', detail: 'Firma criptográfica de licencias corporativas.' },
    ],
    figure: {
      title: 'Figura: Consola Maestra SuperAdmin',
      subtitle: 'Componentes del control global SaaS:',
      zones: [
        { number: '1', name: 'Métricas Financieras MRR/ARR', desc: 'Gráficos de facturación mensual consolidada y tasa de renovación de inquilinos.', badgeColor: 'deep-purple-5', bgClass: 'bg-dark-soft', colClass: 'col-12 col-md-4' },
        { number: '2', name: 'Matriz de Organizaciones', desc: 'Tabla maestra con todas las empresas clientes registradas y sus fechas de vencimiento.', badgeColor: 'teal-6', bgClass: 'bg-dark-soft', colClass: 'col-12 col-md-4' },
        { number: '3', name: 'Acciones de Extensión & Licencia', desc: 'Botones de acción rápida: +7 días, +14 días, generar licencia criptográfica.', badgeColor: 'amber-6', bgClass: 'bg-dark-soft', colClass: 'col-12 col-md-4' },
      ],
    },
    steps: [
      { title: 'Administrar inquilinos', instruction: 'Consulta la salud de cada empresa cliente y gestiona sus límites.' },
      { title: 'Extender prueba', instruction: 'Localiza la empresa en la tabla y presiona "+14 días" para reactivar su período de evaluación.' },
    ],
    example: {
      title: 'Caso Real: Concesión de período de evaluación ampliado',
      scenario: 'Un cliente solicita 1 semana adicional de evaluación técnica. El SuperAdmin pulsa "+7 días" y el sistema renueva el acceso al instante.',
      outcome: 'Operatividad continuada sin fricción para el usuario.',
    },
    pitfalls: [
      { title: 'Módulo de uso exclusivo para soporte maestro', advice: 'Nunca otorgues privilegios de SuperAdmin a usuarios del equipo comercial u operativo.' },
    ],
  },

  // 19. AJUSTES & PREFERENCIAS DE USUARIO
  settings: {
    key: 'settings',
    route: '/app/settings',
    label: 'Ajustes & Perfil del Asesor',
    category: 'Gestión & Configuración',
    icon: 'sym_r_settings',
    roleLabel: 'Todos los Usuarios',
    roleColor: 'positive',
    description:
      'Configura tu experiencia de trabajo: tono de notificaciones para mensajes entrantes de WhatsApp, modo oscuro/claro, cambio de contraseña y autenticación de seguridad 2FA.',
    capabilities: [
      { title: 'Tono de Alerta de Mensajes', detail: 'Elige el sonido de notificación (Campana, Timbre, Suave) para no perder ningún chat entrante.' },
      { title: 'Modo Oscuro / Modo Claro', detail: 'Alterna entre fondo oscuro para jornadas nocturnas o claro según tu preferencia.' },
      { title: 'Cambio Seguro de Contraseña', detail: 'Actualiza tus credenciales personales periódicamente para máxima seguridad.' },
      { title: 'Autenticación en Dos Pasos (2FA)', detail: 'Protege tu acceso mediante código OTP en Google Authenticator.' },
    ],
    figure: {
      title: 'Figura: Panel de Ajustes y Preferencias',
      subtitle: 'Componentes de personalización personal:',
      zones: [
        { number: '1', name: 'Perfil & Credenciales', desc: 'Nombre del asesor, correo corporativo y formulario de cambio de contraseña.', badgeColor: 'primary', bgClass: 'bg-dark-soft', colClass: 'col-12 col-md-4' },
        { number: '2', name: 'Alertas Sonoras & Audio', desc: 'Selector del tono de campana y botón de prueba de volumen en tiempo real.', badgeColor: 'teal-6', bgClass: 'bg-dark-soft', colClass: 'col-12 col-md-4' },
        { number: '3', name: 'Tema & Seguridad 2FA', desc: 'Interruptores de Tema Oscuro y activación de segundo factor de autenticación.', badgeColor: 'amber-6', bgClass: 'bg-dark-soft', colClass: 'col-12 col-md-4' },
      ],
    },
    steps: [
      { title: 'Probar sonido de notificación', instruction: 'En la sección Sonidos, presiona "Probar Tono" para verificar el volumen de tus auriculares.' },
      { title: 'Cambiar a modo oscuro', instruction: 'Activa el switch de "Modo Oscuro" para mayor comodidad visual en turnos prolongados.' },
      { title: 'Guardar cambios', instruction: 'Las preferencias se guardan de forma instantánea en tu perfil de usuario.' },
    ],
    example: {
      title: 'Caso Real: Asesor personalizando su puesto de trabajo',
      scenario: 'Anet en Cochabamba activa el tono de timbre audible y el modo oscuro. Aunque minimice la ventana del CRM para revisar una ficha en Word, escucha el timbre cuando entra un nuevo postulante.',
      outcome: 'Respuesta en menos de 1 minuto sin perder ninguna oportunidad comercial.',
    },
    pitfalls: [
      { title: 'Asegurar permisos del navegador', advice: 'Haz clic en "Permitir" cuando el navegador te pida autorización para emitir sonidos y notificaciones.' },
    ],
  },
}

// Módulo actual seleccionado
const currentModuleData = computed<ModuleGuideContent>(() => {
  return (
    MODULES_KNOWLEDGE_BASE[activeModuleKey.value] ||
    MODULES_KNOWLEDGE_BASE['dashboard']
  )
})

// Opciones del selector superior ordenadas por categoría (visibles para todos para aprender del sistema)
const availableModuleOptions = computed(() => {
  return Object.values(MODULES_KNOWLEDGE_BASE).map((mod) => ({
    label: mod.label,
    value: mod.key,
    category: mod.category,
    icon: mod.icon,
  }))
})

// Filtro de búsqueda en vivo dentro de las capacidades
const filteredCapabilities = computed(() => {
  const query = searchQuery.value.trim().toLowerCase()
  if (!query) {
    return currentModuleData.value.capabilities
  }
  return currentModuleData.value.capabilities.filter(
    (cap) =>
      cap.title.toLowerCase().includes(query) ||
      cap.detail.toLowerCase().includes(query),
  )
})
</script>

<style scoped>
.xf-guide-slide-card {
  width: 580px;
  max-width: 95vw;
  height: 100vh;
  background: var(--xf-bg-surface, #111827);
  color: #f3f4f6;
  border-left: 1px solid var(--xf-border-subtle, #27354a);
}

.xf-guide-header {
  background: var(--xf-bg-surface-elevated, #1a2333);
}

.xf-bulb-avatar {
  background: rgba(245, 158, 11, 0.15);
  border: 1px solid rgba(245, 158, 11, 0.35);
}

.xf-guide-select {
  background: var(--xf-bg-surface, #111827);
}

.xf-select-item {
  border-radius: 6px;
  margin: 2px 4px;
}

.xf-guide-search-input {
  background: var(--xf-bg-surface, #111827);
}

.xf-guide-section-box {
  background: rgba(255, 255, 255, 0.02);
  border: 1px solid var(--xf-border-subtle, #27354a);
  border-radius: 8px;
  padding: 14px;
}

.xf-cap-item {
  transition: background 0.15s ease;
  &:hover {
    background: rgba(255, 255, 255, 0.04);
  }
}

.xf-visual-mockup {
  background: #0d131f;
  border: 1px dashed #334155;
}

.xf-mockup-zone {
  border: 1px solid #1e293b;
  min-height: 85px;
}

.xf-step-card {
  border-left: 3px solid var(--q-primary, #10b981);
}

.xf-step-tip {
  background: rgba(245, 158, 11, 0.12);
  border: 1px solid rgba(245, 158, 11, 0.25);
}

.xf-example-card {
  background: rgba(99, 102, 241, 0.08);
  border: 1px solid rgba(99, 102, 241, 0.25);
  border-radius: 6px;
}

.xf-kbd {
  background: #374151;
  color: #f9fafb;
  padding: 2px 6px;
  border-radius: 4px;
  font-family: monospace;
  font-size: 11px;
  border: 1px solid #4b5563;
}

.border-bottom-subtle {
  border-bottom: 1px solid var(--xf-border-subtle, #27354a);
}

.border-top-subtle {
  border-top: 1px solid var(--xf-border-subtle, #27354a);
}

.border-subtle {
  border: 1px solid var(--xf-border-subtle, #27354a);
}

.bg-surface-elevated {
  background: var(--xf-bg-surface-elevated, #1a2333);
}
</style>
