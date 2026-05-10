<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import DashboardLayout from '@tenant/layouts/DashboardLayout.vue'
import { useBuilderPageStore } from '@tenant/stores/builderPage'
import { useSiteConfigStore } from '@tenant/stores/siteConfig'

const router = useRouter()
const pageStore = useBuilderPageStore()
const siteConfig = useSiteConfigStore()
const showCreateDialog = ref(false)
const newPageTitle = ref('')
const newPageSlug = ref('')

onMounted(async () => {
  await Promise.all([
    pageStore.fetchPages(1, 50),
    siteConfig.fetchSite(),
  ])
})

function slugify(text: string): string {
  return text.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '')
}

function onTitleInput() {
  newPageSlug.value = slugify(newPageTitle.value)
}

async function createPage() {
  if (!newPageTitle.value || !newPageSlug.value) return
  try {
    await pageStore.createPage({
      site_id: siteConfig.site?.id,
      title: newPageTitle.value,
      slug: newPageSlug.value,
    })
    showCreateDialog.value = false
    newPageTitle.value = ''
    newPageSlug.value = ''
  } catch { /* handled in store */ }
}

function goToPage(page: any) {
  router.push(page.is_homepage ? '/' : `/${page.slug}`)
}
</script>

<template>
  <DashboardLayout>
    <div class="max-w-5xl mx-auto p-6">
      <div class="flex items-center justify-between mb-8">
        <div>
          <h1 class="text-2xl font-bold">Páginas</h1>
          <p class="text-muted-foreground text-sm mt-1">Gestiona las páginas de tu sitio web</p>
        </div>
        <button
          class="px-4 py-2 bg-primary text-primary-foreground rounded-md text-sm font-medium hover:bg-primary/90"
          @click="showCreateDialog = true"
        >
          + Nueva Página
        </button>
      </div>

      <div v-if="pageStore.isLoading" class="text-center py-12 text-muted-foreground">Cargando...</div>

      <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <div
          v-for="page in pageStore.pages"
          :key="page.id"
          class="bg-card border rounded-lg p-5 hover:shadow-md transition-shadow group"
        >
          <div class="flex items-start justify-between mb-3">
            <div class="flex items-center gap-2">
              <span>{{ page.is_homepage ? '🏠' : '📄' }}</span>
              <h3 class="font-semibold">{{ page.title }}</h3>
            </div>
            <span
              class="text-xs px-2 py-0.5 rounded-full"
              :class="page.status === 'published'
                ? 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300'
                : 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300'"
            >
              {{ page.status }}
            </span>
          </div>
          <p class="text-sm text-muted-foreground mb-4">/{{ page.slug }}</p>
          <div class="flex items-center gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
            <button
              class="text-xs px-3 py-1.5 bg-primary text-primary-foreground rounded hover:bg-primary/90"
              @click="goToPage(page)"
            >
              Editar en sitio
            </button>
            <button
              v-if="page.status === 'draft'"
              class="text-xs px-3 py-1.5 border rounded hover:bg-muted"
              @click="pageStore.publishPage(page.id)"
            >
              Publicar
            </button>
            <button
              v-else
              class="text-xs px-3 py-1.5 border rounded hover:bg-muted"
              @click="pageStore.unpublishPage(page.id)"
            >
              Despublicar
            </button>
            <button
              class="text-xs px-3 py-1.5 border rounded hover:bg-muted"
              @click="pageStore.duplicatePage(page.id)"
            >
              Duplicar
            </button>
            <button
              v-if="!page.is_homepage"
              class="text-xs px-3 py-1.5 border border-destructive text-destructive rounded hover:bg-destructive/10"
              @click="pageStore.deletePage(page.id)"
            >
              Eliminar
            </button>
          </div>
        </div>
      </div>

      <!-- Create Dialog -->
      <Teleport to="body">
        <div v-if="showCreateDialog" class="fixed inset-0 z-50 flex items-center justify-center">
          <div class="absolute inset-0 bg-black/50" @click="showCreateDialog = false" />
          <div class="relative bg-background border rounded-lg p-6 w-full max-w-md shadow-xl">
            <h2 class="text-lg font-semibold mb-4">Nueva Página</h2>
            <div class="space-y-4">
              <div>
                <label class="block text-sm font-medium mb-1">Título</label>
                <input
                  v-model="newPageTitle"
                  type="text"
                  class="w-full px-3 py-2 border rounded-md bg-background text-sm"
                  placeholder="Ej: Menú, Contacto"
                  @input="onTitleInput"
                />
              </div>
              <div>
                <label class="block text-sm font-medium mb-1">Slug (URL)</label>
                <input
                  v-model="newPageSlug"
                  type="text"
                  class="w-full px-3 py-2 border rounded-md bg-background text-sm"
                  placeholder="ej: menu"
                />
              </div>
            </div>
            <div class="flex justify-end gap-2 mt-6">
              <button class="px-4 py-2 border rounded-md text-sm hover:bg-muted" @click="showCreateDialog = false">Cancelar</button>
              <button
                class="px-4 py-2 bg-primary text-primary-foreground rounded-md text-sm font-medium hover:bg-primary/90"
                :disabled="!newPageTitle || !newPageSlug"
                @click="createPage"
              >
                Crear
              </button>
            </div>
          </div>
        </div>
      </Teleport>
    </div>
  </DashboardLayout>
</template>
