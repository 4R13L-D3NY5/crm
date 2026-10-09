<template>
  <div class="status-flow-diagram-wrapper">
    <!-- Barra de Herramientas y Leyenda del Diagrama -->
    <div class="row items-center justify-between q-mb-md q-px-xs">
      <div class="row items-center q-gutter-x-sm">
        <q-icon name="sym_r_account_tree" color="teal-4" size="22px" />
        <span class="text-subtitle2 text-bold text-white">Mapa Interactivo de Transiciones</span>
        <q-badge color="teal-9" text-color="teal-2" class="text-caption">
          {{ transitions.length }} transiciones configuradas
        </q-badge>
      </div>

      <!-- Leyenda de Etapas -->
      <div class="row items-center q-gutter-x-md text-caption text-grey-4">
        <div class="row items-center q-gutter-x-xs">
          <span class="legend-dot bg-blue-5"></span>
          <span>Inicial</span>
        </div>
        <div class="row items-center q-gutter-x-xs">
          <span class="legend-dot bg-cyan-5"></span>
          <span>En Proceso</span>
        </div>
        <div class="row items-center q-gutter-x-xs">
          <span class="legend-dot bg-positive"></span>
          <span>Ganado / Éxito</span>
        </div>
        <div class="row items-center q-gutter-x-xs">
          <span class="legend-dot bg-negative"></span>
          <span>Perdido / Descarte</span>
        </div>
      </div>
    </div>

    <!-- Contenedor del Lienzo del Diagrama -->
    <div
      ref="diagramContainerRef"
      class="diagram-canvas relative-position"
      @mouseleave="hoveredStatusId = null"
    >
      <!-- Capa de Conectores SVG Vectoriales -->
      <svg
        class="diagram-svg-layer absolute-full"
        :style="{ width: canvasSize.width + 'px', height: canvasSize.height + 'px' }"
      >
        <defs>
          <!-- Marcador de Flecha Estándar -->
          <marker
            id="arrow-default"
            viewBox="0 0 10 10"
            refX="9"
            refY="5"
            markerWidth="6"
            markerHeight="6"
            orient="auto-start-reverse"
          >
            <path d="M 0 1 L 10 5 L 0 9 z" fill="#64748b" />
          </marker>

          <!-- Marcador de Flecha Saliente (Activa / Hover) -->
          <marker
            id="arrow-outgoing"
            viewBox="0 0 10 10"
            refX="9"
            refY="5"
            markerWidth="7"
            markerHeight="7"
            orient="auto-start-reverse"
          >
            <path d="M 0 1 L 10 5 L 0 9 z" fill="#2dd4bf" />
          </marker>

          <!-- Marcador de Flecha Entrante (Requisito / Hover) -->
          <marker
            id="arrow-incoming"
            viewBox="0 0 10 10"
            refX="9"
            refY="5"
            markerWidth="7"
            markerHeight="7"
            orient="auto-start-reverse"
          >
            <path d="M 0 1 L 10 5 L 0 9 z" fill="#38bdf8" />
          </marker>

          <!-- Marcador de Flecha de Descarte -->
          <marker
            id="arrow-lost"
            viewBox="0 0 10 10"
            refX="9"
            refY="5"
            markerWidth="6"
            markerHeight="6"
            orient="auto-start-reverse"
          >
            <path d="M 0 1 L 10 5 L 0 9 z" fill="#f87171" />
          </marker>
        </defs>

        <!-- Rutas / Flechas de Conexión -->
        <g v-for="tr in computedTransitions" :key="tr.id">
          <!-- Línea de fondo invisible más gruesa para fácil detección de hover -->
          <path
            :d="tr.path"
            fill="none"
            stroke="transparent"
            stroke-width="14"
            class="cursor-pointer"
            @mouseenter="highlightedTransition = tr.id"
            @mouseleave="highlightedTransition = null"
          />

          <!-- Línea visible de la flecha con curva Bezier suave -->
          <path
            :d="tr.path"
            fill="none"
            :stroke="tr.strokeColor"
            :stroke-width="tr.strokeWidth"
            :stroke-dasharray="tr.isLost ? '5,5' : (tr.isActive ? '6,3' : 'none')"
            :marker-end="tr.markerId"
            :class="['transition-path', { 'transition-path--active': tr.isActive }]"
          />

          <!-- Etiqueta opcional en el centro de la flecha si está activa -->
          <text
            v-if="tr.isActive"
            :x="tr.midPoint.x"
            :y="tr.midPoint.y - 6"
            text-anchor="middle"
            fill="#e2e8f0"
            font-size="11"
            font-weight="bold"
            class="transition-label"
          >
            {{ tr.label }}
          </text>
        </g>
      </svg>

      <!-- Capa de Nodos HTML Organizados en Niveles Jerárquicos -->
      <div class="diagram-nodes-hierarchy q-gutter-y-xl">
        <!-- Nivel 1: Estado Inicial -->
        <div v-if="initialStatuses.length > 0" class="row justify-center q-gutter-md">
          <div
            v-for="st in initialStatuses"
            :key="st.id"
            :ref="(el) => registerNodeRef(st.id, el as HTMLElement)"
            class="status-node-wrapper"
            :class="{
              'status-node--hovered': hoveredStatusId === st.id,
              'status-node--dimmed': hoveredStatusId && hoveredStatusId !== st.id && !isConnectedToHovered(st.id),
            }"
            @mouseenter="hoveredStatusId = st.id"
          >
            <q-card
              flat
              bordered
              class="status-node-card cursor-pointer"
              :style="{ borderColor: st.color, boxShadow: hoveredStatusId === st.id ? `0 0 16px ${st.color}55` : 'none' }"
              @click="$emit('edit-status', st)"
            >
              <div class="row items-center justify-between q-mb-xs">
                <div class="row items-center q-gutter-x-xs">
                  <span class="status-color-dot" :style="{ backgroundColor: st.color }"></span>
                  <q-icon :name="st.icon || 'sym_r_flag'" size="16px" :style="{ color: st.color }" />
                  <span class="text-weight-bold text-white text-body2 ellipsis">{{ st.name }}</span>
                </div>
                <q-badge color="blue-9" class="text-caption">Inicial</q-badge>
              </div>

              <div class="row items-center justify-between text-caption text-grey-4 q-mt-xs">
                <span>{{ st.conversations_count || 0 }} chats</span>
                <span class="text-teal-4 text-weight-medium">Por defecto ➔</span>
              </div>
            </q-card>
          </div>
        </div>

        <!-- Nivel 2: En Proceso (Etapas Intermedias de Calificación) -->
        <div v-if="inProgressStatuses.length > 0" class="row justify-center q-gutter-lg">
          <div
            v-for="st in inProgressStatuses"
            :key="st.id"
            :ref="(el) => registerNodeRef(st.id, el as HTMLElement)"
            class="status-node-wrapper"
            :class="{
              'status-node--hovered': hoveredStatusId === st.id,
              'status-node--dimmed': hoveredStatusId && hoveredStatusId !== st.id && !isConnectedToHovered(st.id),
            }"
            @mouseenter="hoveredStatusId = st.id"
          >
            <q-card
              flat
              bordered
              class="status-node-card cursor-pointer"
              :style="{ borderColor: st.color, boxShadow: hoveredStatusId === st.id ? `0 0 16px ${st.color}55` : 'none' }"
              @click="$emit('edit-status', st)"
            >
              <div class="row items-center justify-between q-mb-xs">
                <div class="row items-center q-gutter-x-xs">
                  <span class="status-color-dot" :style="{ backgroundColor: st.color }"></span>
                  <q-icon :name="st.icon || 'sym_r_forum'" size="16px" :style="{ color: st.color }" />
                  <span class="text-weight-bold text-white text-body2 ellipsis">{{ st.name }}</span>
                </div>
                <q-badge color="cyan-9" class="text-caption">En Proceso</q-badge>
              </div>

              <div class="row items-center justify-between text-caption text-grey-4 q-mt-xs">
                <span>{{ st.conversations_count || 0 }} chats</span>
                <span class="text-grey-5 text-caption">
                  {{ (st.allowed_previous_statuses || []).length }} requisitos
                </span>
              </div>
            </q-card>
          </div>
        </div>

        <!-- Nivel 3: Conversión / Ganados (Éxito) -->
        <div v-if="wonStatuses.length > 0" class="row justify-center q-gutter-lg">
          <div
            v-for="st in wonStatuses"
            :key="st.id"
            :ref="(el) => registerNodeRef(st.id, el as HTMLElement)"
            class="status-node-wrapper"
            :class="{
              'status-node--hovered': hoveredStatusId === st.id,
              'status-node--dimmed': hoveredStatusId && hoveredStatusId !== st.id && !isConnectedToHovered(st.id),
            }"
            @mouseenter="hoveredStatusId = st.id"
          >
            <q-card
              flat
              bordered
              class="status-node-card cursor-pointer status-node-card--won"
              :style="{ borderColor: st.color, boxShadow: hoveredStatusId === st.id ? `0 0 20px ${st.color}66` : 'none' }"
              @click="$emit('edit-status', st)"
            >
              <div class="row items-center justify-between q-mb-xs">
                <div class="row items-center q-gutter-x-xs">
                  <span class="status-color-dot" :style="{ backgroundColor: st.color }"></span>
                  <q-icon :name="st.icon || 'sym_r_check_circle'" size="18px" :style="{ color: st.color }" />
                  <span class="text-weight-bold text-white text-body2 ellipsis">{{ st.name }}</span>
                </div>
                <q-badge color="positive" class="text-caption">🏆 Ganado</q-badge>
              </div>

              <div class="row items-center justify-between text-caption text-grey-4 q-mt-xs">
                <span>{{ st.conversations_count || 0 }} inscritos</span>
                <span class="text-positive text-weight-medium">Meta alcanzada</span>
              </div>
            </q-card>
          </div>
        </div>

        <!-- Nivel 4: Descarte / Perdidos (Salida) -->
        <div v-if="lostStatuses.length > 0" class="row justify-center q-gutter-lg q-pt-md">
          <div
            v-for="st in lostStatuses"
            :key="st.id"
            :ref="(el) => registerNodeRef(st.id, el as HTMLElement)"
            class="status-node-wrapper status-node-wrapper--lost"
            :class="{
              'status-node--hovered': hoveredStatusId === st.id,
              'status-node--dimmed': hoveredStatusId && hoveredStatusId !== st.id && !isConnectedToHovered(st.id),
            }"
            @mouseenter="hoveredStatusId = st.id"
          >
            <q-card
              flat
              bordered
              class="status-node-card cursor-pointer status-node-card--lost"
              :style="{ borderColor: st.color, boxShadow: hoveredStatusId === st.id ? `0 0 16px ${st.color}55` : 'none' }"
              @click="$emit('edit-status', st)"
            >
              <div class="row items-center justify-between q-mb-xs">
                <div class="row items-center q-gutter-x-xs">
                  <span class="status-color-dot" :style="{ backgroundColor: st.color }"></span>
                  <q-icon :name="st.icon || 'sym_r_cancel'" size="16px" :style="{ color: st.color }" />
                  <span class="text-weight-bold text-white text-body2 ellipsis">{{ st.name }}</span>
                </div>
                <q-badge color="negative" class="text-caption">❌ Descartado</q-badge>
              </div>

              <div class="row items-center justify-between text-caption text-grey-4 q-mt-xs">
                <span>{{ st.conversations_count || 0 }} chats</span>
                <span class="text-negative text-caption">Fin del ciclo</span>
              </div>
            </q-card>
          </div>
        </div>
      </div>
    </div>

    <!-- Panel Informativo de Inspección al pasar cursor -->
    <div v-if="hoveredStatus" class="hover-info-panel q-mt-md q-pa-md rounded-borders">
      <div class="row items-center justify-between">
        <div class="row items-center q-gutter-x-sm">
          <span class="status-color-dot" :style="{ backgroundColor: hoveredStatus.color }"></span>
          <span class="text-weight-bold text-white">{{ hoveredStatus.name }}</span>
          <q-badge :color="getStageBadgeColor(hoveredStatus.stage_type)">
            {{ formatStageType(hoveredStatus.stage_type) }}
          </q-badge>
        </div>

        <div class="text-caption text-grey-4">
          Haz clic en la tarjeta para modificar sus propiedades o dependencias
        </div>
      </div>

      <div class="row q-col-gutter-md q-mt-xs">
        <div class="col-12 col-md-6">
          <div class="text-caption text-cyan-4 text-weight-medium">
            ⬅️ Requisitos Previos (Para llegar a este estado):
          </div>
          <div v-if="hoveredStatus.allowed_previous_statuses?.length" class="row q-gutter-xs q-mt-xs">
            <q-chip
              v-for="prev in hoveredStatus.allowed_previous_statuses"
              :key="prev.id"
              dense
              dark
              size="sm"
              :style="{ backgroundColor: prev.color + '22', borderColor: prev.color, border: '1px solid' }"
            >
              {{ prev.name }}
            </q-chip>
          </div>
          <div v-else class="text-caption text-grey-5 italic q-mt-xs">
            Sin restricciones (Disponible desde el inicio).
          </div>
        </div>

        <div class="col-12 col-md-6">
          <div class="text-caption text-teal-4 text-weight-medium">
            ➡️ Siguientes Estados Permitidos (Hacia dónde puede avanzar):
          </div>
          <div v-if="outgoingStatusesOfHovered.length" class="row q-gutter-xs q-mt-xs">
            <q-chip
              v-for="nxt in outgoingStatusesOfHovered"
              :key="nxt.id"
              dense
              dark
              size="sm"
              :style="{ backgroundColor: nxt.color + '22', borderColor: nxt.color, border: '1px solid' }"
            >
              {{ nxt.name }}
            </q-chip>
          </div>
          <div v-else class="text-caption text-grey-5 italic q-mt-xs">
            Estado terminal (No tiene transiciones siguientes configuradas).
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, nextTick, watch } from 'vue'

