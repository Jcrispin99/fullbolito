<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import BuilderDrawer from '@tenant/components/BuilderDrawer.vue'
import { useBuilderPageStore } from '@tenant/stores/builderPage'
import { useEditorModeStore } from '@tenant/stores/editorMode'

const router = useRouter()
const pageStore = useBuilderPageStore()
const editorMode = useEditorModeStore()

const showCreate = ref(false)
const newTitle = ref('')
const newSlug = ref('')
const editingPageId = ref<number | null>(null)
const editTitle = ref('')
const confirmDeleteId = ref<number | null>(null)

function slugify(text: string): string {
  return text.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '')
}

function onTitleInput() {
  newSlug.value = slugify(newTitle.value)
}

async function createPage() {
  if (!newTitle.value || !newSlug.value) return
  const page = await pageStore.createPage({ title: newTitle.value, slug: newSlug.value })
  showCreate.value = false
  newTitle.value = ''
  newSlug.value = ''
  if (page) navigateToPage(page)
}

function navigateToPage(page: any) {
  editorMode.closeDrawer()
  router.push(page.is_homepage ? '/' : `/${page.slug}`)
}

async function startRename(page: any) {
  editingPageId.value = page.id
  editTitle.value = page.title
}

async function saveRename(page: any) {
  if (!editTitle.value) return
  await pageStore.updatePage(page.id, { title: editTitle.value })
  editingPageId.value = null
}

async function duplicatePage(id: number) {
  await pageStore.duplicatePage(id)
}

async function deletePage(id: number) {
  await pageStore.deletePage(id)
  confirmDeleteId.value = null
}

async function togglePublish(page: any) {
  if (page.status === 'published') {
    await pageStore.unpublishPage(page.id)
  } else {
    await pageStore.publishPage(page.id)
  }
}
</script>

<template>
  <BuilderDrawer title="Paginas" width="w-80" @close="editorMode.closeDrawer()">
    <!-- Create button -->
    <button
      class="w-full px-4 py-2.5 bg-primary text-primary-foreground rounded-lg text-sm font-medium hover:bg-primary/90 mb-4"
      @click="showCreate = !showCreate"
    >
      + Nueva Pagina
    </button>

    <!-- Create form -->
    <div v-if="showCreate" class="border rounded-lg p-3 mb-4 space-y-3">
      <div>
        <label class="block text-xs font-medium mb-1">Titulo</label>
        <input
          v-model="newTitle"
          type="text"
          class="w-full px-3 py-1.5 border rounded text-sm bg-background"
          placeholder="Ej: Contacto"
          @input="onTitleInput"
        />
      </div>
      <div>
        <label class="block text-xs font-medium mb-1">Slug (URL)</label>
        <div class="flex items-center gap-1">
          <span class="text-xs text-muted-foreground">/</span>
          <input
            v-model="newSlug"
            type="text"
            class="w-full px-3 py-1.5 border rounded text-sm bg-background"
            placeholder="contacto"
          />
        </div>
      </div>
      <div class="flex gap-2">
        <button
          class="flex-1 px-3 py-1.5 border rounded text-sm hover:bg-muted"
          @click="showCreate = false"
        >Cancelar</button>
        <button
          class="flex-1 px-3 py-1.5 bg-primary text-primary-foreground rounded text-sm hover:bg-primary/90"
          :disabled="!newTitle || !newSlug"
          @click="createPage"
        >Crear</button>
      </div>
    </div>

    <!-- Page list -->
    <div class="space-y-1.5">
      <div
        v-for="page in pageStore.pages"
        :key="page.id"
        class="border rounded-lg p-3 hover:bg-muted/50 transition-colors"
      >
        <div class="flex items-center justify-between mb-1.5">
          <!-- Title (editable or static) -->
          <div v-if="editingPageId === page.id" class="flex items-center gap-1.5 flex-1 mr-2">
            <input
              v-model="editTitle"
              type="text"
              class="flex-1 px-2 py-1 border rounded text-sm bg-background"
              @keydown.enter="saveRename(page)"
              @keydown.escape="editingPageId = null"
            />
            <button class="text-xs text-primary" @click="saveRename(page)">OK</button>
          </div>
          <div v-else class="flex items-center gap-1.5 min-w-0 cursor-pointer" @click="navigateToPage(page)">
            <span class="text-sm">{{ page.is_homepage ? '&#x1F3E0;' : '&#x1F4C4;' }}</span>
            <span class="text-sm font-medium truncate">{{ page.title }}</span>
          </div>
          <span
            class="text-xs px-1.5 py-0.5 rounded-full flex-shrink-0"
            :class="page.status === 'published'
              ? 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300'
              : 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300'"
          >
            {{ page.status === 'published' ? 'pub' : 'draft' }}
          </span>
        </div>

        <div class="text-xs text-muted-foreground mb-2">/{{ page.slug }}</div>

        <!-- Actions -->
        <div v-if="confirmDeleteId === page.id" class="flex items-center gap-2">
          <span class="text-xs text-destructive">Eliminar?</span>
          <button class="text-xs px-2 py-1 bg-destructive text-white rounded" @click="deletePage(page.id)">Si</button>
          <button class="text-xs px-2 py-1 border rounded" @click="confirmDeleteId = null">No</button>
        </div>
        <div v-else class="flex items-center gap-1.5">
          <button class="text-xs px-2 py-1 border rounded hover:bg-muted" @click="navigateToPage(page)">Abrir</button>
          <button class="text-xs px-2 py-1 border rounded hover:bg-muted" @click="togglePublish(page)">
            {{ page.status === 'published' ? 'Despub.' : 'Publicar' }}
          </button>
          <button class="text-xs px-2 py-1 border rounded hover:bg-muted" @click="startRename(page)">Renombrar</button>
          <button class="text-xs px-2 py-1 border rounded hover:bg-muted" @click="duplicatePage(page.id)">Duplicar</button>
          <button
            v-if="!page.is_homepage"
            class="text-xs px-2 py-1 border border-destructive text-destructive rounded hover:bg-destructive/10"
            @click="confirmDeleteId = page.id"
          >Eliminar</button>
        </div>
      </div>
    </div>
  </BuilderDrawer>
</template>
