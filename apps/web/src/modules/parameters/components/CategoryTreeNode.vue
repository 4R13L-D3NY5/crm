<template>
  <div class="xf-tree-node q-my-xs" :class="`xf-tree-node--level-${level}`">
    <!-- Fila del Nodo -->
    <div
      class="row items-center justify-between q-py-xs q-px-sm rounded-borders xf-node-row"
      :style="{ borderLeft: `3px solid ${node.color || '#10b981'}` }"
    >
      <!-- Información del Nodo -->
      <div class="row items-center q-gutter-x-xs no-wrap ellipsis col">
        <q-icon
          :name="node.icon || (level === 2 ? 'sym_r_subdirectory_arrow_right' : 'sym_r_school')"
          size="16px"
          :style="{ color: node.color || '#10b981' }"
        />

        <!-- Código de Identificación (Parametrizable) -->
        <q-badge
          v-if="node.code"
          outline
          color="teal-3"
          class="font-mono text-weight-bold q-px-xs text-caption"
          style="font-size: 0.72rem; letter-spacing: 0.5px"
        >
          {{ node.code }}
        </q-badge>

        <!-- Nombre -->
        <span
          class="text-body2 text-white ellipsis"
          :class="{ 'text-weight-bold': level <= 2, 'text-weight-medium text-grey-2': level > 2 }"
        >
          {{ node.name }}
        </span>

        <!-- Badge de Categoría Reutilizada / Multi-padre -->
        <q-badge
          v-if="node.is_shared"
          color="purple-10"
          text-color="purple-2"
          class="q-ml-xs cursor-pointer text-caption"
          style="font-size: 0.68rem"
        >
          <q-icon name="sym_r_link" size="12px" class="q-mr-xs" />
          <span>Compartida ({{ node.parents_count }} ramas)</span>
          <q-tooltip class="bg-dark text-caption">
            Ramas donde está vinculada:
            <ul class="q-my-none q-pl-md">
              <li v-for="p in node.parents" :key="p.id">{{ p.name }} ({{ p.code || 'sin código' }})</li>
            </ul>
          </q-tooltip>
        </q-badge>

        <!-- Conteo de Chats -->
        <q-badge
          v-if="node.conversations_count"
          color="teal-9"
          text-color="teal-2"
          class="q-ml-xs text-caption"
          style="font-size: 0.68rem"
        >
          {{ node.conversations_count }} chats
        </q-badge>
      </div>

      <!-- Acciones del Nodo -->
      <div class="row items-center q-gutter-xs no-wrap">
        <q-btn
          flat
          round
          dense
          icon="sym_r_add"
          color="teal-4"
          size="xs"
          @click="$emit('add-child', node.id)"
        >
          <q-tooltip>Agregar nueva subcategoría hija a {{ node.name }}</q-tooltip>
        </q-btn>

        <q-btn
          flat
          round
          dense
          icon="sym_r_link"
          color="purple-3"
          size="xs"
          @click="$emit('link-existing', node)"
        >
          <q-tooltip>Vincular / Reutilizar categoría existente bajo {{ node.name }}</q-tooltip>
        </q-btn>

        <q-btn
          flat
          round
          dense
          icon="sym_r_edit"
          color="grey-4"
          size="xs"
          @click="$emit('edit', node)"
        >
          <q-tooltip>Editar {{ node.name }}</q-tooltip>
        </q-btn>

        <q-btn
          flat
          round
          dense
          icon="sym_r_delete"
          color="negative"
          size="xs"
          @click="$emit('delete', { node, currentParentId })"
        >
          <q-tooltip v-if="node.is_shared">Desvincular o eliminar categoría compartida</q-tooltip>
          <q-tooltip v-else>Eliminar categoría</q-tooltip>
        </q-btn>
      </div>
    </div>

    <!-- Hijos Recursivos (Soporta Niveles Infinitos sin Límite Rígido) -->
    <div
      v-if="node.children && node.children.length > 0"
      class="q-ml-md q-mt-xs q-pl-xs border-left-tree"
    >
      <CategoryTreeNode
        v-for="child in node.children"
        :key="child.id"
        :node="child"
        :level="level + 1"
        :current-parent-id="node.id"
        @add-child="$emit('add-child', $event)"
        @link-existing="$emit('link-existing', $event)"
        @edit="$emit('edit', $event)"
        @delete="$emit('delete', $event)"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
defineProps<{
  node: any
  level: number
  currentParentId?: string | null
}>()

defineEmits<{
  (e: 'add-child', parentId: string): void
  (e: 'link-existing', parentNode: any): void
  (e: 'edit', node: any): void
  (e: 'delete', payload: { node: any; currentParentId?: string | null }): void
}>()
</script>

<style scoped lang="scss">
.xf-node-row {
  background: rgba(255, 255, 255, 0.025);
  border: 1px solid rgba(255, 255, 255, 0.05);
  transition: all 0.15s ease;

  &:hover {
    background: rgba(255, 255, 255, 0.05);
    border-color: rgba(255, 255, 255, 0.12);
  }
}

.border-left-tree {
  border-left: 2px solid rgba(255, 255, 255, 0.08);
  padding-left: 10px;
}
</style>