const props = defineProps<{
  statuses: any[]
}>()

defineEmits<{
  (e: 'edit-status', status: any): void
}>()

const diagramContainerRef = ref<HTMLElement | null>(null)
const nodeElements = ref<Map<string, HTMLElement>>(new Map())
const hoveredStatusId = ref<string | null>(null)
const highlightedTransition = ref<string | null>(null)

const canvasSize = ref({ width: 850, height: 600 })

function registerNodeRef(id: string, el: HTMLElement | null) {
  if (el) {
    nodeElements.value.set(id, el)
  } else {
    nodeElements.value.delete(id)
  }
}

// Clasificación de estados en carriles jerárquicos
const initialStatuses = computed(() => {
  return props.statuses.filter((s) => s.stage_type === 'initial')
})

const inProgressStatuses = computed(() => {
  return props.statuses.filter((s) => s.stage_type === 'in_progress')
})

const wonStatuses = computed(() => {
  return props.statuses.filter((s) => s.stage_type === 'won')
})

const lostStatuses = computed(() => {
  return props.statuses.filter((s) => s.stage_type === 'lost')
})

const hoveredStatus = computed(() => {
  if (!hoveredStatusId.value) return null
  return props.statuses.find((s) => s.id === hoveredStatusId.value) || null
})

// Estados hacia donde puede saltar el estado sobre el que está el cursor
const outgoingStatusesOfHovered = computed(() => {
  if (!hoveredStatusId.value) return []
  return props.statuses.filter((s) => {
    return (s.allowed_previous_statuses || []).some((p: any) => p.id === hoveredStatusId.value)
  })
})

