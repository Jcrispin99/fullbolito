<script setup lang="ts">
import { computed } from 'vue'
import { useBlockCatalogStore } from '@tenant/stores/blockCatalog'
import { GripVertical, Move, Copy, Trash2 } from 'lucide-vue-next'
import type { SiteSection } from '@/types/builder'

const props = defineProps<{
  section: SiteSection
}>()

const emit = defineEmits<{
  duplicate: []
  delete: []
  toggleMode: []
}>()

const catalogStore = useBlockCatalogStore()
const blockName = computed(() => catalogStore.getBlockType(props.section.block_type_key)?.name || props.section.block_type_key)
const isFlow = computed(() => props.section.position_mode !== 'absolute')
</script>

<template>
  <div class="absolute -top-10 left-1/2 -translate-x-1/2 z-30 flex items-center gap-1 bg-zinc-900 rounded-lg shadow-xl px-1.5 py-1">
    <!-- Drag handle (only flow mode) -->
    <span
      v-if="isFlow"
      class="section-drag-handle p-1 text-white/60 hover:text-white hover:bg-white/10 rounded cursor-grab active:cursor-grabbing transition-colors"
      title="Arrastrar para reordenar"
      @click.stop
    >
      <GripVertical :size="14" :stroke-width="1.75" />
    </span>

    <span class="text-xs text-white/80 px-1.5 whitespace-nowrap font-medium">{{ blockName }}</span>
    <div class="w-px h-4 bg-white/20" />

    <button
      class="p-1 rounded transition-colors"
      :class="section.position_mode === 'absolute' ? 'bg-blue-600 text-white' : 'text-white/70 hover:text-white hover:bg-white/10'"
      title="Modo libre (posicionamiento absoluto)"
      @click="emit('toggleMode')"
    >
      <Move :size="14" :stroke-width="1.75" />
    </button>

    <button class="p-1 text-white/70 hover:text-white hover:bg-white/10 rounded transition-colors" title="Duplicar" @click="emit('duplicate')">
      <Copy :size="14" :stroke-width="1.75" />
    </button>

    <button class="p-1 text-red-400 hover:text-red-300 hover:bg-white/10 rounded transition-colors" title="Eliminar" @click="emit('delete')">
      <Trash2 :size="14" :stroke-width="1.75" />
    </button>
  </div>
</template>
