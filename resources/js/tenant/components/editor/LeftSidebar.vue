<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { VueDraggable } from 'vue-draggable-plus'
import { useBlockCatalogStore } from '@tenant/stores/blockCatalog'
import { useBuilderSectionStore } from '@tenant/stores/builderSection'
import { useBuilderPageStore } from '@tenant/stores/builderPage'
import { useGlobalSectionStore } from '@tenant/stores/globalSection'
import { useEditorModeStore } from '@tenant/stores/editorMode'
import BlockIcon from '@tenant/components/blocks/BlockIcon.vue'
import {
  Search,
  Trash2,
  X,
  Star,
  Home,
  FileText,
  Plus,
  GripVertical,
  PanelsTopLeft,
  Type as TypeIcon,
  Image as ImageIcon,
  LayoutGrid,
  MousePointerClick,
} from 'lucide-vue-next'
import type { SiteSection } from '@/types/builder'

const props = defineProps<{ pageId: number }>()

const router = useRouter()
const catalogStore = useBlockCatalogStore()
const sectionStore = useBuilderSectionStore()
const pageStore = useBuilderPageStore()
const globalStore = useGlobalSectionStore()
const editorMode = useEditorModeStore()

const tab = ref<'blocks' | 'layers' | 'pages'>('blocks')
const showCreatePage = ref(false)
const newPageTitle = ref('')
const newPageSlug = ref('')
const search = ref('')

type Cat = 'headers' | 'content' | 'media' | 'grids' | 'interactive'
const categories: { key: Cat; label: string; icon: any }[] = [
  { key: 'headers', label: 'Encabezados', icon: PanelsTopLeft },
  { key: 'content', label: 'Contenido', icon: TypeIcon },
  { key: 'media', label: 'Multimedia', icon: ImageIcon },
  { key: 'grids', label: 'Grillas', icon: LayoutGrid },
  { key: 'interactive', label: 'Interactivos', icon: MousePointerClick },
]

const filteredByCat = computed(() => {
  const term = search.value.trim().toLowerCase()
  return categories.map(c => ({
    ...c,
    blocks: catalogStore.getByCategory(c.key).filter(b =>
      !term || b.name.toLowerCase().includes(term) || (b.description || '').toLowerCase().includes(term),
    ),
  })).filter(c => c.blocks.length)
})

onMounted(() => { globalStore.fetchGlobals() })

async function addBlock(key: string) {
  await sectionStore.addSection(props.pageId, key)
  editorMode.markDirty()
}

function onDragStart(key: string, e: DragEvent) {
  if (!e.dataTransfer) return
  e.dataTransfer.setData('application/x-block-key', key)
  e.dataTransfer.setData('text/plain', key)
  e.dataTransfer.effectAllowed = 'copy'
  document.body.classList.add('is-dragging-block')
}

function onDragEnd() {
  document.body.classList.remove('is-dragging-block')
}

async function addGlobal(sectionId: number) {
  await globalStore.addToPage(sectionId, props.pageId)
  await sectionStore.fetchSections(props.pageId)
  editorMode.markDirty()
}

function selectLayer(id: number) {
  editorMode.selectSection(editorMode.selectedSectionId === id ? null : id)
}

const layerList = ref<SiteSection[]>([])
watch(() => sectionStore.sections, (val) => { layerList.value = [...val] }, { immediate: true, deep: false })

async function onLayerReorder() {
  const ids = layerList.value.map(s => s.id)
  await sectionStore.reorderSections(props.pageId, ids)
  editorMode.markDirty()
}

async function deleteLayer(id: number) {
  await sectionStore.deleteSection(props.pageId, id)
  editorMode.markDirty()
}

function slugify(t: string) { return t.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '') }
function onTitleInput() { newPageSlug.value = slugify(newPageTitle.value) }

async function createPage() {
  if (!newPageTitle.value || !newPageSlug.value) return
  const page = await pageStore.createPage({ title: newPageTitle.value, slug: newPageSlug.value })
  showCreatePage.value = false
  newPageTitle.value = ''
  newPageSlug.value = ''
  if (page) router.push(page.is_homepage ? '/' : `/${page.slug}`)
}

function goToPage(page: any) {
  if (page.id === props.pageId) return
  if (sectionStore.hasPendingChanges() && !confirm('Tenés cambios sin guardar en esta página. ¿Descartarlos y cambiar?')) return
  router.push(page.is_homepage ? '/' : `/${page.slug}`)
}

async function deletePage(id: number) {
  await pageStore.deletePage(id)
}
</script>