function isConnectedToHovered(statusId: string): boolean {
  if (!hoveredStatusId.value) return false
  if (statusId === hoveredStatusId.value) return true

  // ¿Es previo al hovered?
  const isIncoming = (hoveredStatus.value?.allowed_previous_statuses || []).some(
    (p: any) => p.id === statusId
  )
  if (isIncoming) return true

  // ¿Es posterior al hovered?
  const isOutgoing = outgoingStatusesOfHovered.value.some((o: any) => o.id === statusId)
  return isOutgoing
}

// Lista plana de transiciones (from_id -> to_id)
const transitions = computed(() => {
  const result: { fromId: string; toId: string; fromName: string; toName: string; isLost: boolean }[] = []

  for (const target of props.statuses) {
    const prevs = target.allowed_previous_statuses || []
    for (const source of prevs) {
      result.push({
        fromId: source.id,
        toId: target.id,
        fromName: source.name,
        toName: target.name,
        isLost: target.stage_type === 'lost',
      })
    }
  }

  return result
})

// Cálculo de coordenadas geométricas relativas de cada nodo y de las flechas SVG
const nodePositions = ref<Map<string, { left: number; top: number; width: number; height: number }>>(new Map())

function recalculateNodePositions() {
  if (!diagramContainerRef.value) return

  const containerRect = diagramContainerRef.value.getBoundingClientRect()
  canvasSize.value = {
    width: Math.max(800, containerRect.width),
    height: Math.max(550, containerRect.height),
  }

  const positions = new Map<string, { left: number; top: number; width: number; height: number }>()

  nodeElements.value.forEach((el, id) => {
    const rect = el.getBoundingClientRect()
    positions.set(id, {
      left: rect.left - containerRect.left,
      top: rect.top - containerRect.top,
      width: rect.width,
      height: rect.height,
    })
  })

  nodePositions.value = positions
}

