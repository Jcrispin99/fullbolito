<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useBlockCatalogStore } from '@tenant/stores/blockCatalog'
import { useBuilderSectionStore } from '@tenant/stores/builderSection'
import { useGlobalSectionStore } from '@tenant/stores/globalSection'
import { useEditorModeStore } from '@tenant/stores/editorMode'

const props = defineProps<{
  pageId: number
}>()

const catalogStore = useBlockCatalogStore()
const sectionStore = useBuilderSectionStore()
const globalStore = useGlobalSectionStore()
const editorMode = useEditorModeStore()
const showGlobals = ref(false)

onMounted(async () => {
  await globalStore.fetchGlobals()
})

async function addBlock(key: string) {
  await sectionStore.addSection(props.pageId, key)
  editorMode.markDirty()
}

async function addGlobal(sectionId: number) {
  await globalStore.addToPage(sectionId, props.pageId)
  await sectionStore.fetchSections(props.pageId)
  editorMode.markDirty()
  showGlobals.value = false
}
</script>

<template>
  <div class="fixed bottom-0 left-0 right-0 bg-zinc-900 border-t border-zinc-700 z-[90]">
    <!-- Globals popup -->
    <Transition name="fade">
      <div v-if="showGlobals && globalStore.globals.length" class="absolute bottom-full left-0 right-0 bg-zinc-800 border-t border-zinc-700 py-3 px-4">
        <div class="max-w-5xl mx-auto">
          <div class="flex items-center justify-between mb-2">
            <span class="text-xs text-white/70 font-medium">Secciones Globales</span>
            <button class="text-xs text-white/50 hover:text-white" @click="showGlobals = false">Cerrar</button>
          </div>
          <div class="flex gap-2 overflow-x-auto">
            <button
              v-for="section in globalStore.globals"
              :key="section.id"
              class="flex-shrink-0 px-3 py-2 bg-zinc-700 rounded text-xs text-white/80 hover:bg-zinc-600 hover:text-white transition-colors"
              @click="addGlobal(section.id)"
            >
              <div class="font-medium">{{ section.global_name }}</div>
              <div class="text-white/50 text-xs mt-0.5">{{ section.block_type_key }}</div>
            </button>
          </div>
        </div>
      </div>
    </Transition>

    <!-- Main palette bar -->
    <div class="py-2 px-4">
      <div class="max-w-5xl mx-auto flex items-center gap-1.5 overflow-x-auto">
        <span class="text-xs text-white/50 mr-2 whitespace-nowrap">+ Agregar:</span>
        <button
          v-for="block in catalogStore.blockTypes"
          :key="block.key"
          class="flex items-center gap-1.5 px-3 py-1.5 rounded text-xs text-white/80 hover:bg-white/10 hover:text-white transition-colors whitespace-nowrap"
          @click="addBlock(block.key)"
        >
          {{ block.name }}
        </button>

        <!-- Global sections toggle -->
        <div v-if="globalStore.globals.length" class="w-px h-5 bg-white/20 mx-1" />
        <button
          v-if="globalStore.globals.length"
          class="flex items-center gap-1.5 px-3 py-1.5 rounded text-xs whitespace-nowrap transition-colors"
          :class="showGlobals ? 'bg-white/20 text-white' : 'text-yellow-400/80 hover:bg-white/10 hover:text-yellow-400'"
          @click="showGlobals = !showGlobals"
        >
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
          </svg>
          Globales ({{ globalStore.globals.length }})
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.15s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