<template>
  <div class="w-72 bg-background border-r flex flex-col h-full overflow-hidden">
    <!-- Tabs -->
    <div class="flex border-b flex-shrink-0">
      <button
        v-for="t in (['blocks', 'layers', 'pages'] as const)"
        :key="t"
        class="flex-1 py-2.5 text-xs font-medium text-center border-b-2 transition-colors"
        :class="tab === t ? 'border-primary text-foreground' : 'border-transparent text-muted-foreground hover:text-foreground'"
        @click="tab = t"
      >
        {{ t === 'blocks' ? 'Bloques' : t === 'layers' ? 'Capas' : 'Paginas' }}
      </button>
    </div>

    <div class="flex-1 overflow-y-auto">
      <!-- BLOCKS TAB -->
      <template v-if="tab === 'blocks'">
        <!-- Search -->
        <div class="p-3 border-b sticky top-0 bg-background z-10">
          <div class="relative">
            <Search :size="14" :stroke-width="1.75" class="absolute left-2.5 top-1/2 -translate-y-1/2 text-muted-foreground" />
            <input
              v-model="search"
              type="text"
              placeholder="Buscar bloque..."
              class="w-full pl-8 pr-7 py-1.5 border rounded-md text-xs bg-background placeholder:text-muted-foreground focus:outline-none focus:ring-1 focus:ring-primary"
            />
            <button v-if="search" class="absolute right-2 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground" @click="search = ''">
              <X :size="14" :stroke-width="2" />
            </button>
          </div>
        </div>

        <div class="p-3">
          <div v-if="!filteredByCat.length" class="text-center py-10 text-xs text-muted-foreground">
            No se encontraron bloques
          </div>

          <div v-for="cat in filteredByCat" :key="cat.key" class="mb-5">
            <div class="flex items-center gap-1.5 mb-2 px-0.5">
              <component :is="cat.icon" :size="12" :stroke-width="2" class="text-muted-foreground" />
              <h4 class="text-[10px] font-semibold text-muted-foreground uppercase tracking-wider">{{ cat.label }}</h4>
            </div>
            <div class="space-y-1.5">
              <button
                v-for="block in cat.blocks"
                :key="block.key"
                draggable="true"
                class="block-card group w-full flex items-start gap-2.5 p-2.5 border rounded-lg text-left hover:border-primary hover:bg-primary/5 transition-all cursor-grab active:cursor-grabbing"
                :title="block.description || 'Click o arrastra al canvas'"
                @click="addBlock(block.key)"
                @dragstart="onDragStart(block.key, $event)"
                @dragend="onDragEnd"
              >
                <div class="flex-shrink-0 w-9 h-9 rounded-md bg-muted/60 group-hover:bg-primary/10 flex items-center justify-center text-muted-foreground group-hover:text-primary transition-colors">
                  <BlockIcon :name="block.icon" :size="18" :stroke-width="1.75" />
                </div>
                <div class="flex-1 min-w-0">
                  <div class="text-xs font-medium leading-tight truncate">{{ block.name }}</div>
                  <div v-if="block.description" class="text-[10px] text-muted-foreground leading-snug mt-0.5 line-clamp-2">
                    {{ block.description }}
                  </div>
                </div>
                <Plus :size="14" :stroke-width="2" class="flex-shrink-0 mt-1 text-muted-foreground/40 group-hover:text-primary transition-colors" />
              </button>
            </div>
          </div>

          <!-- Global sections -->
          <div v-if="globalStore.globals.length && !search" class="mb-4">
            <div class="flex items-center gap-1.5 mb-2 px-0.5">
              <Star :size="12" :stroke-width="2" class="text-amber-500" />
              <h4 class="text-[10px] font-semibold text-muted-foreground uppercase tracking-wider">Globales</h4>
            </div>
            <div class="space-y-1">
              <button
                v-for="g in globalStore.globals"
                :key="g.id"
                class="w-full flex items-center gap-2 p-2 border rounded-lg text-xs hover:bg-muted/50 hover:border-primary/30 transition-colors text-left"
                @click="addGlobal(g.id)"
              >
                <Star :size="12" :stroke-width="2" class="text-amber-500 flex-shrink-0" />
                <span class="truncate">{{ g.global_name }}</span>
              </button>
            </div>
          </div>
        </div>
      </template>

      <!-- LAYERS TAB -->
      <template v-if="tab === 'layers'">
        <div class="p-3">
          <div v-if="!layerList.length" class="text-center py-10 text-xs text-muted-foreground">
            <p class="mb-1">Aun no hay secciones</p>
            <p class="text-[10px]">Agrega bloques desde la tab "Bloques"</p>
          </div>
          <VueDraggable
            v-else
            v-model="layerList"
            :animation="180"
            handle=".layer-handle"
            ghost-class="layer-ghost"
            chosen-class="layer-chosen"
            class="space-y-1"
            @update="onLayerReorder"
          >
            <div
              v-for="(section, idx) in layerList"
              :key="section.id"
              class="layer-row flex items-center gap-1.5 p-2 rounded-lg text-xs cursor-pointer transition-colors group"
              :class="editorMode.selectedSectionId === section.id ? 'bg-primary/10 border border-primary/30' : 'hover:bg-muted/50 border border-transparent'"
              @click="selectLayer(section.id)"
            >
              <span class="layer-handle flex-shrink-0 text-muted-foreground/40 hover:text-foreground cursor-grab active:cursor-grabbing -ml-1" title="Arrastrar para reordenar">
                <GripVertical :size="14" :stroke-width="1.75" />
              </span>
              <span class="text-muted-foreground text-[10px] w-3 text-center flex-shrink-0">{{ idx + 1 }}</span>
              <BlockIcon :name="catalogStore.getBlockType(section.block_type_key)?.icon" :size="14" :stroke-width="1.75" class="flex-shrink-0 text-muted-foreground" />
              <span class="flex-1 truncate font-medium">
                {{ catalogStore.getBlockType(section.block_type_key)?.name || section.block_type_key }}
              </span>
              <button class="p-0.5 text-muted-foreground hover:text-destructive rounded opacity-0 group-hover:opacity-100 transition-opacity flex-shrink-0" @click.stop="deleteLayer(section.id)" title="Eliminar">
                <Trash2 :size="12" :stroke-width="2" />
              </button>
            </div>
          </VueDraggable>
        </div>
      </template>

      <!-- PAGES TAB -->
      <template v-if="tab === 'pages'">
        <div class="p-3">
          <button
            class="w-full px-3 py-2 bg-primary text-primary-foreground rounded-lg text-xs font-medium hover:bg-primary/90 mb-3 flex items-center justify-center gap-1.5"
            @click="showCreatePage = !showCreatePage"
          >
            <Plus :size="14" :stroke-width="2.5" />
            Nueva Pagina
          </button>

          <div v-if="showCreatePage" class="border rounded-lg p-2.5 mb-3 space-y-2 bg-muted/30">
            <input v-model="newPageTitle" type="text" class="w-full px-2.5 py-1.5 border rounded text-xs bg-background" placeholder="Titulo de la pagina" @input="onTitleInput" />
            <div class="flex items-center gap-1">
              <span class="text-xs text-muted-foreground">/</span>
              <input v-model="newPageSlug" type="text" class="w-full px-2.5 py-1.5 border rounded text-xs bg-background" placeholder="slug" />
            </div>
            <div class="flex gap-1.5">
              <button class="flex-1 px-2 py-1.5 border rounded text-xs hover:bg-muted" @click="showCreatePage = false">Cancelar</button>
              <button class="flex-1 px-2 py-1.5 bg-primary text-primary-foreground rounded text-xs disabled:opacity-50" :disabled="!newPageTitle || !newPageSlug" @click="createPage">Crear</button>
            </div>
          </div>

          <div class="space-y-1">
            <div
              v-for="page in pageStore.pages"
              :key="page.id"
              class="flex items-center justify-between p-2 rounded-lg text-xs cursor-pointer hover:bg-muted/50 transition-colors"
              :class="{ 'bg-primary/10 border border-primary/30': page.id === props.pageId, 'border border-transparent': page.id !== props.pageId }"
              @click="goToPage(page)"
            >
              <div class="flex items-center gap-1.5 min-w-0">
                <component :is="page.is_homepage ? Home : FileText" :size="13" :stroke-width="1.75" class="flex-shrink-0 text-muted-foreground" />
                <span class="truncate font-medium">{{ page.title }}</span>
              </div>
              <div class="flex items-center gap-1.5 flex-shrink-0">
                <span
                  class="text-[10px] px-1.5 py-0.5 rounded-full font-medium"
                  :class="page.status === 'published' ? 'bg-green-100 text-green-700 dark:bg-green-900/50 dark:text-green-400' : 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/50 dark:text-yellow-400'"
                >
                  {{ page.status === 'published' ? 'Publicada' : 'Borrador' }}
                </span>
                <button
                  v-if="!page.is_homepage"
                  class="p-0.5 text-muted-foreground hover:text-destructive rounded"
                  @click.stop="deletePage(page.id)"
                  title="Eliminar"
                >
                  <Trash2 :size="12" :stroke-width="2" />
                </button>
              </div>
            </div>
          </div>
        </div>
      </template>
    </div>
  </div>
</template>

<style scoped>
.layer-ghost {
  opacity: 0.4;
  background: rgb(99 102 241 / 0.08) !important;
  border: 1px dashed rgb(99 102 241 / 0.5) !important;
}
.layer-chosen {
  cursor: grabbing !important;
}
</style>