// Generar rutas SVG (curvas de Bezier y puntos de anclaje)
const computedTransitions = computed(() => {
  return transitions.value.map((tr) => {
    const sourcePos = nodePositions.value.get(tr.fromId)
    const targetPos = nodePositions.value.get(tr.toId)

    if (!sourcePos || !targetPos) {
      return {
        id: `${tr.fromId}->${tr.toId}`,
        path: '',
        strokeColor: '#475569',
        strokeWidth: 1.5,
        markerId: 'url(#arrow-default)',
        isActive: false,
        isLost: tr.isLost,
        midPoint: { x: 0, y: 0 },
        label: 'Avanza',
      }
    }

    // Determinar puntos de anclaje según la disposición relativa
    const isTargetBelow = targetPos.top > sourcePos.top + 30
    const isTargetToTheRight = targetPos.left > sourcePos.left + sourcePos.width + 10

    let startX: number
    let startY: number
    let endX: number
    let endY: number

    if (isTargetBelow) {
      // De arriba a abajo (ej: No Contactado -> Contactado)
      startX = sourcePos.left + sourcePos.width / 2
      startY = sourcePos.top + sourcePos.height
      endX = targetPos.left + targetPos.width / 2
      endY = targetPos.top - 4
    } else if (isTargetToTheRight) {
      // De izquierda a derecha
      startX = sourcePos.left + sourcePos.width
      startY = sourcePos.top + sourcePos.height / 2
      endX = targetPos.left - 4
      endY = targetPos.top + targetPos.height / 2
    } else {
      // Conexión genérica adaptativa
      startX = sourcePos.left + sourcePos.width / 2
      startY = sourcePos.top + sourcePos.height
      endX = targetPos.left + targetPos.width / 2
      endY = targetPos.top - 4
    }

    // Curva cúbica Bezier suave
    const deltaY = endY - startY
    const cpy1 = startY + deltaY * 0.45
    const cpy2 = endY - deltaY * 0.45
    const path = `M ${startX} ${startY} C ${startX} ${cpy1}, ${endX} ${cpy2}, ${endX} ${endY}`

    // Determinar si la conexión está resaltada por hover
    const isOutgoingFromHovered = hoveredStatusId.value === tr.fromId
    const isIncomingToHovered = hoveredStatusId.value === tr.toId
    const isHoveredTransition = highlightedTransition.value === `${tr.fromId}->${tr.toId}`
    const isActive = isOutgoingFromHovered || isIncomingToHovered || isHoveredTransition

    let strokeColor = '#475569'
    let markerId = 'url(#arrow-default)'
    let strokeWidth = 1.5

    if (tr.isLost) {
      strokeColor = isActive ? '#ef4444' : '#7f1d1d88'
      markerId = 'url(#arrow-lost)'
    }

    if (isOutgoingFromHovered) {
      strokeColor = '#2dd4bf' // Teal brillante saliente
      markerId = 'url(#arrow-outgoing)'
      strokeWidth = 2.5
    } else if (isIncomingToHovered) {
      strokeColor = '#38bdf8' // Azul cielo entrante
      markerId = 'url(#arrow-incoming)'
      strokeWidth = 2.5
    }

    return {
      id: `${tr.fromId}->${tr.toId}`,
      path,
      strokeColor,
      strokeWidth,
      markerId,
      isActive,
      isLost: tr.isLost,
      midPoint: {
        x: (startX + endX) / 2,
        y: (startY + endY) / 2,
      },
      label: tr.isLost ? 'Descarte' : 'Avanza',
    }
  })
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

let resizeObserver: ResizeObserver | null = null

onMounted(async () => {
  await nextTick()
  recalculateNodePositions()

  if (diagramContainerRef.value && typeof ResizeObserver !== 'undefined') {
    resizeObserver = new ResizeObserver(() => {
      recalculateNodePositions()
    })
    resizeObserver.observe(diagramContainerRef.value)
  }

  window.addEventListener('resize', recalculateNodePositions)
})

onUnmounted(() => {
  if (resizeObserver) resizeObserver.disconnect()
  window.removeEventListener('resize', recalculateNodePositions)
})

watch(
  () => props.statuses,
  async () => {
    await nextTick()
    setTimeout(recalculateNodePositions, 100)
  },
  { deep: true }
)
</script>

<style scoped>
.status-flow-diagram-wrapper {
  background: rgba(15, 23, 42, 0.4);
  border: 1px solid var(--crm-color-border, rgba(255, 255, 255, 0.08));
  border-radius: 12px;
  padding: 18px;
}

.legend-dot {
  width: 9px;
  height: 9px;
  border-radius: 50%;
  display: inline-block;
}

.diagram-canvas {
  min-height: 520px;
  background-color: #0b1120;
  background-image: radial-gradient(circle, rgba(255, 255, 255, 0.06) 1px, transparent 1px);
  background-size: 24px 24px;
  border-radius: 10px;
  border: 1px solid rgba(255, 255, 255, 0.06);
  padding: 32px 20px;
  overflow: hidden;
}

.diagram-svg-layer {
  pointer-events: auto;
  z-index: 1;
}

.diagram-nodes-hierarchy {
  position: relative;
  z-index: 2;
}

.status-node-wrapper {
  width: 250px;
  transition: transform 0.2s ease, opacity 0.2s ease;
}

.status-node--hovered {
  transform: translateY(-3px) scale(1.02);
  z-index: 10;
}

.status-node--dimmed {
  opacity: 0.35;
}

.status-node-card {
  background: rgba(17, 24, 39, 0.95);
  border-radius: 10px;
  padding: 12px 14px;
  border-width: 1.5px;
  transition: all 0.25s ease;
  backdrop-filter: blur(8px);
}

.status-node-card:hover {
  background: rgba(30, 41, 59, 0.98);
}

.status-node-card--won {
  border-style: solid;
  border-width: 2px;
}

.status-node-card--lost {
  border-style: dashed;
}

.status-color-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  display: inline-block;
  flex-shrink: 0;
}

.transition-path {
  transition: stroke 0.2s ease, stroke-width 0.2s ease;
}

.transition-path--active {
  animation: flowPulse 1.4s infinite linear;
}

@keyframes flowPulse {
  0% {
    stroke-dashoffset: 24;
  }
  100% {
    stroke-dashoffset: 0;
  }
}

.transition-label {
  paint-order: stroke;
  stroke: #0f172a;
  stroke-width: 4px;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.hover-info-panel {
  background: rgba(30, 41, 59, 0.7);
  border: 1px solid rgba(255, 255, 255, 0.1);
}
</style>
